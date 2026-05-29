# consenti

Custom TYPO3 cookie-consent extension for project-specific requirements.

## Version Scope

- Current mainline target: **TYPO3 13** (`^13.4`)
- TYPO3 14 support is planned in a dedicated later branch (`v14`).

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
4. Include static TypoScript template:
   - **consenti**

## Configuration

Default constants in `Configuration/TypoScript/constants.typoscript`:

```typoscript
plugin.tx_consenti {
  cookieName = consenti_consent
  privacyUrl = /datenschutz
  position = bottom
  fab {
    position = left
    bottom = 1rem
    offsetX = 1rem
    zIndex = 9990
  }
}
```

You can override these in site package TypoScript constants.

`privacyUrl` supports:
- absolute/relative URL (e.g. `/datenschutz`)
- TYPO3 page uid as numeric value (e.g. `123`)

If a page uid is provided, `consenti` resolves it via TYPO3 `typolink` on the server side, so configured site routing/slugs are used.

Floating cookie button (`fab`) options:
- `position = left|center|right` (default: `left`)
- `bottom` (e.g. `1rem`)
- `offsetX` (horizontal offset for `left`/`right`)
- `zIndex` (e.g. `9990`)

## Consent Cookie Format

Example:

```json
{
  "necessary": true,
  "statistics": false,
  "marketing": true
}
```

## Roadmap

### Stage 1: MVP (done)

- Frontend banner with consent cookie
- Script blocking/unblocking
- TYPO3 routing-compatible privacy link generation
- Bootstrap color adoption

### Stage 2: v1 (current)

- TYPO3 13-only baseline
- Script + iFrame auto-blocking in middleware
- Category-based deferred loading for blocked assets

### Stage 3: v2 (next)

- Backend configuration (categories/services/domains)
- Domain whitelist/blacklist + manual overrides
- Scanner/reporting UI for third-party sources
- XLF language support
- Stronger consent lifecycle (revisioning, re-consent flows)

## Current Limitations

- No backend module or TCA-driven management UI yet.
- No domain whitelist/blacklist configuration yet.
- Script categorization is heuristic and should be made configurable.
- No multilingual labels in XLF yet.
- No scanner/reporting view for detected third-party sources yet.

## Development Notes

- TYPO3 target: `^13.4`
- PHP classes are strict-typed.
- Middleware only modifies `text/html` frontend responses.
- Internal/local scripts and iframes are not touched.
