# MDSISS Phase 0 Technical Assessment

## 1. Repository Assessment

### Runtime and Framework

- Laravel framework: `^13.17`.
- PHP requirement: `^8.3`.
- Application type: standard Laravel monolith.
- Current application name in `.env`: `MDSISS`.
- No separate API stack is currently implemented.

### Composer Dependencies

Runtime dependencies are intentionally minimal:

- `laravel/framework`
- `laravel/tinker`

Development dependencies include PHPUnit, Laravel Pint, Collision, Faker, Mockery, Pail and Pao.

No roles/permissions, Livewire, Inertia, Sanctum, Excel, PDF, audit, media, or activity-log packages are currently installed.

### Frontend Stack

- Vite is configured.
- Tailwind CSS 4 is configured through `@tailwindcss/vite`.
- No Vue, React, Livewire, Alpine, Inertia, or component framework is installed.
- Current Phase 1 shell uses Blade, CSS, and small vanilla JavaScript.
- `node_modules` is not installed in the current workspace, so the application shell includes a no-build fallback for local rendering.

### Authentication

Current authentication is custom Laravel session authentication:

- Login controller: `app/Http/Controllers/Auth/LoginController.php`.
- Login route: `GET /login`.
- Login submit route: `POST /login`.
- Logout route: `POST /logout`.
- Login throttling uses Laravel `RateLimiter`.
- Sessions regenerate after successful login.
- Logout invalidates the session and regenerates the CSRF token.
- Password reset is currently a placeholder page.

Current seeded development user:

- Email: `admin@mdsiss.test`
- Password: `password`

This is suitable only for local development.

### Database Configuration

- `.env` currently points to MySQL with database `mssid`.
- PHPUnit uses in-memory SQLite.
- Laravel default database config supports SQLite, MySQL, MariaDB, PostgreSQL and SQL Server.
- Existing migrations are Laravel starter migrations for users, password reset tokens, sessions, cache and jobs.
- No MDSISS business tables exist yet.

### Existing Migrations

- `0001_01_01_000000_create_users_table.php`
- `0001_01_01_000001_create_cache_table.php`
- `0001_01_01_000002_create_jobs_table.php`

### Existing Models

- `App\Models\User`

The user model uses Laravel authentication, factories, notifications, hashed password casting, fillable attributes, and hidden password/remember token fields.

### Existing Controllers

- `App\Http\Controllers\Auth\LoginController`
- `App\Http\Controllers\App\DashboardController`
- `App\Http\Controllers\App\ShellPageController`

Controllers are currently thin and appropriate for the shell stage.

### Middleware

- Standard Laravel web middleware stack is used.
- No custom middleware has been registered yet.
- Auth middleware protects dashboard and shell pages.

### Authorization Policies

- No policies exist yet.
- No gates are registered yet.
- Phase 2 must introduce roles, permissions, account status enforcement, and jurisdiction-aware authorization.

### Roles and Permissions

- No roles or permissions implementation exists yet.
- Navigation includes placeholders for roles and permissions, but visibility is not permission-driven yet.

### Route Structure

Current non-vendor routes:

- `GET /`
- `GET /login`
- `POST /login`
- `POST /logout`
- `GET /forgot-password`
- `GET /dashboard`
- `GET /app/{section}`

Routes are currently small enough to remain in `routes/web.php`. As modules grow, route files should be split by domain and loaded through `bootstrap/app.php` or a route service provider pattern.

### Service Providers

- Only `App\Providers\AppServiceProvider` is registered.
- No domain service providers exist yet.

### Storage Configuration

- Default filesystem disk is `local`.
- Local disk points to private storage at `storage/app/private`.
- Public disk exists for public assets.
- Phase 14 document management must use private/protected storage and server-side authorization.

### Queue Configuration

- Default queue connection is database.
- Jobs migration exists.
- Failed jobs are configured for database UUID storage.
- Queue workers are not configured as an operational service yet.

### Mail Configuration

- Default mailer is `log`.
- SMTP, SES, Postmark, Resend, Sendmail, Failover and Roundrobin configs are available.
- Password reset and notification email delivery are not implemented yet.

### Testing Framework

- PHPUnit 12 is configured.
- Feature tests cover login/authentication and the Phase 1 shell.
- Unit test folder contains only the starter example test.
- Tests use in-memory SQLite and array sessions.

### Seeders and Factories

- `DatabaseSeeder` creates or updates a development admin user.
- `UserFactory` exists.
- No production reference data seeders exist yet.

### Application Layout and UI Components

Phase 1 introduced:

- Authenticated layout component.
- Sidebar navigation.
- Top navigation.
- Profile menu.
- Dashboard.
- Placeholder module pages.
- Reusable Blade UI components for cards, stat cards, tables, filters, badges, alerts, modals, empty states, pagination and loading state.

### Asset Build System

- Vite build script exists through `npm run build`.
- Local `node_modules` is absent, so frontend build cannot run until `npm install` is executed.
- Current layout can render without a build manifest, but production should use built Vite assets.

### Current Dashboard

- Dashboard is shell-level only.
- It intentionally shows zero-valued placeholders because no business modules exist yet.

### Reusable UI Components

Current components live under `resources/views/components/ui`.

They are acceptable as Phase 1 foundations but should be refined as real workflows are implemented.

## 2. Identified Technical Risks

1. Authorization is not implemented beyond basic authentication.
2. No role, permission, or jurisdiction model exists yet.
3. Password reset is only a placeholder.
4. No account status enforcement exists, so suspended/deactivated users cannot yet be blocked.
5. No audit architecture exists.
6. Business modules do not yet have database constraints, policies, services or tests.
7. Frontend dependencies are not installed in the workspace.
8. The application currently has no production deployment configuration or operational queue setup.
9. The development admin password is predictable and must not be used outside local development.
10. The app has no document access-control layer yet.
11. Navigation visibility is not permission-driven yet.
12. Route organization will become difficult if every future module stays in `routes/web.php`.

## 3. Recommended Modular Monolith Structure

Use a Laravel modular monolith inside the same application, not microservices.

Recommended folder pattern:

```text
app/
  Domains/
    IdentityAccess/
      Actions/
      Data/
      Models/
      Policies/
      Services/
      QueryServices/
    DiocesanStructure/
    Schools/
    Reporting/
    Enrolment/
    AttendanceWelfare/
    Staff/
    Academics/
    Infrastructure/
    PastoralEducation/
    GovernanceCompliance/
    Inspection/
    ImprovementPlanning/
    DevelopmentProjects/
    FinancialStatistics/
    Documents/
    Communications/
    Analytics/
    DataQualityAudit/
  Http/
    Controllers/
    Requests/
  Support/
```

Recommended route pattern:

```text
routes/
  web.php
  domains/
    identity-access.php
    diocesan-structure.php
    schools.php
    reporting.php
```

Recommended view pattern:

```text
resources/views/
  auth/
  components/
  dashboard.blade.php
  domains/
    identity-access/
    diocesan-structure/
    schools/
    reporting/
```

Domain rules:

- Models represent persistent entities.
- Form Requests validate HTTP input.
- Policies/Gates authorize every sensitive operation.
- Actions perform important state changes.
- Services hold reusable domain processes.
- Query services power dashboards and reports.
- Events/listeners are used where decoupling is valuable.
- Jobs are used for slow or retryable work such as notifications and exports.

## 4. Database Development Strategy

Use migrations only. Do not modify the database manually.

Recommended strategy:

- Create small, focused migrations per phase.
- Use foreign keys for ownership and workflow relationships.
- Use database unique constraints for official identifiers and controlled codes.
- Use indexes for jurisdiction scoping, reporting period lookups, statuses, and dashboards.
- Use controlled status columns with constants/enums in PHP; do not accept free-text statuses from users.
- Separate permanent school master data from reporting-period statistics.
- Preserve history through effective-dated records and status history tables.
- Prefer soft deletes only where deletion is semantically allowed; never soft-delete audit history.
- Use append-only audit tables for high-impact changes.
- Keep statistical returns scoped to both school and reporting period.

## 5. Authorization Strategy

Phase 2 should introduce authorization before business data is implemented.

Recommended approach:

- Add roles and permissions.
- Add account statuses: active, suspended, deactivated, invited or equivalent.
- Enforce account status during login and authenticated requests.
- Use policies for resource authorization.
- Use gates for broad system abilities.
- Add server-side query scopes for school, parish, inspector portfolio and diocesan-wide access.
- Do not rely on hidden navigation links as a security boundary.
- Use authorization tests for direct URL access and ID manipulation.
- Separate technical administration from education approval authority.

Minimum Phase 2 roles:

- System Super Administrator
- Diocesan Education Secretary
- Diocesan Education Officer
- Parish Education Coordinator
- Inspector
- Headteacher
- School Data Officer
- Auditor
- Board/Leadership Viewer
- Technical Administrator

## 6. Testing Strategy

Testing should scale with risk:

- Feature tests for authentication, authorization and workflows.
- Policy tests for jurisdiction boundaries.
- Unit tests for calculations and validation rules.
- Workflow tests for reporting status transitions.
- Security regression tests for IDOR and unauthorized direct URL access.
- Export/report reconciliation tests once reporting exists.
- Document access tests once protected files exist.

Current baseline:

- Authentication tests exist.
- Application shell tests exist.
- No policy, workflow, or domain calculation tests exist yet because the modules do not exist yet.

## 7. Implementation Dependency Map

Recommended order remains:

1. Phase 0: audit and foundation.
2. Phase 1: application shell and design system. Already implemented.
3. Phase 2: identity, users, roles, permissions and account status.
4. Phase 3: diocesan structure and jurisdiction.
5. Phase 4: school registry and institutional profile.
6. Phase 5: reporting period engine.
7. Phase 6 onward: statistical sections and oversight modules.

Important dependencies:

- Schools depend on diocesan structure.
- Reporting periods depend on schools.
- Statistical sections depend on reporting periods.
- Dashboards and analytics depend on approved/locked returns.
- Documents depend on authorization and protected storage.
- Communications depend on users, roles, scopes and queues.
- Audit should be introduced before high-impact master-data workflows.

## 8. Package Recommendations

No package must be added in Phase 0.

Likely future package candidates, to be evaluated when needed:

- Roles/permissions: a proven Laravel permission package or a custom implementation if jurisdiction rules make package use awkward.
- Excel/CSV import-export: Laravel Excel or native League CSV depending on scope.
- PDF exports: DomPDF, Browsershot, or server-rendered PDF depending on hosting constraints.
- Audit/activity log: package or custom append-only audit depending on legal/audit requirements.

Do not add packages until the phase that needs them.

## 9. Proposed Next Implementation Phase

Because Phase 1 is already complete, the next implementation phase should be Phase 2:

Identity, Users, Roles and Permissions.

Phase 2 should not start business modules. It should first establish account status, role assignment, permission enforcement, jurisdiction-aware authorization foundations, and tests proving unauthorized access is blocked.

## 10. Safe Foundational Changes Made In Phase 0

- Added `config/mdsiss.php` to hold system name, short name, domain map and protected document disk configuration.
- Added this Phase 0 assessment document.

No business module tables, workflows, or permissions were created in Phase 0.

