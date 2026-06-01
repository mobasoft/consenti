<?php

declare(strict_types=1);

namespace Mobasoft\Consenti\Middleware;

use DOMDocument;
use DOMElement;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\StreamFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class ExternalScriptBlockerMiddleware implements MiddlewareInterface
{
    /**
     * @var array<int, array{domains: array<int, string>, category: string, whitelist: bool, blacklist: bool}>
     */
    private array $serviceRules = [];

    private bool $serviceRulesLoaded = false;

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

        return $response->withBody(
            GeneralUtility::makeInstance(StreamFactory::class)->createStream($updated)
        );
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

        $decision = $this->resolveDecision($src);
        if ($decision['whitelist']) {
            return;
        }
        $category = $decision['category'];
        if (!$decision['blacklist'] && !empty($consent[$category])) {
            return;
        }

        $iframe->setAttribute('data-consenti-src', $src);
        $iframe->setAttribute('data-consenti-category', $category);
        $iframe->setAttribute('data-consenti-blocked', '1');
        if ($decision['blacklist']) {
            $iframe->setAttribute('data-consenti-blacklist', '1');
        }
        $iframe->setAttribute('style', trim($iframe->getAttribute('style') . ';display:none;'));
        $iframe->removeAttribute('src');

        $placeholder = $iframe->ownerDocument->createElement('div');
        $placeholder->setAttribute('class', 'consenti-embed-placeholder');
        $placeholder->setAttribute('data-consenti-placeholder', '1');
        $placeholder->setAttribute('data-consenti-category', $category);
        $placeholder->setAttribute('style', $this->buildPlaceholderStyle($iframe));

        $message = $iframe->ownerDocument->createElement(
            'p',
            $decision['blacklist']
                ? 'Dieser Inhalt wurde durch eine Consenti-Blacklist blockiert.'
                : sprintf(
                    'Dieser Inhalt ist blockiert, bis "%s" erlaubt ist.',
                    $category === 'marketing' ? 'Marketing' : 'Statistik'
                )
        );
        $message->setAttribute('class', 'consenti-embed-message');
        $placeholder->appendChild($message);

        if (!$decision['blacklist']) {
            $allowButton = $iframe->ownerDocument->createElement('button', $category === 'marketing' ? 'Inhalt laden (Marketing)' : 'Inhalt laden (Statistik)');
            $allowButton->setAttribute('type', 'button');
            $allowButton->setAttribute('class', 'consenti-embed-action');
            $allowButton->setAttribute('data-consenti-allow-category', $category);
            $placeholder->appendChild($allowButton);
        }

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

        $decision = $this->resolveDecision($src);
        if ($decision['whitelist']) {
            return;
        }
        $category = $decision['category'];
        if (!$decision['blacklist'] && !empty($consent[$category])) {
            return;
        }

        $script->setAttribute('data-consenti-src', $src);
        $script->setAttribute('data-consenti-category', $category);
        if ($decision['blacklist']) {
            $script->setAttribute('data-consenti-blacklist', '1');
        }
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

    /**
     * @return array{category: string, whitelist: bool, blacklist: bool}
     */
    private function resolveDecision(string $src): array
    {
        $this->loadServiceRules();
        $host = $this->extractHost($src);
        if ($host !== '') {
            foreach ($this->serviceRules as $rule) {
                foreach ($rule['domains'] as $domain) {
                    if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                        return [
                            'category' => $rule['category'],
                            'whitelist' => $rule['whitelist'],
                            'blacklist' => $rule['blacklist'],
                        ];
                    }
                }
            }
        }

        return [
            'category' => $this->detectCategory($src),
            'whitelist' => false,
            'blacklist' => false,
        ];
    }

    private function loadServiceRules(): void
    {
        if ($this->serviceRulesLoaded) {
            return;
        }
        $this->serviceRulesLoaded = true;

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_consenti_domain_model_service');
        $rows = $queryBuilder
            ->select('domains', 'category', 'whitelist', 'blacklist')
            ->from('tx_consenti_domain_model_service')
            ->where(
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('hidden', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER))
            );

        $storagePids = $this->getConfiguredStoragePids();
        if ($storagePids !== []) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->in(
                    'pid',
                    $queryBuilder->createNamedParameter($storagePids, Connection::PARAM_INT_ARRAY)
                )
            );
        }

        $rows = $queryBuilder
            ->orderBy('sorting')
            ->executeQuery()
            ->fetchAllAssociative();

        foreach ($rows as $row) {
            $domains = array_values(array_filter(array_map(
                fn(string $domain): string => $this->normalizeDomain($domain),
                preg_split('/[\s,]+/', (string)($row['domains'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: []
            )));
            if ($domains === []) {
                continue;
            }
            $category = (string)($row['category'] ?? 'marketing');
            if (!in_array($category, ['statistics', 'marketing'], true)) {
                $category = 'marketing';
            }

            $this->serviceRules[] = [
                'domains' => $domains,
                'category' => $category,
                'whitelist' => (bool)($row['whitelist'] ?? false),
                'blacklist' => (bool)($row['blacklist'] ?? false),
            ];
        }
    }

    private function normalizeDomain(string $domain): string
    {
        $value = strtolower(trim($domain));
        $value = trim($value, '.');
        if ($value === '' || str_contains($value, '/')) {
            return '';
        }

        if (!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $value)) {
            return '';
        }

        return $value;
    }

    /**
     * @return array<int, int>
     */
    private function getConfiguredStoragePids(): array
    {
        if (!isset($GLOBALS['TSFE']) || !is_object($GLOBALS['TSFE']) || !isset($GLOBALS['TSFE']->tmpl)) {
            return [];
        }
        $setup = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_consenti.'] ?? null;
        if (!is_array($setup)) {
            return [];
        }
        $raw = trim((string)($setup['storagePid'] ?? ''));
        if ($raw === '') {
            return [];
        }
        $parts = preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $pids = array_map('intval', $parts);
        $pids = array_values(array_filter($pids, static fn(int $pid): bool => $pid > 0));
        return array_values(array_unique($pids));
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

    private function extractHost(string $src): string
    {
        $normalizedSrc = $src;
        if (str_starts_with($normalizedSrc, '//')) {
            $normalizedSrc = 'https:' . $normalizedSrc;
        }
        if (!preg_match('#^https?://#i', $normalizedSrc)) {
            return '';
        }
        return strtolower((string)parse_url($normalizedSrc, PHP_URL_HOST));
    }

    private function getConsentFromCookie(ServerRequestInterface $request): array
    {
        $cookieName = $this->getConfiguredCookieName();
        $cookie = $request->getCookieParams()[$cookieName] ?? '';
        if (!is_string($cookie) || $cookie === '') {
            return [];
        }
        $decoded = json_decode(rawurldecode($cookie), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function getConfiguredCookieName(): string
    {
        if (!isset($GLOBALS['TSFE']) || !is_object($GLOBALS['TSFE']) || !isset($GLOBALS['TSFE']->tmpl)) {
            return 'consenti_consent';
        }
        $setup = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_consenti.'] ?? null;
        if (!is_array($setup)) {
            return 'consenti_consent';
        }
        $cookieName = trim((string)($setup['cookieName'] ?? ''));
        return $cookieName !== '' ? $cookieName : 'consenti_consent';
    }
}
