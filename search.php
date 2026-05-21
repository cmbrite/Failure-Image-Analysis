<?php
require 'vendor/autoload.php';

use Google\Cloud\DiscoveryEngine\V1\Client\SearchServiceClient;
use Google\Cloud\DiscoveryEngine\V1\SearchRequest;
use Google\Cloud\DiscoveryEngine\V1\SearchRequest\ImageQuery;
use Google\Cloud\DiscoveryEngine\V1\SearchRequest\ContentSearchSpec;
use Google\Cloud\DiscoveryEngine\V1\SearchRequest\ContentSearchSpec\ExtractiveContentSpec;

header('Content-Type: application/json');

// Check for credentials
if (!getenv('GOOGLE_APPLICATION_CREDENTIALS')) {
    if (file_exists('sa.json')) {
        putenv('GOOGLE_APPLICATION_CREDENTIALS=' . realpath('sa.json'));
    }
}

$projectId = "gemini-chatbot-418317";
$datastoreId = "fa-reports_1779052870269";
$location = "global";

$queryText = isset($_POST['query_text']) ? $_POST['query_text'] : "";
$hasImages = isset($_FILES['images']) && count($_FILES['images']['tmp_name']) > 0 && $_FILES['images']['tmp_name'][0] != "";

if (empty($queryText) && !$hasImages) {
    echo json_encode(['error' => 'Please enter some text or upload an image.']);
    exit;
}

try {
    $client = new SearchServiceClient();

    $servingConfig = $client->servingConfigName(
        $projectId,
        $location,
        $datastoreId,
        "default_config"
    );

    $allResults = [];

    $contentSearchSpec = new ContentSearchSpec([
        'extractive_content_spec' => new ExtractiveContentSpec([
            'max_extractive_segment_count' => 1
        ])
    ]);

    if (!$hasImages) {
        $request = new SearchRequest([
            'serving_config' => $servingConfig,
            'query' => $queryText,
            'content_search_spec' => $contentSearchSpec
        ]);

        $response = $client->search($request);

        foreach ($response as $result) {
            $doc = $result->getDocument();
            $docData = json_decode($doc->serializeToJsonString(), true);
            $allResults[$docData['name']] = processDocumentData($docData);
        }
    } else {
        $files = $_FILES['images']['tmp_name'];
        foreach ($files as $tmpFile) {
            if (empty($tmpFile)) continue;

            $imageBytes = file_get_contents($tmpFile);
            $b64Img = base64_encode($imageBytes);

            $imageQuery = new ImageQuery([
                'image_bytes' => base64_decode($b64Img) // Discovery engine accepts binary string here, base64 wrapper not strictly needed if we pass raw bytes, but matching python behavior
            ]);
            // Wait, in Python we did base64.b64encode(image_bytes).decode('utf-8') and passed it.
            // Protobuf bytes field takes standard string in PHP. Let's just pass raw string.
            $imageQuery = new ImageQuery([
                'image_bytes' => $b64Img // The Python code specifically passed b64 encoded string: b64_img = base64.b64encode(image_bytes).decode('utf-8'); image_query = discoveryengine.SearchRequest.ImageQuery(image_bytes=b64_img)
            ]);

            $request = new SearchRequest([
                'serving_config' => $servingConfig,
                'query' => $queryText,
                'image_query' => $imageQuery,
                'content_search_spec' => $contentSearchSpec
            ]);

            $response = $client->search($request);

            foreach ($response as $result) {
                $doc = $result->getDocument();
                $docData = json_decode($doc->serializeToJsonString(), true);
                $name = $docData['name'];
                if (!isset($allResults[$name])) {
                    $allResults[$name] = processDocumentData($docData);
                }
            }
        }
    }

    echo json_encode(['results' => array_values($allResults)]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

function processDocumentData($docData) {
    $processed = [
        'name' => $docData['name'] ?? null,
    ];

    $structData = $docData['structData'] ?? [];
    $derivedStructData = $docData['derivedStructData'] ?? [];

    $processed['title'] = $structData['title'] ?? $derivedStructData['title'] ?? null;
    $processed['link'] = $structData['link'] ?? $derivedStructData['link'] ?? null;

    $segments = [];
    if (isset($derivedStructData['extractive_segments'])) {
        foreach ($derivedStructData['extractive_segments'] as $seg) {
            if (isset($seg['content'])) {
                $segments[] = $seg['content'];
            }
        }
    }
    $processed['content_snippets'] = $segments;

    return $processed;
}
