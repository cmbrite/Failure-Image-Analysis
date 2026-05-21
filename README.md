# Failure-Image-Analysis

AI failure image analysis project

This project provides a PHP web application designed to search through a Google Cloud Discovery Engine Datastore containing failure reports. It supports multimodal search queries (images and text) and can optionally generate concise AI overviews of specific reports using Vertex AI's Gemini 2.5 Flash Lite model.

## Features

- **Multimodal Search:** Upload one or more images (png, jpg, jpeg) and optionally enter a text description/observation to find relevant failure reports.
- **AI Overviews:** Select a retrieved report and generate an AI-powered summary based on the report's content and your observation.

## Prerequisites

Before running the application, ensure you have the following configured:
- PHP 8.1+
- Composer
- A Google Cloud Project (`gemini-chatbot-418317`) with Discovery Engine and Vertex AI enabled.
- A service account JSON key with permissions to use Vertex AI and Discovery Engine.

## Installation

1. Clone this repository.
2. Install the required PHP dependencies using Composer:

```bash
composer install
```

## Configuration

The application authenticates using a Google Cloud Service Account.

### Environment Variable or File

You must have a service account JSON file (e.g., `sa.json`). The application looks for it in two ways:

1. By setting the `GOOGLE_APPLICATION_CREDENTIALS` environment variable:
   ```bash
   export GOOGLE_APPLICATION_CREDENTIALS=/path/to/your/sa.json
   ```

2. By placing a file named `sa.json` in the root directory of the project. The code will automatically read it and set the environment variable.

## Usage

Start the local PHP development server:

```bash
php -S localhost:8000
```

Open your browser and navigate to `http://localhost:8000/index.php`.

### How to use the app:

1. **Enter Observation (Optional):** In the "Enter description or observation (optional)" text box, type any context or keywords related to the failure.
2. **Upload Image(s):** Use the file uploader to provide images of the failure.
3. **Search:** Click the "Search" button.
4. **View Results:** The application will display the most relevant failure reports from the datastore.
5. **AI Overview:**
   - Once the search results are populated, a dropdown will appear.
   - Select the specific report you are interested in from the dropdown.
   - Click the "Generate AI Overview" button.
   - The application will extract text snippets from the selected document and use Gemini to provide a concise summary incorporating your initial observation.

## Architecture & Logic

- **Frontend:** HTML, CSS, and Vanilla JavaScript (`index.php`) handle the user interface and fetch requests to the backend API.
- **Backend Search (`search.php`):** Integrates with `Google\Cloud\DiscoveryEngine\V1\Client\SearchServiceClient` to query the unstructured datastore (`fa-reports_1779052870269`). Search requests combine text queries and image queries. Extracts text snippets to provide context.
- **Backend Overview (`overview.php`):** Integrates with `Google\Cloud\AIPlatform\V1\Client\PredictionServiceClient` ("gemini-2.5-flash-lite") to summarize the extracted snippets of the selected document, specifically guided by the user's input observation.
