# System audit ? 3 November 2026

## Scope and result
Reviewed annual submission rules, representative isolation, profile editing, source-form persistence, alerts/support, shared frontend behavior, database access patterns and the build. Fixes are implemented; this is a code and regression audit, not a claim of perfect quality or a production penetration/load test.

## Changes
- Submission and review requests use database transactions and row locks, recheck authorization against locked records and record status changes in the audit log.
- Submission locks the school and return in a consistent order. Profile confirmation follows the same order. Form saves recheck confirmation after locking and merge the current confirmed identity snapshot, rejecting stale profile saves.
- Annual window creation and participation changes are atomic. A database uniqueness race returns a validation error rather than a server error.
- Administrators can edit an existing window schedule/deadline/status without changing its reporting year. Changes are audited and immediately drive submission eligibility, notices and countdowns.
- Repeated permission checks reuse loaded roles and permissions. Recipient queries eager-load role permissions. Schema parsing is reused within each request. Announcement selection filters dates in SQL and loads only necessary return columns.
- Legacy notification and helpdesk listings respect recipient/requester privacy. Feedback/support writes have rate limits.
- Submitted dashboard links open the complete submitted-data table. Unneeded chart calculations were removed.
- Dirty-form protection no longer recreates removed status text. Submitting with unsaved section changes is blocked. Cloned entry rows discard old validation errors.
- Removed unused Instrument Sans loading and missing-font declarations; Segoe UI remains. Removed obsolete selectors for deleted profile banners, guidance and old section controls. Tailwind scans source templates instead of compiled/inlined templates.
- Updated touched backend classes with Laravel Pint and corrected outdated representative-workflow documentation.

## Validation
Full application feature/unit suite, frontend dirty-form tests, production asset build, and PHP syntax checks of all compiled Blade templates. Added regression coverage for schedule controls, repeated permission-query behavior and legacy notification privacy. Existing tests cover representative isolation, annual uniqueness, acknowledgement, immutable submitted data, school feedback and support.

## Remaining limits and follow-up
- No interactive browser visual/accessibility audit or realistic concurrent-user load test was performed. A passing build does not prove pixel alignment or a throughput target.
- Shared styles still contain historical overrides; a broader component-by-component CSS refactor should be visually verified before replacing them.
- Automated reminder delivery and broadcast notifications for deadline changes are not implemented. Current notices/countdowns read the live window schedule; school feedback and support responses populate Alerts.
- WhatsApp support prepares a message; delivery requires the user to send it in WhatsApp.
- Archived duplicate demo drafts are preserved. Submitted demo timestamps are synthetic. Dependency vulnerability scanning, infrastructure/backups, production configuration, and recovery drills remain deployment checks.
