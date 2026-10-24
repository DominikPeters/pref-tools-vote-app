<?php
/** Resolve the classic embed loader without starting an application session. */
require_once __DIR__ . '/src/Services/AssetManifest.php';

$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/embed-script.php'), '/');
header('Cache-Control: no-cache');
try {
    $url = (new \App\Services\AssetManifest(__DIR__))->url('assets/js/embed/vote-poll.js', $basePath);
    header('Location: ' . $url, true, 302);
} catch (\RuntimeException $error) {
    http_response_code(503);
    header('Content-Type: application/javascript; charset=utf-8');
    echo 'console.error("The voting embed is temporarily unavailable.");';
}
