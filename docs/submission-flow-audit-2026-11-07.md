# Submission flow audit ? 7 November 2026

Scope: school start/save/review/submit, admin incoming queue and review, correction feedback, resubmission, immutable copies, window participation and access boundaries. Changes are local; production was not inspected or deployed.

## Fixes

- Submission and resubmission create an admin inbox/bell notification and a school receipt inside the same transaction as the state change, version capture and audit event. Deterministic keys avoid duplicate notices. Feedback goes to the current school headteacher account, never an old submitter reassigned elsewhere.
- Admin approval/return posts carry a signed version fingerprint, rechecked under the return lock. A stale review page cannot act on a newer submission.
- Admin pages and downloads show the last submitted version while a school edits returned corrections. Unsent autosave sidecars are excluded too. Admin direct access to unsubmitted drafts is forbidden; school update permissions remain separate.
- Retried Submit requests for an already received form return its receipt without creating another version or notification. Final submission still requires acknowledgement, unchanged reviewed answers, valid sections, an assigned school and an available submission window (returned corrections retain the existing deadline exception).
- Window edits can add newly registered schools. Submitted schools cannot be removed from that window. Assignment changes are audited. Start and submit re-read locked window records.
- Legacy not-started records become drafts through Start form without a duplicate return. Mobile and desktop saved-section counts now match the eight data-entry pages; the school status filter includes Approved.

## Verification

Automated PHP feature/unit suite, autosave/navigation JavaScript checks, production asset build and Blade compilation. Regression coverage includes submit ? notification ? correction ? private edits ? resubmit ? stale review rejection ? approval, repeated submission, window assignment protection, start/resume and role boundaries. The end-to-end regression uses real HTTP save/review endpoints with a prefilled valid initial draft.

Local database read-only checks: 37 returns (5 draft, 31 locked, 1 returned), no orphaned returns, no duplicate school/window groups, no submitted states missing submission timestamps, one active admin. One historical returned form lacks a retained submission version. Its original answers cannot be reconstructed reliably; admin sees an explicit unavailable-copy notice until the school resubmits. Current corrections remain intact.

No live browser or production database verification was performed. Notifications here are in-app inbox/bell records; no email/SMS delivery is implied.
