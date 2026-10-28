<?php
declare(strict_types=1);

// Run with: php studio/tests/calendar-feed.test.php
require_once __DIR__ . '/../_private/_core/calendar_feed.php';

$fixture = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nEND:VCALENDAR\r\n";
if (PHP_SAPI === 'cli-server') {
    switch ($_SERVER['REQUEST_URI']) {
        case '/gzip':
            header('Content-Encoding: gzip');
            echo gzencode($fixture);
            break;
        case '/redirect':
            header('Location: /gzip');
            break;
        case '/truncated':
            echo "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n";
            break;
        case '/invalid':
            echo '<html>Sign in</html>';
            break;
        default:
            http_response_code(503);
            echo $fixture;
    }
    return;
}

set_error_handler(static function (int $severity, string $message, string $file, int $line): void {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function expect_failure(callable $action, string $message): void
{
    try {
        $action();
    } catch (RuntimeException $e) {
        check(strpos($e->getMessage(), $message) !== false, 'Unexpected error: ' . $e->getMessage());
        return;
    }
    throw new RuntimeException('Expected failure: ' . $message);
}

$directory = sys_get_temp_dir() . '/rss-calendar-test-' . bin2hex(random_bytes(8));
$cacheFile = $directory . '/feed.ics';
$server = null;
try {
    $calls = 0;
    $download = static function (string $url) use (&$calls, $fixture): string {
        $calls++;
        return $fixture;
    };
    check(calendar_feed_load('test', $cacheFile, $download) === $fixture, 'Initial download');
    check(calendar_feed_load('test', $cacheFile, $download) === $fixture && $calls === 1, 'Reuse complete fresh feed');
    touch($cacheFile, time() - 61);
    clearstatcache();
    calendar_feed_load('test', $cacheFile, $download);
    check($calls === 2, 'Refresh expired feed');
    touch($cacheFile, time() - 61);
    clearstatcache();
    expect_failure(static function () use ($cacheFile): void {
        calendar_feed_load('test', $cacheFile, static function (): string {
            throw new RuntimeException('provider offline');
        });
    }, 'provider offline');
    file_put_contents($cacheFile, 'BEGIN:VCALENDAR');
    calendar_feed_load('test', $cacheFile, $download);
    check($calls === 3, 'Reject partial cache');
    check(calendar_feed_load('test', $cacheFile . '/unavailable', $download) === $fixture, 'Cache failure must allow download');

    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $server = proc_open([PHP_BINARY, '-S', $address, __FILE__], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    check(is_resource($server), 'Start fixture server');
    $ready = false;
    for ($attempt = 0; $attempt < 50; $attempt++) {
        try {
            $probe = stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
            if ($probe) {
                fclose($probe);
                $ready = true;
                break;
            }
        } catch (Throwable $e) {
            // Wait briefly for the local fixture server to start.
        }
        usleep(100000);
    }
    if (!$ready) {
        proc_terminate($server);
        throw new RuntimeException('Fixture server failed: ' . stream_get_contents($pipes[2]));
    }
    $base = 'http://' . $address;
    check(calendar_feed_download($base . '/gzip') === $fixture, 'Decode compressed feed');
    check(calendar_feed_download($base . '/redirect') === $fixture, 'Follow provider redirect');
    expect_failure(static function () use ($base): void { calendar_feed_download($base . '/invalid'); }, 'complete calendar');
    expect_failure(static function () use ($base): void { calendar_feed_download($base . '/truncated'); }, 'complete calendar');
    expect_failure(static function () use ($base): void { calendar_feed_download($base . '/error'); }, 'HTTP 503');
    echo "Passed: compression, redirects, HTTP errors, incomplete feeds, cache reuse/expiry, no stale fallback, and cache-write failure.\n";
} finally {
    if (is_resource($server)) {
        proc_terminate($server);
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }
        proc_close($server);
    }
    if (is_file($cacheFile)) {
        unlink($cacheFile);
    }
    if (is_dir($directory)) {
        rmdir($directory);
    }
}
