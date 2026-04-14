## Description

Brief description of what this PR does.

Closes #<!-- issue number -->

## Type of Change

- [ ] Bug fix (non-breaking change that fixes an issue)
- [ ] New feature (non-breaking change that adds functionality)
- [ ] Breaking change (fix or feature that changes existing functionality)
- [ ] Documentation update

## Checklist

- [ ] I have tested this change on a local WordPress installation
- [ ] All existing features still work as expected
- [ ] My code follows the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [ ] All user-facing strings are wrapped in `__()` / `esc_html__()` with the `smartly-import-export` text domain
- [ ] All output is properly escaped (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`)
- [ ] All `$_POST` / `$_GET` / `$_REQUEST` input is sanitized with `wp_unslash()` + appropriate sanitizer
- [ ] All AJAX handlers have `check_ajax_referer()` and `current_user_can()` checks
- [ ] Functions use the `smie_` prefix, CSS classes use `smie-`, constants use `SMIE_`
- [ ] No `var_dump`, `print_r`, `error_log`, or `die()` left in the code
- [ ] `readme.txt` Stable tag, Tested up to, and Changelog updated (if applicable)
- [ ] Plugin header `Version:` bumped (if applicable)

## Screenshots (if applicable)

<!-- Add screenshots to show the visual changes -->

## Testing Instructions

1. Activate the plugin on a test WordPress site.
2. Go to **Tools → Smartly Import Export**.
3. <!-- Add specific steps for this PR -->
4. Verify the expected result.
