<?php
require 'vendor/autoload.php';

use Google\Cloud\AIPlatform\V1\Client\PredictionServiceClient;
use Google\Cloud\AIPlatform\V1\GenerateContentRequest;
use Google\Cloud\AIPlatform\V1\Content;
use Google\Cloud\AIPlatform\V1\Part;

header('Content-Type: application/json');

// Check for credentials
if (!getenv('GOOGLE_APPLICATION_CREDENTIALS')) {
    if (file_exists('sa.json')) {
        putenv('GOOGLE_APPLICATION_CREDENTIALS=' . realpath('sa.json'));
    }
}

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

if (!$input || !isset($input['document'])) {
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

$queryText = $input['query_text'] ?? '';
$document = $input['document'];

$projectId = "gemini-chatbot-418317";
$location = "us-central1";
$model = "gemini-2.5-flash-lite";

$docContent = "";
if (isset($document['content_snippets']) && is_array($document['content_snippets'])) {
    $docContent = implode(" ", $document['content_snippets']);
}

$promptText = "Provide a concise overview highlighting the key findings for this failure report.";
if (!empty($queryText)) {
    $promptText .= "\n\nObservation from user: " . $queryText;
}

$title = $document['title'] ?? ($document['name'] ?? 'Unknown Document');
$link = $document['link'] ?? 'No Link';

if (!empty($docContent)) {
    $promptText .= "\n\nDocument content snippets:\n" . $docContent;
} else {
    $promptText .= "\n\nDocument Title: " . $title . "\nDocument Link: " . $link . "\n(Note: Full document text might not be available in snippets, please summarize based on the observation and title if possible.)";
}

try {
    $client = new PredictionServiceClient([
        'apiEndpoint' => "{$location}-aiplatform.googleapis.com"
    ]);

    $request = new GenerateContentRequest();
    $request->setModel("projects/{$projectId}/locations/{$location}/publishers/google/models/{$model}");

    $content = new Content();
    $content->setRole('user');
    $part = new Part();
    $part->setText($promptText);
    $content->setParts([$part]);

    $request->setContents([$content]);

    $response = $client->generateContent($request);

    $overviewText = "";
    foreach ($response->getCandidates() as $candidate) {
        $parts = $candidate->getContent()->getParts();
        foreach ($parts as $part) {
            $overviewText .= $part->getText();
        }
    }

    echo json_encode(['overview' => $overviewText]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
