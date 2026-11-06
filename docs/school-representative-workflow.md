# School representative entry workflow

Each representative is assigned directly to one school through `users.school_id`. Assign a school and the School Representative role in Users. Headteacher and school-data-officer accounts also require direct school assignments; parish assignments no longer broaden their access. Existing administrative/coordinator parish access remains unchanged.

Representatives arrive at My School Form from the dashboard. Their portal lists only collection windows assigned to their school and their own submissions. Starting a form first opens profile confirmation. The representative can complete the permitted school particulars, but cannot rename the school. Confirmation records the account, timestamp and an immutable profile snapshot on that return. These confirmed particulars populate the identity section and remain read-only for that representative in that collection.

The ten sections follow the supplied 2026 statistical form. Each section saves independently as a draft. Reviewed non-applicable sections can be saved blank. The final submission action performs completeness checks, verifies profile confirmation and checks that the window is open, including opening/closing or extension dates in Africa/Kampala. Draft preparation is allowed before opening, but closed/locked windows and expired deadlines disallow representative writes. Administrators can reopen a window for corrections; a returned form can then be edited and resubmitted.

Representatives cannot certify, approve, lock, or request corrections. Authorized reviewers perform those actions on the admin side. Submitted/certified/approved/locked forms are read-only for representatives. Server-side policies apply to both direct URLs and POST requests. Selecting another school's return is forbidden, including schools in the same parish.

## Demo setup

Run `php artisan db:seed --class=SchoolRepresentativeDemoSeeder` in local/testing only. The seeder uses two existing DEMO schools, creates representative1@mdsiss.test and representative2@mdsiss.test, and uses the single annual submission window for 2026. Passwords are generated and displayed only when each account is first created. Reruns preserve accounts, passwords, assignments and existing forms. No real accounts are modified.

## Checks

Tests cover same-parish isolation, blocked cross-school reads/writes, profile confirmation before entry, frozen confirmed identity, draft saves, full form submission, admin certification, and closed-window enforcement. The existing application test suite is also retained.
