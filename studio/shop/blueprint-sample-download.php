<?php
declare(strict_types=1);

$filePath = dirname(__DIR__) . '/_private/private_downloads/backing-track-blueprint-sample.pdf';
$downloadName = 'backing-track-blueprint-sample.pdf';

if (!is_file($filePath) || !is_readable($filePath)) {
	http_response_code(404);
	header('Content-Type: text/plain; charset=utf-8');
	echo "Sample file not found.";
	exit;
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . (string)filesize($filePath));
header('X-Content-Type-Options: nosniff');
readfile($filePath);
exit;
