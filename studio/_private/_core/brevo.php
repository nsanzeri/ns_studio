<?php

declare(strict_types=1);

function brevo_sync_contact_to_list(string $email, ?string $firstName, int $listId): array
{
	$apiKey = trim((string)env('BREVO_API_KEY', ''));
	if ($apiKey === '' || $listId <= 0) {
		return [
			'ok' => false,
			'status' => 0,
			'body' => '',
			'error' => 'Brevo API key or list ID is not configured.',
		];
	}

	$payload = [
		'email' => $email,
		'listIds' => [$listId],
		'updateEnabled' => true,
	];

	$firstName = trim((string)$firstName);
	if ($firstName !== '') {
		$payload['attributes'] = [
			'FIRSTNAME' => $firstName,
		];
	}

	$json = json_encode($payload, JSON_UNESCAPED_SLASHES);
	if ($json === false) {
		return [
			'ok' => false,
			'status' => 0,
			'body' => '',
			'error' => 'Unable to encode Brevo contact payload.',
		];
	}

	$url = 'https://api.brevo.com/v3/contacts';
	$headers = [
		'Accept: application/json',
		'Content-Type: application/json',
		'api-key: ' . $apiKey,
	];

	if (function_exists('curl_init')) {
		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_POST => true,
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_POSTFIELDS => $json,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CONNECTTIMEOUT => 8,
			CURLOPT_TIMEOUT => 15,
		]);

		$body = (string)curl_exec($ch);
		$error = curl_error($ch);
		$status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		curl_close($ch);

		return [
			'ok' => in_array($status, [200, 201, 204], true),
			'status' => $status,
			'body' => $body,
			'error' => $error,
		];
	}

	$context = stream_context_create([
		'http' => [
			'method' => 'POST',
			'header' => implode("\r\n", $headers),
			'content' => $json,
			'ignore_errors' => true,
			'timeout' => 15,
		],
	]);

	$body = (string)@file_get_contents($url, false, $context);
	$status = 0;

	foreach (($http_response_header ?? []) as $header) {
		if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches)) {
			$status = (int)$matches[1];
			break;
		}
	}

	return [
		'ok' => in_array($status, [200, 201, 204], true),
		'status' => $status,
		'body' => $body,
		'error' => $body === '' ? 'No response from Brevo.' : '',
	];
}
