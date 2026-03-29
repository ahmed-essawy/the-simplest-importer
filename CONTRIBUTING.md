# Contributing to The Simplest Importer

Thank you for your interest in contributing! Here's how you can help.

## Getting Started

1. Fork the repository.
2. Clone your fork locally.
3. Create a feature branch: `git checkout -b feature/your-feature-name`
4. Make your changes.
5. Push to your fork and open a Pull Request.

## Coding Standards

This plugin follows the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/).

- **PHP**: Tabs for indentation, Yoda conditions, spaces inside parentheses.
- **JavaScript**: IIFE wrapper with `'use strict'`, jQuery passed as `$`.
- **CSS**: All classes prefixed with `tsi-`.
- **Prefix**: All PHP functions, hooks, options, and transients use the `tsi_` prefix.

## Security

- Verify nonces on every form and AJAX handler.
- Check `current_user_can()` on every privileged action.
- Sanitize all input, escape all output.
- Use `$wpdb->prepare()` for any raw SQL.

## Internationalization

- Wrap all user-facing strings in `__()`, `_e()`, `esc_html__()`, etc.
- Text domain: `the-simplest-importer`

## Pull Request Guidelines

- One feature or fix per PR.
- Reference the related issue (e.g., `Closes #12`).
- Update `CHANGELOG.md` under an `[Unreleased]` section.
- Test on WordPress 5.8+ and PHP 7.4+.

## Reporting Bugs

Open a [GitHub Issue](https://github.com/ahmed-essawy/the-simplest-importer/issues) using the Bug Report template.

## Security Vulnerabilities

Please see [SECURITY.md](SECURITY.md) for responsible disclosure instructions.
