# Repository Guidelines

## Project Structure & Module Organization

This repository is a WordPress plugin that enhances block-level Additional CSS support.

- `additional-css-enhancement.php` contains the plugin header, hooks, supported block list, frontend rendering, and CSS template validation.
- `uninstall.php` removes the plugin's compiled CSS cache attributes while preserving user-authored `style.css` values.
- `src/additional-css-enhancement.jsx` registers the editor UI and live preview behavior.
- `src/custom-css-parser.js` parses and scopes user CSS; keep parser behavior covered by tests.
- `src/custom-css-parser.test.js` contains Jest unit tests.
- `tests/additional-css-enhancement.test.php` is a standalone PHP test runner with WordPress function stubs.
- `build/` contains generated editor assets. Rebuild it after source changes.

## Build, Test, and Development Commands

Use Node `>=20.10.0` and npm `>=10.2.3`.

- `npm install` installs WordPress scripts and parser dependencies.
- `npm run build` compiles `src/additional-css-enhancement.jsx` into `build/`.
- `npm run watch` starts the development build watcher for editor work.
- `npm test` runs JavaScript unit tests, then the PHP test runner.
- `npm run test:php` runs only `tests/additional-css-enhancement.test.php`.

## Coding Style & Naming Conventions

Follow the existing WordPress style: tabs for indentation in PHP and JS, spaces inside parentheses for WordPress-flavored JavaScript, and snake_case for PHP functions. Prefix PHP symbols with `acsse_` and constants with `ACSSE_`. Use camelCase for JavaScript functions. Keep user-facing strings translation-ready with `__()` or `sprintf()`.

## Testing Guidelines

Add or update Jest tests for parser changes in `src/custom-css-parser.test.js`. Add PHP assertions when behavior affects registered block supports, frontend rendering, template validation, or uninstall cleanup. Test names should describe accepted or rejected behavior, for example `rejects unsupported at-rules` or `PASS render supported block`.

Run `npm test` before handing off changes. If you only touched PHP logic, `npm run test:php` is acceptable during iteration.

## Commit & Pull Request Guidelines

This checkout has no committed history yet, so use concise conventional-style commits such as `feat: add enhanced css control` or `test: cover uninstall cleanup`. Pull requests should include a behavior summary, test results, and screenshots or notes for editor UI changes. Mention WordPress version assumptions and confirm generated `build/` files are updated when `src/` changes.

## Security & Configuration Tips

Do not render raw editor CSS unless it passes the compiled template validator. Preserve the selector token flow between JS and PHP, and check both editor preview and frontend rendering before adding supported blocks.
