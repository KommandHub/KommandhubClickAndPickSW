# Security Policy

This plugin touches checkout, order data, and customer-facing storefront routes.
Please treat security reports with care.

## Supported versions

Security fixes are provided for the latest released version.

| Version | Supported |
| ------- | --------- |
| Latest release | ✅ |
| Older releases | ❌ |

## Reporting a vulnerability

**Please do not report security issues in public GitHub issues, pull requests,
or discussions.** A public report tells attackers before the fix ships.

Report privately:

1. **GitHub Security Advisories** (preferred): the repository's
   **Security → Report a vulnerability** tab.
2. **Email**: **admin@kommandhub.com**.

Please include, as far as you can:

- A description of the issue and its impact.
- Steps to reproduce, or a proof of concept.
- Affected version(s) and environment (Shopware version, PHP version).
- Any suggested remediation.

## What to expect

- **Acknowledgement** within 3 business days.
- An initial **assessment** within 10 business days.
- Updates on remediation and a coordinated disclosure timeline. Please allow a
  reasonable period to release a fix before any public disclosure.

## Scope

In scope: the plugin's own source code in this repository.

Out of scope: vulnerabilities in Shopware itself or in third-party dependencies
(report those to their respective maintainers). If a dependency issue affects
this plugin, we still want to know so we can pin or patch.

## Handling of sensitive data

Never include live credentials or production customer data in a report, issue, or
pull request. Use redacted or test values.
