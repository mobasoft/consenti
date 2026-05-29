<?php

declare(strict_types=1);

namespace Mobasoft\Consenti\Middleware;

use DOMDocument;
use DOMElement;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ExternalScriptBlockerMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        $contentType = $response->getHeaderLine('Content-Type');
        if (stripos($contentType, 'text/html') === false) {
            return $response;
        }

        $consent = $this->getConsentFromCookie($request);
        if (!empty($consent['marketing']) && !empty($consent['statistics'])) {
            return $response;
        }

        $html = (string)$response->getBody();
        if ($html === '' || stripos($html, '<script') === false) {
            return $response;
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        if (!$loaded) {
            return $response;
        }

        $host = $request->getUri()->getHost();
        foreach ($dom->getElementsByTagName('script') as $script) {
            if (!$script instanceof DOMElement) {
                continue;
            }
            $this->maybeBlockScript($script, $host, $consent);
        }

        $updated = $dom->saveHTML();
        if (!is_string($updated) || $updated === '') {
            return $response;
        }

        $response->getBody()->rewind();
        $response->getBody()->write($updated);
        return $response;
    }

    private function maybeBlockScript(DOMElement $script, string $currentHost, array $consent): void
    {
        if ($script->hasAttribute('data-consenti-ignore')) {
            return;
        }
        if ($script->getAttribute('type') === 'text/plain') {
            return;
        }
        $src = trim($script->getAttribute('src'));
        if ($src === '') {
            return;
        }
        if (!$this->isExternalSource($src, $currentHost)) {
            return;
        }

        $category = $this->detectCategory($src);
        if (!empty($consent[$category])) {
            return;
        }

        $script->setAttribute('data-consenti-src', $src);
        $script->setAttribute('data-consenti-category', $category);
        $script->removeAttribute('src');
        $script->setAttribute('type', 'text/plain');
    }

    private function detectCategory(string $src): string
    {
        $needle = strtolower($src);
        $statisticsPatterns = ['matomo', 'plausible', 'analytics', 'gtag', 'googletagmanager'];
        foreach ($statisticsPatterns as $pattern) {
            if (str_contains($needle, $pattern)) {
                return 'statistics';
            }
        }

        return 'marketing';
    }

    private function isExternalSource(string $src, string $currentHost): bool
    {
        $normalizedSrc = $src;
        if (str_starts_with($normalizedSrc, '//')) {
            $normalizedSrc = 'https:' . $normalizedSrc;
        }

        if (!preg_match('#^https?://#i', $normalizedSrc)) {
            return false;
        }

        $scriptHost = (string)parse_url($normalizedSrc, PHP_URL_HOST);
        if ($scriptHost === '') {
            return false;
        }

        return strcasecmp($scriptHost, $currentHost) !== 0;
    }

    private function getConsentFromCookie(ServerRequestInterface $request): array
    {
        $cookie = $request->getCookieParams()['consenti_consent'] ?? '';
        if (!is_string($cookie) || $cookie === '') {
            return [];
        }
        $decoded = json_decode(rawurldecode($cookie), true);
        return is_array($decoded) ? $decoded : [];
    }
}
