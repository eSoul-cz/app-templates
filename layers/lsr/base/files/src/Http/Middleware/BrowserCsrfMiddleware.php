<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Lsr\Core\Requests\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * CSRF protection for unsafe methods: the request must come from the app's own browser client
 * (Inertia or XHR) and its Origin (or Referer) must match the public origin of the request.
 *
 * The public origin is read from `X-Forwarded-Proto` / `X-Forwarded-Host` / `Host`, so the reverse proxy
 * in front of RoadRunner must set them and must not forward client-supplied values.
 */
readonly class BrowserCsrfMiddleware implements MiddlewareInterface
{
    /** @var list<string> */
    private const array SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
        if (in_array(strtoupper($request->getMethod()), self::SAFE_METHODS, true)) {
            return $handler->handle($request);
        }

        if ( ! $this->hasBrowserClientHeader($request) || ! $this->hasSameOrigin($request)) {
            return Response::create(419)->withJsonBody([
                'message' => 'The request could not be verified. Reload the page and try again.',
            ]);
        }

        return $handler->handle($request);
    }

    private function hasBrowserClientHeader(ServerRequestInterface $request): bool {
        return strtolower($request->getHeaderLine('X-Inertia')) === 'true'
            || strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest';
    }

    private function hasSameOrigin(ServerRequestInterface $request): bool {
        $source = $request->getHeaderLine('Origin');
        if ($source === '') {
            $source = $request->getHeaderLine('Referer');
        }
        if ($source === '') {
            return false;
        }

        $parts = parse_url($source);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        $uri = $request->getUri();
        $scheme = $this->firstValue($request->getHeaderLine('X-Forwarded-Proto')) ?? $uri->getScheme();
        $authority = $this->firstValue($request->getHeaderLine('X-Forwarded-Host'))
            ?? $this->firstValue($request->getHeaderLine('Host'))
            ?? $uri->getAuthority();

        $sourceAuthority = $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');

        return $this->origin($parts['scheme'], $sourceAuthority) === $this->origin($scheme, $authority);
    }

    private function firstValue(string $header): ?string {
        $value = trim(explode(',', $header, 2)[0]);

        return $value === '' ? null : $value;
    }

    private function origin(string $scheme, string $authority): string {
        $scheme = strtolower($scheme);
        $authority = strtolower($authority);
        $defaultPort = match ($scheme) {
            'http'  => ':80',
            'https' => ':443',
            default => '',
        };
        if ($defaultPort !== '' && str_ends_with($authority, $defaultPort)) {
            $authority = substr($authority, 0, -strlen($defaultPort));
        }

        return $scheme . '://' . $authority;
    }
}
