<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Failure Report Analysis</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        h1, h2, h3 {
            color: #333;
        }
        .container {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        input[type="file"] {
            display: block;
            margin-top: 5px;
        }
        button {
            background-color: #007bff;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #0056b3;
        }
        button:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }
        #results-container {
            margin-top: 20px;
            display: none;
        }
        .result-item {
            border-bottom: 1px solid #eee;
            padding: 10px 0;
        }
        #ai-overview-container {
            margin-top: 20px;
            padding: 15px;
            background-color: #e9ecef;
            border-radius: 4px;
            display: none;
        }
        select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .loading {
            font-style: italic;
            color: #666;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Failure Report Analysis</h1>

    <div class="form-group">
        <label for="query_text">Enter description or observation (optional)</label>
        <input type="text" id="query_text" name="query_text">
    </div>

    <div class="form-group">
        <label for="images">Upload Image(s)</label>
        <input type="file" id="images" name="images[]" accept="image/png, image/jpeg, image/jpg" multiple>
    </div>

    <button id="search-btn">Search</button>
    <div id="search-loading" class="loading" style="display:none; margin-top: 10px;">Searching...</div>

    <div id="results-container">
        <h2>Search Results</h2>
        <div id="results-list"></div>

        <div id="ai-section" style="display:none; margin-top: 20px;">
            <h3>AI Overview</h3>
            <label for="report-select">Select a report to view / generate AI Overview</label>
            <select id="report-select"></select>

            <div id="selected-report-info" style="margin-bottom: 10px;"></div>

            <button id="overview-btn">Generate AI Overview</button>
            <div id="overview-loading" class="loading" style="display:none; margin-top: 10px;">Generating AI Overview...</div>

            <div id="ai-overview-content" style="margin-top: 10px; white-space: pre-wrap; font-family: monospace; background: #fff; padding: 10px; border: 1px solid #ccc;"></div>
        </div>
    </div>
</div>

<script>
    let currentResults = [];

    document.getElementById('search-btn').addEventListener('click', async () => {
        const queryText = document.getElementById('query_text').value;
        const images = document.getElementById('images').files;

        if (!queryText && images.length === 0) {
            alert("Please enter some text or upload an image.");
            return;
        }

        const formData = new FormData();
        formData.append('query_text', queryText);
        for (let i = 0; i < images.length; i++) {
            formData.append('images[]', images[i]);
        }

        document.getElementById('search-loading').style.display = 'block';
        document.getElementById('results-container').style.display = 'none';
        document.getElementById('search-btn').disabled = true;

        try {
            const response = await fetch('search.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.error) {
                alert("Error during search: " + data.error);
            } else {
                currentResults = data.results || [];
                displayResults();
            }
        } catch (error) {
            alert("An error occurred while searching.");
            console.error(error);
        } finally {
            document.getElementById('search-loading').style.display = 'none';
            document.getElementById('search-btn').disabled = false;
        }
    });

    function displayResults() {
        const container = document.getElementById('results-container');
        const list = document.getElementById('results-list');
        const aiSection = document.getElementById('ai-section');
        const select = document.getElementById('report-select');

        list.innerHTML = '';
        select.innerHTML = '';
        document.getElementById('ai-overview-content').innerHTML = '';
        document.getElementById('selected-report-info').innerHTML = '';

        container.style.display = 'block';

        if (currentResults.length === 0) {
            list.innerHTML = '<p>No results found.</p>';
            aiSection.style.display = 'none';
            return;
        }

        aiSection.style.display = 'block';

        currentResults.forEach((doc, idx) => {
            const title = doc.title || doc.name || `Report ${idx + 1}`;
            select.options.add(new Option(`${idx + 1}: ${title}`, idx));
        });

        updateSelectedInfo();
    }

    document.getElementById('report-select').addEventListener('change', updateSelectedInfo);

    function updateSelectedInfo() {
        const idx = document.getElementById('report-select').value;
        const doc = currentResults[idx];
        const title = doc.title || doc.name || `Report ${idx + 1}`;
        const link = doc.link || "No Link";

        let html = `<strong>Selected Report:</strong> ${title}<br>`;
        if (link !== "No Link") {
            html += `<strong>Link:</strong> <a href="${link}" target="_blank">${link}</a>`;
        } else {
            html += `<strong>Link:</strong> ${link}`;
        }

        document.getElementById('selected-report-info').innerHTML = html;
        document.getElementById('ai-overview-content').innerHTML = ''; // Clear previous overview
    }

    document.getElementById('overview-btn').addEventListener('click', async () => {
        const idx = document.getElementById('report-select').value;
        const doc = currentResults[idx];
        const queryText = document.getElementById('query_text').value;

        document.getElementById('overview-loading').style.display = 'block';
        document.getElementById('overview-btn').disabled = true;
        document.getElementById('ai-overview-content').innerHTML = '';

        try {
            const response = await fetch('overview.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    query_text: queryText,
                    document: doc
                })
            });
            const data = await response.json();

            if (data.error) {
                document.getElementById('ai-overview-content').innerHTML = "Error: " + data.error;
            } else {
                document.getElementById('ai-overview-content').innerHTML = data.overview;
            }
        } catch (error) {
            document.getElementById('ai-overview-content').innerHTML = "An error occurred while generating AI overview.";
            console.error(error);
        } finally {
            document.getElementById('overview-loading').style.display = 'none';
            document.getElementById('overview-btn').disabled = false;
        }
    });
</script>

</body>
</html>
