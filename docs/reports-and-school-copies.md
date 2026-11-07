# Reports and school form copies

The Admin Reports menu provides 20 report types with a shared preview and Word, Excel, PDF and CSV exports. Headteachers cannot access the report workspace or diocesan exports. They can download their own saved form through My Submissions, the form editor, or Review and submit.

## Catalogue coverage

| Report | Coverage |
| --- | --- |
| Diocesan annual education report | Registered and assigned schools, reporting coverage, enrolment by parish/level, staffing, religion, examinations, facilities, utilities and staff studies |
| Registered schools directory | Registration number, EMIS, parish, district, level, funding, school gender, residence, operating status and registration date |
| Leadership and contacts | Headteacher, deputies and recorded school telephone/email contacts |
| Submission progress | Assigned-school denominator, submission/approval/outstanding summary and school statuses/dates/correction requests |
| Enrolment | School, class/year, boys, girls, totals and applicable nursery rows |
| Enrolment rankings | All reported schools ranked within their own level |
| Religious affiliation | Learners, teachers and support staff by all four religious categories |
| Academic performance | Separate PLE, UCE scores, UCE student result categories, UACE and vocational results; complete-entry distributions for fixed categories |
| Teaching staff | Every captured teaching-staff field, qualification/payroll/religion/responsibility/gender summaries and age bands |
| Support staff | Every captured support-staff field and summaries |
| Staff study programmes | Staff member, course and institution |
| Facilities and resources | All 20 source items, quantities, remarks and reported-school coverage |
| Water and electricity | All water and power source answers, water reliability and explanations |
| Planning indicators | Paired reported ratios and recorded seating shortages, with methodological limits |
| Year comparison | Matched enrolment, teaching/support staff and classroom figures, plus all 20 facility quantities and changes |
| Announcements | Selected audience, delivery, acknowledgement, awaiting and unreachable totals plus school details |
| Submission review history | Submit, approve and return events, responsible user, date and reason |
| Support requests | School issues, status and recorded responses |
| User accounts | Identity/contact, username, role, assigned school, account status, assignment validity and last login; never password/session/reset secrets |
| Complete school statistical form | All applicable source questions and answers for one selected school, including acknowledgement |

## Filters and interpretation

- Reporting year, parish, district, school level, funding, residence, school gender and individual school; the comparison report also accepts a previous year.
- Statistical data basis: submitted forms (including pending review and approved) or approved forms only. A real submission timestamp is required. Draft and returned answers are excluded from statistical reports.
- Reporting completeness uses schools assigned to the selected collection, counted once. Current registered-school count is shown separately.
- Operational and directory reports state their own basis: current records, publication year, request creation year, or the review collection year. Their data is not restricted to approved forms.
- Category filters use the current registered directory. Historical answers and applicable questionnaire tables use the saved return/profile. The report notes disclose this distinction; original historical directory memberships cannot be reconstructed where they were never recorded.
- Examination year remains the year printed in the source questionnaire. It is not inferred from reporting year. UCE score counts and UCE student counts are separate. Vocational grading systems are not combined into a pass rate.
- Missing information is not converted to zero. Ratios require paired values and a positive denominator. Quantities do not establish condition, compliance or functionality.

## Formats and previews

- Word is a genuine DOCX package with headings, repeated table headers, landscape pages and page numbering. No external Word-generation service is used.
- Excel uses separate worksheets for report information and each table, explicit numeric values for measures, text identifiers, filters, frozen headers, wrapping, row shading and print settings. Leading zeros and formula-like text remain literal.
- PDF is a fixed landscape report with repeated headers and page numbers. User text is escaped; JavaScript and remote resource loading are disabled.
- CSV is retained and protects untrusted formula-like strings.
- Preview shows the first 15 rows of each table with its full row count. Exports contain every row. Word/PDF requests above 5,000 rows are rejected with instructions to use Excel/CSV or narrow filters; no silent truncation occurs. Generation is synchronous and very large exports may require infrastructure tuning.

## Preserved submissions

Migration `2026_11_07_000006_create_submission_versions.php` introduces immutable answer versions. Submission and resubmission capture the answers, school/profile, reporting period, submitter identity, applicable schema and introduction under the return transaction lock. Approval and return actions update review metadata without changing answers. A version belonging to another return cannot be downloaded by changing its ID.

Existing submitted/approved forms are imported as clearly labelled legacy copies. The earlier states of forms already being corrected cannot safely be invented, and are not backfilled. New versions preserve the data from this change onward.

School copies support PDF, Word and Excel. Draft downloads include saved unfinished draft entries, state that they are not submitted, and do not change submission status. Download links inside the form use the existing save-before-navigation behaviour. Submitted copies show reference, version, status, date and submitter. Editing a downloaded file does not update the system record.

Files and export history remain private. Admin exports are restricted to their generating administrator, and school copies require object-level access. Generation/download audit events record user, format and version. No public storage URLs are introduced.

## Verification and deployment

The migration has been applied locally. Deploy the code, run migrations and rebuild production assets together; do not delete the snapshot table or historical exports as part of routine cleanup.

Tests cover all 20 preview types and real DOCX/XLSX packages, numeric zero and text identifiers, formula safety, data basis and assigned-school coverage, PDF download, cross-school/version access, immutable versions, resubmission/approval lifecycle, draft copies and oversize-report handling. A PDF sample was rendered and visually inspected, XLSX was also checked with openpyxl, and DOCX package XML/relationships were parsed. Native Word visual rendering could not be completed: the bundled renderer lacks pdf2image/LibreOffice in this environment, and no Word window was available to the UI tool. A live Office layout check remains advisable. No live browser layout review was possible through the unavailable browser host.

Final automated results: **202 tests passed, 1,877 assertions**. Autosave/navigation checks, production asset build, Blade compilation and Git whitespace checks passed. Changes are local, not pushed or deployed.
