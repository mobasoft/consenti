# consenti

Custom TYPO3 cookie-consent extension for project-specific requirements.

## Version Scope

- Current target: **TYPO3 14** (`^14.0`)
- This branch is the TYPO3 14 line (`v14`).

## Current Feature Set (v1)

- Cookie banner in frontend with three categories:
  - `necessary` (always active)
  - `statistics`
  - `marketing`
- Consent storage in cookie `consenti_consent` (JSON).
- Automatic blocking of external scripts without consent:
  - External `<script src="https://...">` tags are converted to `type="text/plain"` in middleware.
  - Original source is stored in `data-consenti-src`.
  - Category is stored in `data-consenti-category`.
- Automatic blocking of external iframes without consent:
  - External `<iframe src="https://...">` sources are moved to `data-consenti-src`.
  - iFrames are restored only after category consent.
- Blocked iframe placeholder UX:
  - Inline placeholder explains why content is blocked.
  - Placeholder area is visibly highlighted (light hatch background) and keeps embed size context.
  - `Inhalt laden` enables only the required category and loads immediately (without accepting all categories).
  - `Cookie-Einstellungen` opens the consent dialog.
- Automatic category detection for external scripts:
  - `statistics` for known analytics patterns (e.g. `matomo`, `plausible`, `analytics`, `gtag`, `googletagmanager`)
  - `marketing` as fallback for other external sources
- Backend-managed service rules (new):
  - New table: `tx_consenti_domain_model_service`
  - Map domains to category (`statistics` or `marketing`)
  - Optional whitelist flag to always allow matching domains
  - Optional blacklist flag to never allow matching domains
  - Rule order follows record sorting
- Scanner MVP for external sources (new):
  - New table: `tx_consenti_domain_model_discovery`
  - Tracks detected external hosts from `script` and `iframe` sources
  - Stores category, source type, hit counter, timestamps, and last decision
- Automatic script execution after consent:
  - Blocked scripts are loaded after user approval of their category.
- Bootstrap/CI color usage:
  - Banner reads CSS variables from active theme (`--bs-primary`, `--bs-body-color`, `--bs-body-bg`).
  - Works out of the box with `bk2k/bootstrap-package` and compatible themes.
- TypoScript integration included:
  - CSS + JS assets are registered
  - Banner mount point is rendered via `footerData`
- Middleware registration included for frontend responses.
- Fixed floating cookie-settings button (left bottom) to reopen and change consent anytime.

## Extension Structure

- `Classes/Middleware/ExternalScriptBlockerMiddleware.php`
  Detects and blocks external scripts until consent is granted.
- `Configuration/RequestMiddlewares.php`
  Registers middleware in TYPO3 frontend stack.
- `Configuration/TypoScript/constants.typoscript`
  Basic configurable defaults.
- `Configuration/TypoScript/setup.typoscript`
  Includes CSS/JS and renders banner root element.
- `Resources/Public/JavaScript/consenti.js`
  Consent logic, cookie handling, banner UI, deferred script loading.
- `Resources/Public/Css/consenti.css`
  Banner, floating button, and blocked-content placeholder styling.

## Installation / Activation

1. Ensure package repository is active in root `composer.json` (`"url": "packages/*"` already present).
2. Require/update dependencies:
   - `composer dump-autoload`
3. Activate extension in TYPO3 backend (`Admin Tools > Extensions`) or via CLI.
4. Add Site Set in TYPO3 14:
   - **Site Management > Sites > [your site] > Sets**
   - add **consenti** (`mobasoft/consenti`)
   - if not visible yet: run `composer dump-autoload` and flush TYPO3 caches
5. (Legacy fallback) Include static TypoScript template:
   - **consenti**
6. Run database schema update (new tables for service rules and source discoveries).

## Configuration

Default constants in `Configuration/TypoScript/constants.typoscript`:

```typoscript
plugin.tx_consenti {
  cookieName = consenti_consent
  privacyPage =
  privacyUrl = /datenschutz
  storagePid =
  loggingPid =
  consentRevision = 1
  forceReconsentOnRevisionChange = 1
  colors {
    useThemeColors = 1
    bannerBackground = #ffffff
    bannerText = #212529
    accent = #0d6efd
    buttonTextOnAccent = #ffffff
    placeholderBackground = #f8f9fa
    placeholderBorder = #d0d7de
  }
  branding {
    enabled = 0
    text = by consenti
    url = https://github.com/mobasoft/consenti
  }
  position = bottom
  fab {
    enabled = 1
    iconPreset = gear
    customIconHtml =
    position = left
    bottom = 1rem
    offsetX = 1rem
    zIndex = 9990
  }
}
```

You can override these in site package TypoScript constants.

Preferred configuration:
- `privacyPage`: TYPO3 page uid (recommended)

Fallback configuration:
- `privacyUrl`: absolute/relative URL (legacy fallback)

Resolution behavior:
- if `privacyPage` is set, consenti resolves it via TYPO3 `typolink` (routing/slugs)
- else `privacyUrl` is used

`privacyUrl` also supports:
- absolute/relative URL (e.g. `/datenschutz`)
- TYPO3 page uid as numeric value (e.g. `123`)

Floating cookie button (`fab`) options:
- `enabled = 0|1` (default: `1`)
- `iconPreset = gear|cookie|shield` (default: `gear`)
- `customIconHtml` (if non-empty, overrides icon preset)
- `position = left|center|right` (default: `left`)
- `bottom` (e.g. `1rem`)
- `offsetX` (horizontal offset for `left`/`right`)
- `zIndex` (e.g. `9990`)

If `fab.enabled = 0`, you can still open the consent dialog from any custom link/button by adding:
- `data-consenti-open-settings="1"`

Example:

```html
<a href="#" data-consenti-open-settings="1">Cookie settings</a>
```

Custom FAB icon examples:

```typoscript
# Use preset
plugin.tx_consenti.fab.iconPreset = shield

# Or override with custom symbol/entity
plugin.tx_consenti.fab.customIconHtml = &#x2699;
```

Service-rule scope:
- `storagePid`: comma-separated PID list for service-rule records (empty = all records)

Logging scope:
- `loggingPid`: target sysfolder PID for discovery and consent-stat records
- fallback: first PID from `storagePid`
- if neither resolves to a valid PID (`>0`), no logging records are written

Consent lifecycle:
- `consentRevision`: arbitrary revision identifier for consent text/vendor set (e.g. `2026-06`)
- `forceReconsentOnRevisionChange`:
  - `1` = existing cookie becomes invalid when revision changes
  - `0` = keep existing cookie despite revision change

Color behavior:
- `colors.useThemeColors = 1` (default):
  - reads active Bootstrap variables (`--bs-primary`, `--bs-body-color`, `--bs-body-bg`)
- `colors.useThemeColors = 0`:
  - uses explicit TypoScript color values:
    - `colors.bannerBackground`
    - `colors.bannerText`
    - `colors.accent`
    - `colors.buttonTextOnAccent`
    - `colors.placeholderBackground`
    - `colors.placeholderBorder`

Optional branding footer:
- `branding.enabled = 1` enables a small footer link in the consent banner
- `branding.text` and `branding.url` define label and target

## Consent Cookie Format

Example:

```json
{
  "necessary": true,
  "statistics": false,
  "marketing": true
}
```

## Backend Service Rules

Create records of type **Consenti Service Rules** (table `tx_consenti_domain_model_service`) in a sysfolder:

- `Title`: internal name
- `Category`: `statistics` or `marketing`
- `Domains`: one or multiple domains (comma, whitespace, or newline separated), e.g. `youtube.com`
- `Whitelist`: if enabled, matching domains are never blocked by consenti
- `Blacklist`: if enabled, matching domains are always blocked by consenti (consent cannot load them)

Matching behavior:
- exact domain match and subdomains are supported (e.g. rule `youtube.com` matches `www.youtube.com`)
- first matching rule by sorting is used
- if no rule matches, fallback heuristics are used

## Backend Source Discoveries (Scanner MVP)

Detected external sources are written to `tx_consenti_domain_model_discovery` and can be reviewed via List module in a sysfolder.

Stored fields:
- `host` (detected external domain)
- `category` (`statistics` or `marketing`)
- `source_type` (`script` or `iframe`)
- `decision` (`blocked`, `consented`, `whitelist`, `blacklist`)
- `hits`, `first_seen`, `last_seen`, `last_source_url`

Cleanup command:

```bash
# Delete discoveries older than 90 days (default)
ddev typo3 consenti:discovery:cleanup

# Preview only
ddev typo3 consenti:discovery:cleanup --dry-run

# Custom threshold
ddev typo3 consenti:discovery:cleanup --days=30

# Remove all discovery records
ddev typo3 consenti:discovery:cleanup --all
```

Consent stats logging:
- Aggregated page-request logging by day + revision + consent state
- Table: `tx_consenti_domain_model_consent_stat`
- Fields: `date_key`, `revision`, `necessary`, `statistics`, `marketing`, `hits`, `first_seen`, `last_seen`

Cleanup command:

```bash
# Delete consent stats older than 365 days (default)
ddev typo3 consenti:consent-stats:cleanup

# Preview only
ddev typo3 consenti:consent-stats:cleanup --dry-run

# Custom threshold
ddev typo3 consenti:consent-stats:cleanup --days=90

# Remove all consent stats records
ddev typo3 consenti:consent-stats:cleanup --all
```

## Roadmap

### Stage 1: MVP (done)

- Frontend banner with consent cookie
- Script blocking/unblocking
- TYPO3 routing-compatible privacy link generation
- Bootstrap color adoption

### Stage 2: v1 (current)

- TYPO3 14 baseline
- Script + iFrame auto-blocking in middleware
- Category-based deferred loading for blocked assets

### Stage 3: v2 (next)

- Backend configuration (categories/services/domains) (started)
- Domain whitelist/blacklist + manual overrides (started)
- Scanner/reporting UI for third-party sources (MVP tracking done, dedicated UI pending)
- XLF language support
- Stronger consent lifecycle (revisioning, re-consent flows)

## Current Limitations

- No dedicated backend module yet (record management via List module/TCA available).
- Script categorization is heuristic and should be made configurable.
- No dedicated scanner/reporting backend module yet (MVP data tracking is available via List module).

## Development Notes

- TYPO3 target: `^14.0`
- PHP classes are strict-typed.
- Middleware only modifies `text/html` frontend responses.
- Internal/local scripts and iframes are not touched.

## Smoke Test Checklist

Quick manual validation after changes:

1. Open a page with external media (e.g. YouTube embed).
2. Without consent cookie:
   - external iframe/script is blocked
   - placeholder is visible
3. With `statistics=true` and `marketing=false`:
   - marketing embeds stay blocked
4. With `statistics=true` and `marketing=true`:
   - blocked embeds are loaded/restored
5. Set `plugin.tx_consenti.cookieName` to a custom value:
   - banner still stores consent
   - middleware reads the configured cookie and unblocks correctly
6. Set `plugin.tx_consenti.position = top`:
   - banner is rendered at top, not bottom

## Links

- Issue tracker: https://github.com/mobasoft/consenti/issues
- Repository: https://github.com/mobasoft/consenti
- GitHub profile: https://github.com/mobasoft
