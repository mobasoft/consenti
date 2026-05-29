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
        if ($html === '' || (stripos($html, '<script') === false && stripos($html, '<iframe') === false)) {
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
        foreach ($dom->getElementsByTagName('iframe') as $iframe) {
            if (!$iframe instanceof DOMElement) {
                continue;
            }
            $this->maybeBlockIframe($iframe, $host, $consent);
        }

        $updated = $dom->saveHTML();
        if (!is_string($updated) || $updated === '') {
            return $response;
        }

        $response->getBody()->rewind();
        $response->getBody()->write($updated);
        return $response;
    }

    private function maybeBlockIframe(DOMElement $iframe, string $currentHost, array $consent): void
    {
        if ($iframe->hasAttribute('data-consenti-ignore')) {
            return;
        }
        $src = trim($iframe->getAttribute('src'));
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

        $iframe->setAttribute('data-consenti-src', $src);
        $iframe->setAttribute('data-consenti-category', $category);
        $iframe->setAttribute('data-consenti-blocked', '1');
        $iframe->setAttribute('style', trim($iframe->getAttribute('style') . ';display:none;'));
        $iframe->removeAttribute('src');

        $placeholder = $iframe->ownerDocument->createElement('div');
        $placeholder->setAttribute('class', 'consenti-embed-placeholder');
        $placeholder->setAttribute('data-consenti-placeholder', '1');
        $placeholder->setAttribute('data-consenti-category', $category);
        $placeholder->setAttribute('style', $this->buildPlaceholderStyle($iframe));

        $message = $iframe->ownerDocument->createElement(
            'p',
            sprintf(
                'Dieser Inhalt ist blockiert, bis "%s" erlaubt ist.',
                $category === 'marketing' ? 'Marketing' : 'Statistik'
            )
        );
        $message->setAttribute('class', 'consenti-embed-message');
        $placeholder->appendChild($message);

        $allowButton = $iframe->ownerDocument->createElement('button', $category === 'marketing' ? 'Inhalt laden (Marketing)' : 'Inhalt laden (Statistik)');
        $allowButton->setAttribute('type', 'button');
        $allowButton->setAttribute('class', 'consenti-embed-action');
        $allowButton->setAttribute('data-consenti-allow-category', $category);
        $placeholder->appendChild($allowButton);

        $settingsButton = $iframe->ownerDocument->createElement('button', 'Cookie-Einstellungen');
        $settingsButton->setAttribute('type', 'button');
        $settingsButton->setAttribute('class', 'consenti-embed-settings');
        $settingsButton->setAttribute('data-consenti-open-settings', '1');
        $placeholder->appendChild($settingsButton);

        $iframe->parentNode?->insertBefore($placeholder, $iframe->nextSibling);
    }

    private function buildPlaceholderStyle(DOMElement $iframe): string
    {
        $styles = ['width:100%', 'min-height:220px'];

        $width = trim($iframe->getAttribute('width'));
        if ($width !== '') {
            $styles[] = 'width:' . (ctype_digit($width) ? $width . 'px' : $width);
        }

        $height = trim($iframe->getAttribute('height'));
        if ($height !== '') {
            $styles[] = 'min-height:' . (ctype_digit($height) ? $height . 'px' : $height);
        }

        return implode(';', $styles) . ';';
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
