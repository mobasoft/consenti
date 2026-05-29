# consenti

Custom TYPO3 cookie-consent extension for project-specific requirements.

## Current Feature Set (MVP)

- Cookie banner in frontend with three categories:
  - `necessary` (always active)
  - `statistics`
  - `marketing`
- Consent storage in cookie `consenti_consent` (JSON).
- Automatic blocking of external scripts without consent:
  - External `<script src="https://...">` tags are converted to `type="text/plain"` in middleware.
  - Original source is stored in `data-consenti-src`.
  - Category is stored in `data-consenti-category`.
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
  Banner styling.

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
}
```

You can override these in site package TypoScript constants.

## Consent Cookie Format

Example:

```json
{
  "necessary": true,
  "statistics": false,
  "marketing": true
}
```

## Current Limitations (next steps)

- No backend module or TCA-driven management UI yet.
- No domain whitelist/blacklist configuration yet.
- No automatic blocking for iframes/embeds yet.
- Script categorization is heuristic and should be made configurable.
- No multilingual labels in XLF yet.
- No scanner/reporting view for detected third-party sources yet.

## Development Notes

- TYPO3 target: `^13.4 || ^14.0`
- PHP classes are strict-typed.
- Middleware only modifies `text/html` frontend responses.
- Internal/local scripts are not touched.
