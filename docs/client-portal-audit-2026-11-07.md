# School portal audit - 7 November 2026

## Scope and outcome

Reviewed the local School Headteacher portal: registration and login, recovery and password changes, school profile and confirmation, dashboard and header, form navigation and autosave, full-form review and submission, private announcements and support, and object-level access boundaries. Confirmed defects were fixed locally. This is not a certification of the production deployment or a claim that all possible vulnerabilities have been eliminated.

## Fixes in this audit

- Bound the acknowledgement to the exact reviewed form with a server-generated HMAC fingerprint. A change to saved answers, draft revisions, profile snapshot or status invalidates an earlier review. Submission checks this under the return lock; a stale review produces a clear message and remains a draft.
- Revalidated saved sections against their field schema during final completeness checks, including numeric bounds and allowed options. Academic tables inappropriate for the confirmed school category are rejected. A No answer to reliable water supply now requires an explanation before submission; incomplete drafts remain saveable.
- Used the confirmed school-level snapshot when deciding which enrolment and examination tables belong to a return. Historical submissions no longer change their applicable tables when the current school profile changes. Older submitted returns can fall back to their saved identity classification.
- Restricted profile parish changes to active Masaka Diocese parishes, while retaining the current legacy selection. Inactive or unrelated new selections cannot bypass the dropdown through a crafted request.
- Rechecked immutable Registration number and EMIS values after acquiring the school lock, so an overlapping profile request cannot overwrite an administrative identifier correction using stale validation.
- Corrected progress totals to count the eight data-entry pages and exclude the final review action. Saving all data pages can now reach 100% without saving the removed signature section.
- Corrected inactive-session errors to use username, matching the login form.
- Bounded current and replacement password lengths before password verification and hashing.
- Limited school-header unread counts to notifications belonging to the current school or account-wide notices, consistent with inbox access checks.
- Assigned unique control IDs to newly added staff/programme rows and added browser-side numeric maximums consistent with server validation.
- Removed obsolete declaration-rendering branches and superseded two-row school-header CSS. Retained the requested single-row mobile header and the existing logo. Added reduced-motion handling for the window ticker.

## Verified protections retained

- School-level object authorization on forms, source questionnaires, profile data, notifications and documents; headteachers cannot access other schools or administrative publishing/review actions.
- Server-side registration uniqueness and one account per school; username-only authentication, login throttling, inactive-account rejection, session revocation, CSRF middleware and escaped user-generated content.
- Draft revision conflict protection, incomplete-entry preservation, save-before-navigation, disabled edits to submitted forms, duplicate annual-submission prevention, required acknowledgement, and admin return-for-correction/resubmission.
- Targeted announcement delivery and acknowledgement ownership, private support tickets and school message inboxes.

## Validation

- Baseline full suite: 185 tests passed, 1,579 assertions.
- Focused audit regression run: 58 tests passed, 628 assertions.
- New regression cases cover stale review rejection, final validation of invalid saved values, and historical school-level preservation.
- JavaScript autosave/navigation checks passed.
- Production asset build and Blade view compilation passed.
- Fresh Composer audit: zero known advisories and zero abandoned packages.
- Fresh npm audit: zero known vulnerabilities.
- Final full suite: **189 tests passed, 1,595 assertions**.
- Additional profile-scope regression run: 9 tests passed, 96 assertions.
- Git whitespace checks passed.

Browser access host was unavailable. Rendered response tests and CSS review were completed, but live desktop/mobile visual inspection, screen-reader testing, production infrastructure review and an external penetration test were not completed.

## Remaining security decisions and operational checks

1. New schools still receive the shared default password specified by the owner, and changing it remains optional. That policy leaves a predictable-credential risk; this audit has not silently changed it.
2. Unique Registration numbers prevent duplicates but do not establish that a self-registering person is authorised to represent a school. Ownership verification still needs an operational policy.
3. Production HTTPS, cookie settings, actual recovery-email delivery, privileged/demo accounts, backup restoration and monitoring must be verified on the actual deployment. Local development settings were not replaced with production values, and no secrets were changed or printed.
4. The stylesheet still contains older responsive overrides outside the repaired scope. A broad deletion without browser coverage could cause regressions; no claim is made that every unused CSS selector has been eliminated.

All work is in the local workspace; nothing was pushed or deployed during this audit.

## Security references

The server-side authorization and stale-review safeguards follow the principles in the [OWASP Authorization Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html) and [OWASP Transaction Authorization Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Transaction_Authorization_Cheat_Sheet.html). These references guide the checks; they are not certification of this application.
