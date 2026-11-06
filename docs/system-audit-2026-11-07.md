# System audit — 7 November 2026

This audit reviewed the current local Admin and School Headteacher implementation: authentication, registration, account changes, permissions, submission review, announcements, exports, documents, and existing responsive interfaces. Confirmed defects were repaired locally. This is not a certification of the live production server.

## Repairs

- Added versioned session revocation. Password resets and administrative security changes invalidate existing authenticated sessions even when the session driver is not database-backed. The current session remains valid after an intentional password change.
- Password reset processing now locks the user and rechecks and consumes the token under that lock, preventing concurrent reuse. Reset password validation is consistent across account types.
- Reassigning a Headteacher account to another school updates its username to the new school's MOE registration number and revokes its previous sessions.
- Headteachers cannot replace established MOE or EMIS identifiers through profile requests. Existing identifiers display as read-only; administrative corrections remain available. Legacy missing identifiers can still be supplied.
- Fresh database seeding now runs username generation events, so the seeded administrator has a usable username.
- Malformed announcement preview tokens produce validation errors rather than server errors.
- Statistical exports and report totals now retain approved submissions. Draft and returned forms remain excluded, and exports identify the review status.
- Missing private document files return a controlled 404 response.
- Password-change and reset session cleanup respects the configured session database connection and table.

## Verification

- Full automated suite: **166 tests passed, 1,330 assertions**.
- Focused account-settings and system-integrity checks repeated after final refinements.
- Production assets built successfully with Vite.
- Blade templates compiled successfully.
- Composer platform requirements passed; Composer and npm audit scans reported zero known advisories.
- Session-revocation migration applied to the local database.
- Git whitespace checks passed (line-ending normalization warnings only).

Automated coverage includes permission boundaries, school registration, account uniqueness, submissions and review, announcements, and the regression cases above. No real-device visual review, assistive-technology audit, external penetration test, or production-server inspection was completed in this pass.

## Remaining decisions and deployment requirements

1. **Shared default password:** new school accounts retain `mdes123` and password changes remain optional, following the requested policy. This creates a predictable-credential risk. Unique initial credentials or verified activation would materially improve security.
2. **School ownership:** uniqueness of a MOE number prevents duplicate registration but does not prove that the registrant is authorised to represent the school. Verified activation needs an operational policy.
3. **Legacy records:** the local database contains 11 schools without a MOE number and 11 without an EMIS code. These values must be reconciled with official records; they cannot safely be invented. These counts do not establish the state of production data.
4. **Production configuration:** the local readiness checks flag the development environment, debug mode, HTTP application URL, cookie configuration, email transport, demo identities, default privileged credentials, and missing recent restore evidence. Verify these on the actual deployment; local development settings were not changed to simulate production readiness.
5. **Operations:** verify live HTTPS, secure cookies, real email delivery and recovery, removal of demo accounts, unique administrator credentials, backup restoration, and monitoring before declaring production readiness.

All code changes described here are in the local workspace. They have not been pushed or deployed by this audit. The system is improved and its tests pass, but the retained credential policy and unverified production controls prevent an honest “10/10” security claim.
