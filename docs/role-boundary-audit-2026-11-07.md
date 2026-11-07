# User role audit - 7 November 2026

## Supported account types

The application supports two account types: Admin (`system-super-administrator`) and School Headteacher (`headteacher`). A valid Admin has exactly one Admin role and no school assignment. A valid School Headteacher has exactly one Headteacher role and one school assignment. Create and edit validation enforces one role, requires a school for Headteachers, prohibits school assignment for Admins, and prevents a second account for a school.

Local database inventory: **6 accounts: 1 Admin and 5 School Headteachers.** No mixed roles, unsupported roles, school-assigned Admins, or unassigned Headteachers were found. This does not establish the production database state.

## Access matrix

| Capability | Admin | School Headteacher |
| --- | --- | --- |
| Diocesan dashboard and comparisons | Allowed | Blocked; school dashboard only |
| Parish directory and management | Allowed | Blocked |
| Registered schools directory and administrative school pages | Allowed | Blocked |
| School profile | Manage registered schools | Own school through the school portal; identifiers remain protected |
| Submission window administration | Allowed | Blocked, including direct URLs |
| Start, save, continue or submit annual form | Blocked | Own assigned school and permitted window/status only |
| Submitted answers | Read-only | Own forms only; locked after submission |
| Approve or return a submission | Allowed | Blocked |
| Publish announcements or message schools | Allowed | Blocked |
| Read and acknowledge announcements/messages | Own authorised inbox | Own school/account only |
| Support tickets | Review/reply | Open and read own tickets |
| Users, roles, settings, audit, exports and system logs | Allowed | Blocked |
| Password and own account profile | Own account | Own account |
| Protected school documents | Administrative access | Permitted documents within own school scope; no upload/management |

## Defects repaired

1. **Role classification depended on permissions.** A stray all-schools permission could make a headteacher cease to be recognised as a school user. Account classification now depends on role and school assignment, independently of permission grants.
2. **Raw Admin role membership granted the global Gate bypass.** It now requires the valid, single-role, unassigned Admin account type. A mixed-role or school-assigned Admin cannot inherit the bypass.
3. **Headteacher permissions could expand through database grants.** A central permission ceiling allows only `dashboard.view`, `schools.view.assigned`, `returns.view`, `returns.manage.assigned` and `documents.view`. Unsupported role permissions confer no privileged access. Both seeders use the same definition.
4. **Legacy jurisdiction roles retained school visibility code paths.** School policies and query scopes now recognise only valid Admin access or the assigned school's Headteacher access. Unsupported roles receive no school records.
5. **Administrative school URLs could render school records inside the wrong interface.** The directory resource is now protected by the Admin middleware. School submission and dashboard links point to school profile or form routes instead.
6. **Malformed role assignments could still open shared portal pages.** Portal middleware blocks invalid role/school combinations. Own-account profile/password pages remain available for account recovery; they confer no administrative or school-record access.
7. **Retired scoped publishing and management assumptions remained in controllers and tests.** Announcement publishing, school messaging and support management now explicitly require Admin identity. Retired-role regression tests now expect denial rather than treating those roles as supported.
8. **Administrator safety checks relied on raw role grants.** Self-demotion protection now recognises only the actual Admin role, and administrator counts used by account protection/readiness exclude malformed mixed or school-assigned accounts.
9. **Admin dashboard lacked an explicit dashboard permission check.** It now checks permission in addition to redirecting Headteachers to the school dashboard.

The earlier corrective change removing the school submissions admin toolbar and window-view permission remains in place. Window settings have an Admin route guard even if an old account retains a stale permission.

## Verification

- New role-boundary tests cover a Headteacher role polluted with every permission, mixed Headteacher/Admin assignments, unsupported legacy roles, cross-school record denial, admin interface denial, valid admin management, and denial of admin form editing.
- Existing creation/editing, last-admin protection, school account uniqueness, session revocation, announcement delivery and form workflow tests remain.
- The local inventory contains only the two supported role types and valid assignments.
- Final full suite: **195 tests passed, 1,647 assertions**.
- Assignment safety checks after final cleanup: **17 tests passed, 142 assertions**.
- Controller boundary checks: **18 tests passed, 190 assertions**.
- Autosave/navigation JavaScript checks, Blade compilation and Git whitespace checks passed.

No roles or accounts were silently deleted or reassigned. These changes are local and have not been deployed. The shared default school-password and ownership-verification decisions recorded in the client portal audit remain outside this role separation fix.
