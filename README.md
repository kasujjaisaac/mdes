<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).


## Security and deployment

Document access is scoped to visible schools. Confidential/restricted files additionally
require document-management permission or ownership. School return edits require
explicit write permission; reviews also require school visibility.

Reports generate private CSV, XLSX and PDF files. Statistical exports contain locked
returns only. Only the generating user can download an export, and the user's current
school scope is checked again at download time. PDF exports are limited to 5,000 rows;
use CSV or XLSX for larger reports. Historic export logs have no downloadable file.

Run the export file migration with `php artisan migrate --force` when deploying.
Run `php artisan mdsiss:security-audit` after installing dependencies and at least daily
for a fresh readiness result. It records a timestamp and Composer lockfile hash.
An unsuccessful or stale scan never counts as a clean dependency audit.

The readiness page measures configuration, database access, private storage, demo
identities and default administrator passwords. For production use HTTPS, disable debug,
enable secure HTTP-only session cookies and configure a real mail transport. Verify
password-reset delivery against that provider; a configured transport alone is not proof
of delivery. Local development settings are intentionally not reported as production ready.

After an actual backup restore drill, set `MDSISS_BACKUP_RESTORE_VERIFIED_AT` to the
verification timestamp and `MDSISS_BACKUP_RESTORE_EVIDENCE` to the retained evidence
reference. This is an operator attestation, not an automated restore test; it expires after
30 days. Never set it before performing the drill.

The general seeder creates its demo administrator only in local/testing and never resets
an existing administrator password. For production, seed reference data, then provision
an administrator using `php artisan mdsiss:create-admin` (interactive hidden password
entry). Remove development demo identities from a production database and rotate any
known default credentials before launch. No existing local credentials are changed by
these deployment safeguards.
