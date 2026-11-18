<?php

declare(strict_types=1);

// Runs against the built production image over HTTP, without bootstrapping the application in this process.
// `--ready` checks only that the home page answers (container health check); no argument runs the full smoke test.
$baseUrl = rtrim(getenv('HTTP_SMOKE_BASE_URL') ?: 'http://127.0.0.1:8001', '/');
$client = curl_init();
if ($client === false) {
    fwrite(STDERR, "Cannot initialize the HTTP smoke client.\n");
    exit(1);
}

curl_setopt_array($client, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 2,
    CURLOPT_TIMEOUT => 4,
    CURLOPT_FOLLOWLOCATION => false,
]);

/** @param list<string> $headers @return array{status: int, headers: array<string, string>, body: string} */
function request(CurlHandle $client, string $url, array $headers = []): array
{
    $responseHeaders = [];
    curl_setopt_array($client, [
        CURLOPT_URL => $url,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_HEADERFUNCTION => static function (CurlHandle $client, string $line) use (&$responseHeaders): int {
            $separator = strpos($line, ':');
            if ($separator !== false) {
                $responseHeaders[strtolower(trim(substr($line, 0, $separator)))] = trim(substr($line, $separator + 1));
            }
            return strlen($line);
        },
    ]);
    $body = curl_exec($client);
    if (!is_string($body)) {
        throw new RuntimeException($url . ': ' . curl_error($client));
    }

    return [
        'status' => curl_getinfo($client, CURLINFO_RESPONSE_CODE),
        'headers' => $responseHeaders,
        'body' => $body,
    ];
}

function requireCondition(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $html = request($client, $baseUrl . '/', ['Accept: text/html']);
    requireCondition($html['status'] === 200, '/: HTML returned HTTP ' . $html['status']);
    if (($argv[1] ?? null) === '--ready') {
        exit(0);
    }

    requireCondition(str_contains($html['headers']['content-type'] ?? '', 'text/html'), '/: response is not HTML');
    requireCondition(
        preg_match('~<script\b(?=[^>]*\bdata-page="app")(?=[^>]*\btype="application/json")[^>]*>(.*?)</script>~s', $html['body'], $pageMatch) === 1,
        '/: missing Inertia JSON page script',
    );
    $page = json_decode($pageMatch[1], true, flags: JSON_THROW_ON_ERROR);
    requireCondition(is_array($page) && ($page['component'] ?? null) === 'Home', '/: unexpected Inertia component');
    requireCondition(str_contains($html['body'], '<div id="app"></div>'), '/: missing the Inertia mount point');
    echo "/: HTML and Inertia page OK\n";

    $inertia = request($client, $baseUrl . '/', ['Accept: text/html, application/xhtml+xml', 'X-Inertia: true', 'X-Requested-With: XMLHttpRequest']);
    requireCondition($inertia['status'] === 200, '/: Inertia returned HTTP ' . $inertia['status']);
    requireCondition(($inertia['headers']['x-inertia'] ?? '') === 'true', '/: missing X-Inertia response header');
    requireCondition(str_contains($inertia['headers']['content-type'] ?? '', 'application/json'), '/: Inertia response is not JSON');
    $inertiaPage = json_decode($inertia['body'], true, flags: JSON_THROW_ON_ERROR);
    requireCondition(is_array($inertiaPage) && ($inertiaPage['component'] ?? null) === 'Home', '/: unexpected Inertia JSON component');
    echo "/: Inertia JSON OK\n";

    preg_match_all('~(?:src|href)="(/build/assets/[^"?]+\.(?:js|css))"~', $html['body'], $assetMatches);
    $hasScript = $hasStylesheet = false;
    foreach (array_unique($assetMatches[1]) as $asset) {
        $response = request($client, $baseUrl . $asset);
        requireCondition($response['status'] === 200, $asset . ': asset returned HTTP ' . $response['status']);
        $contentType = $response['headers']['content-type'] ?? '';
        if (str_ends_with($asset, '.js')) {
            $hasScript = true;
            requireCondition(str_contains($contentType, 'javascript'), $asset . ': asset is not JavaScript');
        } else {
            $hasStylesheet = true;
            requireCondition(str_contains($contentType, 'text/css'), $asset . ': asset is not CSS');
        }
    }
    requireCondition($hasScript && $hasStylesheet, 'HTML must load both production JavaScript and CSS');
    echo "Production browser assets OK\n";

    foreach (['/.env', '/composer.json', '/config/di/services.neon', '/build/.vite/manifest.json', '/assets/js/app.ts'] as $path) {
        $response = request($client, $baseUrl . $path);
        requireCondition($response['status'] === 404, $path . ': private/source path returned HTTP ' . $response['status']);
    }
    echo "Private files and frontend sources are not publicly served\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'HTTP smoke failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
