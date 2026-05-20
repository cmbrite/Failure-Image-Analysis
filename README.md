# Failure-Image-Analysis

AI failure image analysis project

This project provides a Streamlit application designed to search through a Google Cloud Discovery Engine Datastore containing failure reports. It supports multimodal search queries (images and text) and can optionally generate concise AI overviews of specific reports using Vertex AI's Gemini 2.5 Flash Lite model.

## Features

- **Multimodal Search:** Upload one or more images (png, jpg, jpeg) and optionally enter a text description/observation to find relevant failure reports.
- **AI Overviews:** Select a retrieved report and generate an AI-powered summary based on the report's content and your observation.

## Prerequisites

Before running the application, ensure you have the following configured:
- Python 3.8+
- A Google Cloud Project (`gemini-chatbot-418317`) with Discovery Engine and Vertex AI enabled.
- A service account JSON key with permissions to use Vertex AI and Discovery Engine.

## Installation

1. Clone this repository.
2. Install the required Python dependencies:

```bash
pip install -r requirements.txt
```

## Configuration

The application authenticates using a Google Cloud Service Account. You can provide this in one of two ways:

### Option 1: Streamlit Secrets (Recommended for local development)

1. Create a `.streamlit` folder in the root directory.
2. Inside `.streamlit`, create a file named `secrets.toml`.
3. Add your service account details under the `[gcp_service_account]` section. Example:

```toml
[gcp_service_account]
type = "service_account"
project_id = "your-project-id"
private_key_id = "your-private-key-id"
private_key = "-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----\n"
client_email = "your-service-account-email"
client_id = "your-client-id"
auth_uri = "https://accounts.google.com/o/oauth2/auth"
token_uri = "https://oauth2.googleapis.com/token"
auth_provider_x509_cert_url = "https://www.googleapis.com/oauth2/v1/certs"
client_x509_cert_url = "your-cert-url"
universe_domain = "googleapis.com"
```

The application will automatically read these secrets, create a temporary `sa.json` file, and set the `GOOGLE_APPLICATION_CREDENTIALS` environment variable.

### Option 2: Environment Variable

Alternatively, if you are deploying to an environment that natively supports it (or if you already have the file locally), you can directly set the `GOOGLE_APPLICATION_CREDENTIALS` environment variable to point to your service account JSON file before running the app.

```bash
export GOOGLE_APPLICATION_CREDENTIALS=/path/to/your/sa.json
```

## Usage

Start the Streamlit application:

```bash
streamlit run app.py
```

### How to use the app:

1. **Enter Observation (Optional):** In the "Enter description or observation (optional)" text box, type any context or keywords related to the failure.
2. **Upload Image(s):** Use the file uploader to provide images of the failure. The application will use the first uploaded image as an `ImageQuery` for the Discovery Engine.
3. **Search:** Click the "Search" button.
4. **View Results:** The application will display the most relevant failure reports from the datastore.
5. **AI Overview:**
   - Once the search results are populated, a dropdown will appear.
   - Select the specific report you are interested in from the dropdown.
   - Check the "Enable AI Overview for selected report" box.
   - The application will extract text snippets from the selected document and use Gemini to provide a concise summary incorporating your initial observation.

## Architecture & Logic

- **Framework:** [Streamlit](https://streamlit.io/) provides the interactive web interface.
- **Search Engine:** `google.cloud.discoveryengine_v1.SearchServiceClient` queries the unstructured datastore (`fa-reports_1779052870269`).
  - Search requests combine `query` (text) and `image_query` (base64 encoded image).
  - An `ExtractiveContentSpec` is used to retrieve text snippets (`extractive_segments`) from the matched documents (like PDFs) to provide context for the AI model.
- **Generative AI:** `vertexai.generative_models.GenerativeModel` ("gemini-2.5-flash-lite") summarizes the extracted snippets of the *selected* document, specifically guided by the user's input observation.
