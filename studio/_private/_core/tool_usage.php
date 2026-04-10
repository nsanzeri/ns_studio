<?php

declare(strict_types=1);

if (!function_exists('tool_usage_table_exists')) {
    function tool_usage_table_exists(PDO $pdo): bool
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }

        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'tool_usage_log'");
            $exists = (bool) $stmt->fetchColumn();
        } catch (Throwable $e) {
            $exists = false;
        }

        return $exists;
    }
}

if (!function_exists('tool_usage_client_ip')) {
    function tool_usage_client_ip(): ?string
    {
        $keys = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'REMOTE_ADDR',
        ];

        foreach ($keys as $key) {
            $raw = trim((string)($_SERVER[$key] ?? ''));
            if ($raw === '') {
                continue;
            }

            if ($key === 'HTTP_X_FORWARDED_FOR') {
                $parts = array_map('trim', explode(',', $raw));
                foreach ($parts as $part) {
                    if (filter_var($part, FILTER_VALIDATE_IP)) {
                        return $part;
                    }
                }
                continue;
            }

            if (filter_var($raw, FILTER_VALIDATE_IP)) {
                return $raw;
            }
        }

        return null;
    }
}

if (!function_exists('tool_usage_inet_pton')) {
    function tool_usage_inet_pton(?string $ip): ?string
    {
        if (!$ip) {
            return null;
        }

        $packed = @inet_pton($ip);
        return $packed === false ? null : $packed;
    }
}

if (!function_exists('tool_usage_current_user_context')) {
    function tool_usage_current_user_context(PDO $pdo): array
    {
        $userId = class_exists('Auth') ? Auth::userId() : null;
        $email = null;

        if ($userId && class_exists('Auth')) {
            try {
                $user = Auth::currentUser($pdo);
                $email = $user['email'] ?? null;
            } catch (Throwable $e) {
                $email = null;
            }
        }

        return [
            'user_id' => $userId ? (int)$userId : null,
            'user_email' => $email ? strtolower(trim((string)$email)) : null,
        ];
    }
}

if (!function_exists('log_tool_usage')) {
    /**
     * Lightweight, fail-safe usage logger.
     *
     * Example:
     * log_tool_usage($pdo, [
     *   'feature_key' => 'availability_check',
     *   'action_key' => 'run',
     *   'status' => 'success',
     *   'calendar_count' => 2,
     *   'result_count' => 14,
     *   'input' => ['from' => '2026-04-01', 'to' => '2026-04-30'],
     * ]);
     */
    function log_tool_usage(PDO $pdo, array $data): bool
    {
        if (!tool_usage_table_exists($pdo)) {
            return false;
        }

        $featureKey = trim((string)($data['feature_key'] ?? ''));
        if ($featureKey === '') {
            return false;
        }

        $actionKey = isset($data['action_key']) ? trim((string)$data['action_key']) : null;
        $status = strtolower(trim((string)($data['status'] ?? 'success')));
        if (!in_array($status, ['success', 'blocked', 'error'], true)) {
            $status = 'success';
        }

        $calendarCount = isset($data['calendar_count']) && $data['calendar_count'] !== ''
            ? max(0, (int)$data['calendar_count'])
            : null;
        $resultCount = isset($data['result_count']) && $data['result_count'] !== ''
            ? max(0, (int)$data['result_count'])
            : null;

        $inputJson = null;
        if (array_key_exists('input_json', $data) && $data['input_json'] !== null) {
            $inputJson = (string)$data['input_json'];
        } elseif (array_key_exists('input', $data)) {
            $json = json_encode($data['input'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $inputJson = $json === false ? null : $json;
        }

        $note = isset($data['note']) ? trim((string)$data['note']) : null;
        if ($note === '') {
            $note = null;
        }
        if ($note !== null && strlen($note) > 255) {
            $note = substr($note, 0, 255);
        }

        $context = tool_usage_current_user_context($pdo);
        $userId = array_key_exists('user_id', $data) && $data['user_id'] !== null
            ? (int)$data['user_id']
            : $context['user_id'];
        $userEmail = array_key_exists('user_email', $data) && $data['user_email'] !== null
            ? strtolower(trim((string)$data['user_email']))
            : $context['user_email'];
        if ($userEmail === '') {
            $userEmail = null;
        }

        $ipBinary = array_key_exists('ip', $data)
            ? tool_usage_inet_pton((string)$data['ip'])
            : tool_usage_inet_pton(tool_usage_client_ip());

        $userAgent = isset($data['user_agent']) ? (string)$data['user_agent'] : (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        if ($userAgent === '') {
            $userAgent = null;
        }
        if ($userAgent !== null && strlen($userAgent) > 255) {
            $userAgent = substr($userAgent, 0, 255);
        }

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO tool_usage_log (
                    user_id,
                    user_email,
                    feature_key,
                    action_key,
                    calendar_count,
                    input_json,
                    result_count,
                    status,
                    note,
                    ip,
                    user_agent,
                    created_at
                ) VALUES (
                    :user_id,
                    :user_email,
                    :feature_key,
                    :action_key,
                    :calendar_count,
                    :input_json,
                    :result_count,
                    :status,
                    :note,
                    :ip,
                    :user_agent,
                    NOW()
                )'
            );

            $stmt->bindValue(':user_id', $userId, $userId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':user_email', $userEmail, $userEmail === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':feature_key', $featureKey, PDO::PARAM_STR);
            $stmt->bindValue(':action_key', $actionKey, $actionKey === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':calendar_count', $calendarCount, $calendarCount === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':input_json', $inputJson, $inputJson === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':result_count', $resultCount, $resultCount === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':status', $status, PDO::PARAM_STR);
            $stmt->bindValue(':note', $note, $note === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':ip', $ipBinary, $ipBinary === null ? PDO::PARAM_NULL : PDO::PARAM_LOB);
            $stmt->bindValue(':user_agent', $userAgent, $userAgent === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

            return $stmt->execute();
        } catch (Throwable $e) {
            error_log('[tool_usage] ' . $e->getMessage());
            return false;
        }
    }
}
