<?php
declare(strict_types=1);

function cms_stream_speaker_audio($requestedId): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        header('Allow: GET');
        http_response_code(405);
        exit;
    }

    if (!is_string($requestedId)) {
        http_response_code(404);
        exit;
    }
    $speakerId = cms_text($requestedId, 40);
    if (!preg_match('/^[A-Za-z0-9_-]{1,40}$/', $speakerId)) {
        http_response_code(404);
        exit;
    }

    $speaker = null;
    foreach ((cms_content()['speakers']['items'] ?? []) as $item) {
        if (is_array($item) && hash_equals((string)($item['id'] ?? ''), $speakerId)) {
            $speaker = $item;
            break;
        }
    }
    if ($speaker === null || ($source = cms_speaker_download_url($speaker)) === '') {
        http_response_code(404);
        exit;
    }
    if (!function_exists('curl_init')) {
        http_response_code(503);
        exit;
    }

    $range = trim((string)($_SERVER['HTTP_RANGE'] ?? ''));
    if ($range !== '' && !preg_match('/^bytes=(?:[0-9]+-[0-9]*|-[0-9]+)$/', $range)) {
        http_response_code(416);
        header('Accept-Ranges: bytes');
        exit;
    }

    while (ob_get_level() > 0) ob_end_clean();
    header('Accept-Ranges: bytes');
    header('Cache-Control: public, max-age=3600');
    header('X-Content-Type-Options: nosniff');
    header('X-Accel-Buffering: no');

    $upstreamStatus = 0;
    $upstreamHeaders = [];
    $headersSent = false;
    $curl = curl_init($source);
    if ($curl === false) {
        http_response_code(502);
        exit;
    }

    $options = [
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_LOW_SPEED_LIMIT => 1024,
        CURLOPT_LOW_SPEED_TIME => 45,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$upstreamStatus, &$upstreamHeaders): int {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/i', $line, $match)) {
                $upstreamStatus = (int)$match[1];
                $upstreamHeaders = [];
            } elseif (($separator = strpos($line, ':')) !== false) {
                $name = strtolower(trim(substr($line, 0, $separator)));
                $upstreamHeaders[$name] = trim(substr($line, $separator + 1));
            }
            return strlen($line);
        },
        CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$upstreamStatus, &$upstreamHeaders, &$headersSent): int {
            $length = strlen($chunk);
            if ($upstreamStatus < 200 || $upstreamStatus >= 300) return $length;

            $contentType = (string)($upstreamHeaders['content-type'] ?? '');
            if (!preg_match('~^(?:audio/[a-z0-9.+-]+|application/octet-stream)(?:\s*;|$)~i', $contentType)) {
                $upstreamStatus = 502;
                return $length;
            }

            if (!$headersSent) {
                http_response_code($upstreamStatus);
                header('Content-Type: ' . $contentType);
                header('Content-Disposition: inline');
                foreach (['content-length' => 'Content-Length', 'content-range' => 'Content-Range'] as $sourceHeader => $targetHeader) {
                    $value = (string)($upstreamHeaders[$sourceHeader] ?? '');
                    if ($value !== '' && preg_match('/^[A-Za-z0-9 *\/-]+$/', $value)) header($targetHeader . ': ' . $value);
                }
                $headersSent = true;
            }

            echo $chunk;
            flush();
            return $length;
        },
    ];
    if ($range !== '') $options[CURLOPT_HTTPHEADER] = ['Range: ' . $range];
    curl_setopt_array($curl, $options);
    $result = curl_exec($curl);
    $curlError = curl_errno($curl);
    curl_close($curl);

    if (!$headersSent) {
        if ($upstreamStatus === 416 && isset($upstreamHeaders['content-range']) && preg_match('/^bytes \*\/[0-9]+$/', $upstreamHeaders['content-range'])) {
            http_response_code(416);
            header('Content-Range: ' . $upstreamHeaders['content-range']);
            exit;
        }
        http_response_code($curlError === 0 && $upstreamStatus === 404 ? 404 : 502);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Не удалось получить аудиозапись.';
    } elseif ($result === false || $curlError !== 0) {
        exit;
    }
}
