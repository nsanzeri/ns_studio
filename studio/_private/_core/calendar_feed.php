<?php
declare(strict_types=1);

function calendar_feed_is_complete(string $raw): bool
{
    return (bool)preg_match('/\ABEGIN:VCALENDAR\s*$/mi', ltrim($raw, "\xEF\xBB\xBF\r\n \t")) &&
        (bool)preg_match('/^END:VCALENDAR\s*\z/mi', $raw);
}

/** Only complete calendar responses are safe to use for availability. */
function calendar_feed_download(string $url): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_ENCODING => '',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    try {
        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($raw === false) {
            if (curl_errno($ch) === CURLE_OPERATION_TIMEDOUT) {
                throw new RuntimeException('The calendar provider took too long to send the feed. Please try again shortly.');
            }
            // Do not expose the secret feed URL or provider response to the browser.
            throw new RuntimeException('Could not download the calendar feed. Please try again shortly.');
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('The calendar provider returned HTTP ' . $status . '. Please check the calendar link or try again shortly.');
        }
        if (!calendar_feed_is_complete($raw)) {
            throw new RuntimeException('The provider did not return a complete calendar feed. Please check the calendar link or try again shortly.');
        }
        return $raw;
    } finally {
        curl_close($ch);
    }
}

/** Cache the full feed across date ranges; never fall back to expired data. */
function calendar_feed_load(string $url, string $cacheFile, ?callable $download = null): string
{
    $download = $download ?? 'calendar_feed_download';
    $handle = null;
    try {
        $directory = dirname($cacheFile);
        if (!is_dir($directory)) {
            try {
                mkdir($directory, 0700, true);
            } catch (Throwable $e) {
                if (!is_dir($directory)) {
                    throw $e;
                }
            }
        }
        $handle = fopen($cacheFile, 'c+b');
        if ($handle === false || !flock($handle, LOCK_EX)) {
            throw new RuntimeException('Calendar cache unavailable');
        }
        // The lock also lets simultaneous month/availability requests share one download.
        $stat = fstat($handle);
        if ($stat && $stat['size'] > 0 && time() - $stat['mtime'] < 60) {
            $cached = stream_get_contents($handle);
            if (is_string($cached) && calendar_feed_is_complete($cached)) {
                fclose($handle);
                return $cached;
            }
        }
    } catch (Throwable $e) {
        // Hosting may disallow local cache writes. Downloads must still work.
        if (is_resource($handle)) {
            fclose($handle);
        }
        $handle = null;
    }

    try {
        $raw = $download($url);
        if (is_resource($handle)) {
            try {
                rewind($handle);
                ftruncate($handle, 0);
                if (fwrite($handle, $raw) !== strlen($raw) || !fflush($handle)) {
                    throw new RuntimeException('Incomplete calendar cache write');
                }
            } catch (Throwable $e) {
                // Ensure a partial write cannot become a successful cached response.
                try {
                    ftruncate($handle, 0);
                } catch (Throwable $ignored) {
                    // The complete download can still be used for this request.
                }
            }
        }
        return $raw;
    } finally {
        if (is_resource($handle)) {
            fclose($handle);
        }
    }
}
