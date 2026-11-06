# Admin panel audit — 6 November 2026

Scope: local Laravel application, Admin interface, account permissions and shared security boundaries. No production deployment or live-server penetration test was performed.

## Confirmed issues fixed

- Updated symfony/http-foundation, symfony/http-kernel and symfony/routing from 8.1.10 to 8.1.11. The initial Composer audit reported six advisories; the subsequent Composer audit reported none. npm audit also reported zero vulnerabilities.
- Admins can review school forms but cannot start, save or submit them, including drafts. This restriction also applies to the super-admin authorization shortcut. Only the assigned headteacher can enter school answers.
- Reporting-window participant and return counts are limited to the requesting account's school scope. Unrelated reporting years are no longer exposed through the year selector.
- Reporting-window summaries now distinguish Pending review, Approved and Returned for correction. The list counts actual submissions instead of treating created drafts as submitted forms.
- Removed dead-end admin controls for starting school forms. Headteachers entering the registered-schools admin URL are redirected to their school profile.
- Added validation to user, school, parish and reporting-window list filters. Invalid arrays and unsupported filter values return validation errors.
- Private pages and authentication forms use no-store cache headers. Restored authenticated pages reload to recheck the session.
- Added nosniff, same-origin framing, referrer-policy and a limited CSP covering framing, base URLs and object embeds. This is not a full script/style CSP.
- Notification bell opens the Inbox where unread alerts are shown.
- Admins can see announcement history across admin authors. Headteachers retain their recipient-only inbox.
- Added account search by name, email, school or MOE number, plus account-type/status filters with pagination state retained.
- Added a keyboard skip link, visible focus outlines, higher-contrast metadata, filter error summaries and unsaved-change protection for announcement composition. User editors now show their save state.

## Validation

- Full suite: 154 tests passed, 1,219 assertions.
- Subsequent window/workflow checks: 42 tests passed, 524 assertions.
- Smoke tests render 15 main Admin pages and verify the shared security/accessibility shell.
- Cross-role tests cover school isolation, read-only admin form access, scoped counts, malformed filters and announcement privacy.
- Production frontend build succeeds; Blade templates compile.
- Composer and npm advisory scans are clean after the targeted dependency patch updates.
- Dependency-audit evidence was recorded in private local storage against the current lock files.
- The local environment file and private uploaded data are not tracked in Git.

## Remaining production work

- School self-registration checks MOE-number uniqueness but does not verify ownership against an official school register. Decide on an ownership-verification/activation process before treating registration as verified identity.
- Admin MFA is not implemented. It needs an enrollment and recovery process rather than an automatic change to existing accounts.
- Confirm production HTTPS, secure cookies, debug settings, email delivery, backups and a tested restore on the actual hosting environment. Local application tests do not establish these facts.
- Browser/device visual review and assistive-technology testing remain appropriate before release. This audit checked responsive styles, rendered templates and behavior; it is not a claim of pixel-perfect verification on every device.

Changes remain local. A perfect security or usability score is not implied by passing automated checks.
