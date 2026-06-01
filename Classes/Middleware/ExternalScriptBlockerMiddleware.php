<?php

declare(strict_types=1);

namespace Mobasoft\Consenti\Middleware;

use DOMDocument;
use DOMElement;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\StreamFactory;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class ExternalScriptBlockerMiddleware implements MiddlewareInterface
{
    /**
     * @var array<int, array{domains: array<int, string>, category: string, whitelist: bool, blacklist: bool}>
     */
    private array $serviceRules = [];

    private bool $serviceRulesLoaded = false;

    /**
     * @var array<string, int>
     */
    private array $scannerHits = [];

    /**
     * @var array<string, array{host: string, category: string, sourceType: string, lastSourceUrl: string, decision: string}>
     */
    private array $scannerRows = [];

    /**
     * @var array<string, mixed>
     */
    private array $flatSettings = [];

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        $contentType = $response->getHeaderLine('Content-Type');
        if (stripos($contentType, 'text/html') === false) {
            return $response;
        }
        $this->initializeRuntimeSettings($request);

        $consent = $this->getConsentFromCookie($request);
        $consent = $this->validateConsentRevision($consent);
        $this->logConsentState($consent);
        $allConsented = !empty($consent['marketing']) && !empty($consent['statistics']);

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
            $this->maybeBlockScript($script, $host, $consent, $allConsented);
        }
        foreach ($dom->getElementsByTagName('iframe') as $iframe) {
            if (!$iframe instanceof DOMElement) {
                continue;
            }
            $this->maybeBlockIframe($iframe, $host, $consent, $allConsented);
        }

        $this->flushScannerRows();

        $updated = $dom->saveHTML();
        if (!is_string($updated) || $updated === '') {
            return $response;
        }

        return $response->withBody(
            GeneralUtility::makeInstance(StreamFactory::class)->createStream($updated)
        );
    }

    private function initializeRuntimeSettings(ServerRequestInterface $request): void
    {
        $this->flatSettings = [];
        $frontendTypoScript = $request->getAttribute('frontend.typoscript');
        if ($frontendTypoScript instanceof FrontendTypoScript) {
            $this->flatSettings = $frontendTypoScript->getFlatSettings();
        }
    }

    private function getFlatSettingString(string $key): string
    {
        $value = $this->flatSettings[$key] ?? '';
        return trim((string)$value);
    }

    private function maybeBlockIframe(DOMElement $iframe, string $currentHost, array $consent, bool $allConsented): void
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
        $this->collectScannerRow(
            $src,
            $decision['category'],
            'iframe',
            $this->buildDecisionLabel($decision, $consent, $allConsented)
        );
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

        $message = $iframe->ownerDocument->createElement('p');
        $message->setAttribute('class', 'consenti-embed-message');
        $placeholder->appendChild($message);

        if (!$decision['blacklist']) {
            $allowButton = $iframe->ownerDocument->createElement('button');
            $allowButton->setAttribute('type', 'button');
            $allowButton->setAttribute('class', 'consenti-embed-action');
            $allowButton->setAttribute('data-consenti-allow-category', $category);
            $placeholder->appendChild($allowButton);
        }

        $settingsButton = $iframe->ownerDocument->createElement('button');
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

    private function maybeBlockScript(DOMElement $script, string $currentHost, array $consent, bool $allConsented): void
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
        $this->collectScannerRow(
            $src,
            $decision['category'],
            'script',
            $this->buildDecisionLabel($decision, $consent, $allConsented)
        );
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
                    $queryBuilder->createNamedParameter($storagePids, ArrayParameterType::INTEGER)
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

    private function buildDecisionLabel(array $decision, array $consent, bool $allConsented): string
    {
        if ($decision['blacklist']) {
            return 'blacklist';
        }
        if ($decision['whitelist']) {
            return 'whitelist';
        }
        if ($allConsented || !empty($consent[$decision['category']])) {
            return 'consented';
        }
        return 'blocked';
    }

    private function collectScannerRow(string $src, string $category, string $sourceType, string $decision): void
    {
        $host = $this->extractHost($src);
        if ($host === '') {
            return;
        }
        $key = $host . '|' . $category . '|' . $sourceType;
        $this->scannerHits[$key] = ($this->scannerHits[$key] ?? 0) + 1;
        $this->scannerRows[$key] = [
            'host' => $host,
            'category' => $category,
            'sourceType' => $sourceType,
            'lastSourceUrl' => $src,
            'decision' => $decision,
        ];
    }

    private function flushScannerRows(): void
    {
        if ($this->scannerRows === []) {
            return;
        }
        $pid = $this->getLoggingPid();
        if ($pid <= 0) {
            return;
        }
        try {
            $connection = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getConnectionForTable('tx_consenti_domain_model_discovery');
            $now = time();

            foreach ($this->scannerRows as $key => $row) {
                $hits = $this->scannerHits[$key] ?? 1;
                $queryBuilder = $connection->createQueryBuilder();
                $existing = $queryBuilder
                    ->select('uid', 'hits')
                    ->from('tx_consenti_domain_model_discovery')
                    ->where(
                        $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER)),
                        $queryBuilder->expr()->eq('hidden', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER)),
                        $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, ParameterType::INTEGER)),
                        $queryBuilder->expr()->eq('host', $queryBuilder->createNamedParameter($row['host'])),
                        $queryBuilder->expr()->eq('category', $queryBuilder->createNamedParameter($row['category'])),
                        $queryBuilder->expr()->eq('source_type', $queryBuilder->createNamedParameter($row['sourceType']))
                    )
                    ->setMaxResults(1)
                    ->executeQuery()
                    ->fetchAssociative();

                if (is_array($existing) && isset($existing['uid'])) {
                    $connection->update(
                        'tx_consenti_domain_model_discovery',
                        [
                            'tstamp' => $now,
                            'last_seen' => $now,
                            'hits' => ((int)($existing['hits'] ?? 0)) + $hits,
                            'last_source_url' => $row['lastSourceUrl'],
                            'decision' => $row['decision'],
                        ],
                        ['uid' => (int)$existing['uid']]
                    );
                    continue;
                }

                $connection->insert(
                    'tx_consenti_domain_model_discovery',
                    [
                        'pid' => $pid,
                        'tstamp' => $now,
                        'crdate' => $now,
                        'cruser_id' => 0,
                        'deleted' => 0,
                        'hidden' => 0,
                        'sorting' => 0,
                        'host' => $row['host'],
                        'category' => $row['category'],
                        'source_type' => $row['sourceType'],
                        'last_source_url' => $row['lastSourceUrl'],
                        'first_seen' => $now,
                        'last_seen' => $now,
                        'hits' => $hits,
                        'decision' => $row['decision'],
                    ]
                );
            }
        } catch (\Throwable) {
            // Scanner is best-effort and must never break frontend rendering.
        }
    }

    private function getLoggingPid(): int
    {
        $loggingPid = (int)$this->getFlatSettingString('plugin.tx_consenti.loggingPid');
        if ($loggingPid > 0) {
            return $loggingPid;
        }

        if (!isset($GLOBALS['TSFE']) || !is_object($GLOBALS['TSFE']) || !isset($GLOBALS['TSFE']->tmpl)) {
            return 0;
        }
        $setup = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_consenti.'] ?? null;
        if (!is_array($setup)) {
            return 0;
        }

        $loggingPid = (int)trim((string)($setup['loggingPid'] ?? ''));
        if ($loggingPid > 0) {
            return $loggingPid;
        }

        $storagePids = $this->getConfiguredStoragePids();
        if ($storagePids !== []) {
            return (int)$storagePids[0];
        }
        return 0;
    }

    /**
     * @return array<int, int>
     */
    private function getConfiguredStoragePids(): array
    {
        $rawFromSettings = $this->getFlatSettingString('plugin.tx_consenti.storagePid');
        if ($rawFromSettings !== '') {
            $parts = preg_split('/[\s,]+/', $rawFromSettings, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $pids = array_map('intval', $parts);
            $pids = array_values(array_filter($pids, static fn(int $pid): bool => $pid > 0));
            $pids = array_values(array_unique($pids));
            if ($pids !== []) {
                return $pids;
            }
        }
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
        $cookieNameFromSettings = $this->getFlatSettingString('plugin.tx_consenti.cookieName');
        if ($cookieNameFromSettings !== '') {
            return $cookieNameFromSettings;
        }
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

    private function validateConsentRevision(array $consent): array
    {
        if ($consent === []) {
            return [];
        }
        $configuredRevision = $this->getConfiguredConsentRevision();
        if ($configuredRevision === '') {
            return $consent;
        }
        if (!$this->isForceReconsentOnRevisionChangeEnabled()) {
            return $consent;
        }
        $cookieRevision = trim((string)($consent['revision'] ?? ''));
        return $cookieRevision === $configuredRevision ? $consent : [];
    }

    private function getConfiguredConsentRevision(): string
    {
        $revisionFromSettings = $this->getFlatSettingString('plugin.tx_consenti.consentRevision');
        if ($revisionFromSettings !== '') {
            return $revisionFromSettings;
        }
        if (!isset($GLOBALS['TSFE']) || !is_object($GLOBALS['TSFE']) || !isset($GLOBALS['TSFE']->tmpl)) {
            return '';
        }
        $setup = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_consenti.'] ?? null;
        if (!is_array($setup)) {
            return '';
        }
        return trim((string)($setup['consentRevision'] ?? ''));
    }

    private function isForceReconsentOnRevisionChangeEnabled(): bool
    {
        $forceSetting = $this->getFlatSettingString('plugin.tx_consenti.forceReconsentOnRevisionChange');
        if ($forceSetting !== '') {
            return $forceSetting !== '0';
        }
        if (!isset($GLOBALS['TSFE']) || !is_object($GLOBALS['TSFE']) || !isset($GLOBALS['TSFE']->tmpl)) {
            return true;
        }
        $setup = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_consenti.'] ?? null;
        if (!is_array($setup)) {
            return true;
        }
        return (string)($setup['forceReconsentOnRevisionChange'] ?? '1') !== '0';
    }

    private function logConsentState(array $consent): void
    {
        $pid = $this->getLoggingPid();
        if ($pid <= 0) {
            return;
        }
        try {
            $connection = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getConnectionForTable('tx_consenti_domain_model_consent_stat');
            $dateKey = date('Y-m-d');
            $revision = $this->getConfiguredConsentRevision();
            $necessary = 1;
            $statistics = !empty($consent['statistics']) ? 1 : 0;
            $marketing = !empty($consent['marketing']) ? 1 : 0;
            $now = time();
            $queryBuilder = $connection->createQueryBuilder();
            $existing = $queryBuilder
                ->select('uid', 'hits')
                ->from('tx_consenti_domain_model_consent_stat')
                ->where(
                    $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER)),
                    $queryBuilder->expr()->eq('hidden', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER)),
                    $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, ParameterType::INTEGER)),
                    $queryBuilder->expr()->eq('date_key', $queryBuilder->createNamedParameter($dateKey)),
                    $queryBuilder->expr()->eq('revision', $queryBuilder->createNamedParameter($revision)),
                    $queryBuilder->expr()->eq('necessary', $queryBuilder->createNamedParameter($necessary, ParameterType::INTEGER)),
                    $queryBuilder->expr()->eq('statistics', $queryBuilder->createNamedParameter($statistics, ParameterType::INTEGER)),
                    $queryBuilder->expr()->eq('marketing', $queryBuilder->createNamedParameter($marketing, ParameterType::INTEGER))
                )
                ->setMaxResults(1)
                ->executeQuery()
                ->fetchAssociative();

            if (is_array($existing) && isset($existing['uid'])) {
                $connection->update(
                    'tx_consenti_domain_model_consent_stat',
                    [
                        'tstamp' => $now,
                        'last_seen' => $now,
                        'hits' => ((int)($existing['hits'] ?? 0)) + 1,
                    ],
                    ['uid' => (int)$existing['uid']]
                );
                return;
            }

            $connection->insert(
                'tx_consenti_domain_model_consent_stat',
                [
                    'pid' => $pid,
                    'tstamp' => $now,
                    'crdate' => $now,
                    'cruser_id' => 0,
                    'deleted' => 0,
                    'hidden' => 0,
                    'sorting' => 0,
                    'date_key' => $dateKey,
                    'revision' => $revision,
                    'necessary' => $necessary,
                    'statistics' => $statistics,
                    'marketing' => $marketing,
                    'hits' => 1,
                    'first_seen' => $now,
                    'last_seen' => $now,
                ]
            );
        } catch (\Throwable) {
            // Consent stats are best-effort and must never break frontend rendering.
        }
    }
}
