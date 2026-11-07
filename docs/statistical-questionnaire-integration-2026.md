# Original questionnaire integration

Source: `STATISTICAL FORM for all schools, 2026.pdf`, seven pages, supplied by the user. All seven pages were rendered and visually inspected, and their text was extracted for comparison.

The unmodified source is retained at `resources/forms/statistical-2026-source.pdf`. Its SHA-256 is `07e88ac86a81bd57f9cf8b182626663158d1fae16da1c146a9c6a63d901c90bb` and matches the supplied PDF byte for byte. Access through the application requires permission to view the school return.

The introduction is the first page reached when starting a form. It contains the original Secretariat header, contact line, document title, instructions, and both objectives. The original PDF is available from this page.

`resources/forms/statistical-2026.json` holds the source question labels, numbering, titles, table captions, row and column labels, facility descriptions, printed remarks, staff continuation note, and declaration wording. `statistical-2026-introduction.json` holds the introduction. Original source wording, including the jump from item f) to h), is retained. The questionnaire uses the source label “Reg. Number:” while registration screens retain their separately requested “Registration number” label.

Responsive tables and application navigation remain digital controls. Existing storage keys, data values, validation, autosave, optional deputy registration, and school-level applicability rules are retained. Display labels map the original option wording onto existing stored values so older returns remain compatible.

The source contains no general total column for facilities or staff ages; those additional calculated columns were removed. Original printed notes on desk capacity and library books are shown with the relevant fields. The source’s printed instructions about additional paper are retained as requested.

Regression coverage includes source wording, the introduction flow, protected PDF access, every form section, draft persistence, navigation, and submitted-form review. The introduction paragraph and objectives are also compared with text extracted from the source PDF. Live browser visual verification is not claimed.
