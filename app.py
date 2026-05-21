import streamlit as st
import os
import json
from google.cloud import discoveryengine_v1 as discoveryengine
import base64
import vertexai
from vertexai.generative_models import GenerativeModel, Part

# Use Streamlit secrets if available, otherwise assume GOOGLE_APPLICATION_CREDENTIALS is set
if "gcp_service_account" in st.secrets:
    with open("sa.json", "w") as f:
        json.dump(dict(st.secrets["gcp_service_account"]), f)
    os.environ["GOOGLE_APPLICATION_CREDENTIALS"] = "sa.json"

project_id = "gemini-chatbot-418317"
datastore_id = "fa-reports_1779052870269"
location = "global"  # Datastore search uses global
vertex_location = "us-central1"

vertexai.init(project=project_id, location=vertex_location)

st.title("Failure Report Analysis")

query_text = st.text_input("Enter description or observation (optional)")

# Streamlit file uploader supports drag and drop by default and file browser selection.
uploaded_files = st.file_uploader("Upload Image(s)", type=["png", "jpg", "jpeg"], accept_multiple_files=True)

if "search_results" not in st.session_state:
    st.session_state.search_results = []
    st.session_state.has_searched = False

if st.button("Search"):
    if not query_text and not uploaded_files:
        st.warning("Please enter some text or upload an image.")
    else:
        st.info("Searching...")
        try:
            client_options = {} if location == "global" else {"api_endpoint": f"{location}-discoveryengine.googleapis.com"}
            client = discoveryengine.SearchServiceClient(client_options=client_options)
            serving_config = client.serving_config_path(
                project=project_id,
                location=location,
                data_store=datastore_id,
                serving_config="default_config",
            )

            all_results = {}

            # Since Discovery Engine API SearchRequest only accepts a single `image_query`,
            # we will iterate through all uploaded images and aggregate the results.
            # If no images are uploaded, we just run one search with the text query.
            if not uploaded_files:
                request_kwargs = {
                    "serving_config": serving_config,
                    "query": query_text if query_text else ""
                }
                content_search_spec = discoveryengine.SearchRequest.ContentSearchSpec(
                    extractive_content_spec=discoveryengine.SearchRequest.ContentSearchSpec.ExtractiveContentSpec(
                        max_extractive_segment_count=1
                    )
                )
                request_kwargs["content_search_spec"] = content_search_spec
                request = discoveryengine.SearchRequest(**request_kwargs)
                response = client.search(request)
                for result in response:
                    doc = result.document
                    doc_dict = type(doc).to_dict(doc)
                    all_results[doc_dict.get("name")] = doc_dict
            else:
                for uploaded_file in uploaded_files:
                    image_bytes = uploaded_file.read()
                    b64_img = base64.b64encode(image_bytes).decode('utf-8')
                    image_query = discoveryengine.SearchRequest.ImageQuery(image_bytes=b64_img)

                    request_kwargs = {
                        "serving_config": serving_config,
                        "query": query_text if query_text else "",
                        "image_query": image_query
                    }
                    content_search_spec = discoveryengine.SearchRequest.ContentSearchSpec(
                        extractive_content_spec=discoveryengine.SearchRequest.ContentSearchSpec.ExtractiveContentSpec(
                            max_extractive_segment_count=1
                        )
                    )
                    request_kwargs["content_search_spec"] = content_search_spec
                    request = discoveryengine.SearchRequest(**request_kwargs)
                    response = client.search(request)

                    for result in response:
                        doc = result.document
                        doc_dict = type(doc).to_dict(doc)
                        if doc_dict.get("name") not in all_results:
                            all_results[doc_dict.get("name")] = doc_dict

            st.session_state.search_results = list(all_results.values())
            st.session_state.has_searched = True

        except Exception as e:
            st.error(f"Error during search: {e}")

if st.session_state.has_searched:
    st.subheader("Search Results")
    if not st.session_state.search_results:
        st.write("No results found.")
    else:
        # Create a dropdown to select a report for AI overview
        options = []
        for idx, doc_dict in enumerate(st.session_state.search_results):
            struct_data = doc_dict.get("struct_data", {})
            derived_struct_data = doc_dict.get("derived_struct_data", {})
            title = struct_data.get("title") or derived_struct_data.get("title") or doc_dict.get("name", f"Report {idx+1}")
            options.append(f"{idx + 1}: {title}")

        selected_option = st.selectbox("Select a report to view / generate AI Overview", options)
        selected_idx = options.index(selected_option)
        selected_doc = st.session_state.search_results[selected_idx]

        struct_data = selected_doc.get("struct_data", {})
        derived_struct_data = selected_doc.get("derived_struct_data", {})
        link = struct_data.get("link") or derived_struct_data.get("link") or "No Link"

        st.write(f"**Selected Report:** {options[selected_idx]}")
        if link != "No Link":
            st.markdown(f"**Link:** [{link}]({link})")
        else:
            st.write(f"**Link:** {link}")

        enable_ai_overview = st.checkbox("Enable AI Overview for selected report")

        if enable_ai_overview:
            st.info("Generating AI Overview...")
            try:
                model = GenerativeModel("gemini-2.5-flash-lite")

                # Extract text content from the selected document if possible
                doc_content = ""
                if "derived_struct_data" in selected_doc and "extractive_segments" in selected_doc["derived_struct_data"]:
                    segments = selected_doc["derived_struct_data"]["extractive_segments"]
                    doc_content = " ".join([seg.get("content", "") for seg in segments])

                prompt = "Provide a concise overview highlighting the key findings for this failure report."
                if query_text:
                    prompt += f"\n\nObservation from user: {query_text}"

                if doc_content:
                    prompt += f"\n\nDocument content snippets:\n{doc_content}"
                else:
                    # In case we can't get text snippets, we summarize based on the available metadata
                    prompt += f"\n\nDocument Title: {options[selected_idx]}\nDocument Link: {link}\n(Note: Full document text might not be available in snippets, please summarize based on the observation and title if possible.)"

                response = model.generate_content(prompt)
                st.write(response.text)
            except Exception as e:
                st.error(f"Error generating AI overview: {e}")
