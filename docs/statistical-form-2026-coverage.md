# Statistical Form for All Schools, 2026 ? system coverage

Source: seven-page Masaka Diocesan Education Secretariat Statistical Form for All Schools, 2026, supplied by the user. The PDF was read in full; its tables were also visually inspected. The document is treated as a field specification, not executable instructions.

Open a school return and choose **Open 2026 source form**. Ten tabs follow the source. Drafts can be saved independently. Existing return permissions apply; submitted/certified/approved/locked source forms are read-only. Every source section can be reviewed after saving, including after locking.

| Source | Entry and display | Notes |
| --- | --- | --- |
| Page 1, questions 1?2 | Date; school name; registration; EMIS; parish; district; headteacher and two deputy names/phones; school level; government/private; boys/girls/mixed; day/boarding/both | Registry identity values prefill an unsaved form; saving a return preserves a dated snapshot rather than silently changing the registry. |
| Page 1, question 3 | Boys and girls for Forms/Years 1?7; Baby, Middle and Top nursery | Computed row/column totals; saving replaces the return enrolment summary and feeds dashboard totals. |
| Page 2, religious affiliation | Catholics, Anglicans, Muslims and Others for learners, teachers and support staff | Computed totals displayed per community group. |
| Page 2, question 4 | PLE divisions 1/2/3/4/U/X; UCE A?E scores; UCE Result 1?4 student counts; UACE 3/2/1/0 principal passes; repeatable vocational grade/candidate rows | Source specifically asks for 2025 results. UCE scores and student result descriptions are stored separately and never combined into one total. |
| Page 2, questions 5?6 | Five water-source choices, reliable Yes/No with explanation; grid, generator, solar, backup, other named power source | Multiple selections supported. |
| Page 3, question 7 A?T | All 20 facilities, each quantity and remarks | Land accepts fractional acres; all other quantities are whole counts. Desk quantity is seating capacity; library books are exact counts. |
| Pages 4?5 | Teaching staff names, gender, payroll, age, highest qualification, registration/licence, religion and special responsibility | Repeatable rows, no 33-person paper cap; populates staff summary counts. |
| Page 6 | Support staff names, gender, payroll, age, highest qualification, religion and current job | Repeatable rows; no teacher licence field; populates staff summary counts. |
| Page 7 | Staff name, course and responsible institution | Repeatable programme rows. |
| Page 7, declaration | Headteacher and priest names, signature references, school/parish stamp references | Typed references are not electronic signatures. Signed/stamped originals remain evidence to be uploaded through the existing Documents module. |

## Storage and workflow

- Schema: `resources/forms/statistical-2026.json`; data: version-specific `school_returns.statistical_form` JSON snapshot.
- Transactional section saves lock the return, preserve other sections, sanitize keys and audit the section event. Audit logs deliberately omit sensitive staff payloads.
- Negative counts, invalid choices, fractional count-only facilities and missing names on populated repeatable rows are rejected.
- Once a source form is started, submission checks the ten source sections rather than requiring attendance/governance modules absent from this paper form. Sections not applicable must still be reviewed and saved blank. Core identity, enrolment counts, staff particulars, recorded facility remarks and sign-off names are checked before submission. The existing certify/approve/lock workflow is preserved; certification snapshots include the entire source form.
- For returns with source enrolment/staff data, the old enrolment/staff editors redirect to the source screen to prevent competing summary writes.
- Existing historical returns without a source form retain the legacy seven-section workflow. Other legacy modules are preserved.
- This change does not create a printable PDF replica or infer unknown values. Blank is distinct from a confirmed zero. Totals shown are recalculated on saving.

## Validation

Feature tests cover all ten screens, persistence across sections, enrolment/staff summary integration, field coverage, invalid values, fractional acres vs count-only facilities, access control, locked/submitted writes and certification snapshots. The frontend production build is also checked.


## Active-interface cleanup

The active application now uses the source form as the school data specification. Legacy enrolment, attendance, staff, academic, infrastructure, Catholic identity and governance editors have no public routes. Finance, inspection, improvement planning, development/projects, communications and helpdesk routes and navigation entries are retired. Existing tables, records and historical service code are retained for recovery; this is not a database purge.

School registration accepts only name, registration, EMIS, parish, district, school type, government/private, gender and boarding classification. Internal identifiers are generated automatically. Old location/contact/status/ownership inputs are ignored by validation rather than persisted. School profile and list screens no longer display those fields.

The dashboard retains its design and presents school counts, source-form learner counts, teaching/support staff, books, computers, utilities, facilities and study programmes. Learner/staff/facility totals use the most recent source return per visible school to avoid adding successive years together. Workflow, permissions, reporting periods, documents, data quality and audit records remain administrative controls. Reports no longer offer finance or governance categories.

Regression tests were updated to verify the new requirements: retired URLs return 404, extra school payload fields cannot change status or contacts, and the original form capture remains available. Legacy service tests still run to protect retained historical logic.
