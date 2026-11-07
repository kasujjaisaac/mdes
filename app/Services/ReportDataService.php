<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\AuditEvent;
use App\Models\District;
use App\Models\HelpdeskTicket;
use App\Models\Parish;
use App\Models\ReportingPeriod;
use App\Models\School;
use App\Models\SchoolReturn;
use App\Models\User;
use App\Support\Reports\ReportCatalog;
use App\Support\StatisticalForm;
use Illuminate\Support\Collection;

class ReportDataService
{
    public function schools(User $user, array $filters)
    {
        return School::visibleTo($user)->with('parish', 'district', 'contact', 'currentHeadteacher', 'currentFirstDeputy', 'currentSecondDeputy')
            ->when($filters['school_id'] ?? null, fn ($q, $v) => $q->whereKey($v))
            ->when($filters['parish_id'] ?? null, fn ($q, $v) => $q->where('parish_id', $v))
            ->when($filters['district_id'] ?? null, fn ($q, $v) => $q->where('district_id', $v))
            ->when($filters['level'] ?? null, fn ($q, $v) => $q->where('school_level', $v))
            ->when($filters['funding'] ?? null, fn ($q, $v) => $q->where('funding_classification', $v))
            ->when($filters['residence'] ?? null, fn ($q, $v) => $q->where('boarding_status', $v))
            ->when($filters['gender'] ?? null, fn ($q, $v) => $q->where('gender_classification', $v))->orderBy('official_name');
    }

    private function returns(Collection $schools, int $year, string $basis): Collection
    {
        return SchoolReturn::whereNotNull('submitted_at')->whereIn('school_id', $schools->pluck('id'))
            ->whereIn('status', $basis === 'approved' ? ['approved'] : SchoolReturn::SUBMITTED_STATUSES)
            ->whereHas('reportingPeriod', fn ($q) => $q->where('year', $year)->where('is_archived', false))
            ->with('school.parish', 'school.district', 'reportingPeriod')->latest('submitted_at')->orderByDesc('id')->get()->unique('school_id')->values();
    }

    private function table(string $name, array $headers, array $rows, string $note = ''): array
    {
        return ['name' => $name, 'headers' => array_values($headers), 'rows' => array_map('array_values', array_values($rows)), 'note' => $note];
    }

    private function countValue($value): int|float|null
    {
        return is_numeric($value) ? 0 + $value : null;
    }

    private function sumRows(array $rows): ?int
    {
        if (! $rows) {
            return null;
        }
        foreach ($rows as $row) {
            if (! isset($row['boys'],$row['girls'])) {
                return null;
            }
        }

        return array_sum(array_map(fn ($r) => (int) $r['boys'] + (int) $r['girls'], $rows));
    }

    private function learners(SchoolReturn $r): ?int
    {
        $data = $r->statistical_form['enrolment'] ?? [];

        return $this->sumRows(array_merge(array_values($data['classes'] ?? []), StatisticalForm::allowsNursery($r) ? array_values($data['nursery'] ?? []) : []));
    }

    private function prefix(SchoolReturn $r): array
    {
        return [$r->statistical_form['identity']['school_name'] ?? $r->school->official_name,
            $r->profile_snapshot['registration_number'] ?? $r->school->ministry_registration_number, $r->reportingPeriod->year, $r->statusLabel()];
    }

    private function number(SchoolReturn $r, string $item): int|float|null
    {
        return $this->countValue($r->statistical_form['facilities']['items'][$item]['quantity'] ?? null);
    }

    private function teacherCount(SchoolReturn $r): ?int
    {
        return array_key_exists('teaching', $r->statistical_form ?? []) ? count($r->statistical_form['teaching']['members'] ?? []) : null;
    }

    private function ratio($numerator, $denominator): ?float
    {
        return $numerator !== null && $denominator !== null && $denominator > 0 ? round($numerator / $denominator, 2) : null;
    }

    public function build(User $user, string $type, array $filters = []): array
    {
        abort_unless($user->isAdministrator(), 403);
        abort_unless(isset(ReportCatalog::TYPES[$type]), 422);
        $filters = array_filter($filters, fn ($v) => $v !== null && $v !== '');
        $year = (int) ($filters['year'] ?? now('Africa/Kampala')->year);
        $basis = $filters['basis'] ?? 'submitted';
        $schools = $this->schools($user, $filters)->get();
        $returns = $this->returns($schools, $year, $basis);
        $expected = ReportingPeriod::where('year', $year)->where('is_archived', false)->with(['schools' => fn ($q) => $q->whereIn('schools.id', $schools->pluck('id'))->with('parish', 'district')])->get()->flatMap->schools->unique('id')->values();
        $meta = ['Institution' => 'Masaka Diocesan Education Secretariat', 'Reporting year' => in_array($type, ['school registry', 'leadership', 'user accounts']) ? 'Current records' : (string) $year, 'Data basis' => $basis === 'approved' ? 'Approved forms only' : 'Submitted forms including pending review and approved',
            'Registered schools matching filters' => (string) $schools->count(), 'Assigned schools for this collection' => (string) $expected->count(), 'Schools supplying statistical data' => (string) $returns->count(),
            'Generated' => now('Africa/Kampala')->format('d F Y H:i').' EAT'];
        foreach (['parish_id', 'district_id', 'school_id', 'level', 'funding', 'residence', 'gender', 'comparison_year'] as $key) {
            if (isset($filters[$key])) {
                $value = $filters[$key];
                if ($key === 'parish_id') {
                    $value = Parish::find($value)?->name;
                }
                if ($key === 'district_id') {
                    $value = District::find($value)?->name;
                }
                if ($key === 'school_id') {
                    $value = School::find($value)?->official_name;
                }
                $meta[ucwords(str_replace('_', ' ', $key))] = (string) $value;
            }
        }
        $meta['Data basis'] = match ($type) {
            'school registry','leadership','user accounts' => 'Current registered records, independent of submission status',
            'submission progress' => 'All assigned schools and current submission statuses',
            'announcements' => 'Announcements published in the selected year',
            'review history' => 'All review events for the selected reporting year',
            'support requests' => 'School support requests created in the selected year',
            default => $meta['Data basis'],
        };
        $notes = ['Blank values are not reported, not zero. School/category filters use the current registered directory; saved answers and applicable tables use the return snapshot.',
            'Statistical totals exclude drafts and forms returned for correction. Directory, reporting progress, communications and account reports describe their own records.'];
        $tables = [];
        $base = ['School', 'Registration number', 'Reporting year', 'Review status'];
        switch ($type) {
            case 'school registry':
                $tables[] = $this->table('Registered schools', ['School', 'Registration number', 'EMIS code', 'Parish', 'District', 'Level', 'Funding', 'School gender', 'Residence', 'Status', 'Registration date'],
                    $schools->map(fn ($s) => [$s->official_name, $s->ministry_registration_number, $s->emis_number, $s->parish?->name, $s->district?->name, $s->school_level, $s->funding_classification, $s->gender_classification, $s->boarding_status, $s->operational_status, $s->registration_date?->format('Y-m-d')])->all(), 'Current school directory, independent of form submissions.');
                break;
            case 'leadership':
                $tables[] = $this->table('Leadership and contacts', ['School', 'Registration number', 'Headteacher', 'Telephone', 'Email', 'First deputy', 'First deputy telephone', 'Second deputy', 'Second deputy telephone', 'School telephone', 'School email'],
                    $schools->map(fn ($s) => [$s->official_name, $s->ministry_registration_number, $s->currentHeadteacher?->person_name, $s->currentHeadteacher?->telephone, $s->currentHeadteacher?->email, $s->currentFirstDeputy?->person_name, $s->currentFirstDeputy?->telephone, $s->currentSecondDeputy?->person_name, $s->currentSecondDeputy?->telephone, $s->contact?->telephone, $s->contact?->email])->all(), 'Current registered contact details.');
                break;
            case 'submission progress':
                $all = SchoolReturn::whereIn('school_id', $expected->pluck('id'))->whereHas('reportingPeriod', fn ($q) => $q->where('year', $year)->where('is_archived', false))->latest('updated_at')->get()->unique('school_id')->keyBy('school_id');
                $rows = $expected->map(function ($s) use ($all) {
                    $r = $all->get($s->id);

                    return [$s->official_name, $s->ministry_registration_number, $s->parish?->name, $r?->statusLabel() ?? 'Not started', $r?->submitted_at?->timezone('Africa/Kampala')->format('Y-m-d H:i'), $r?->approved_at?->timezone('Africa/Kampala')->format('Y-m-d H:i'), $r?->correction_reason];
                })->all();
                $submitted = $all->filter(fn ($r) => $r->submitted_at && in_array($r->status, SchoolReturn::SUBMITTED_STATUSES, true))->count();
                $pending = $all->filter(fn ($r) => $r->submitted_at && in_array($r->status, SchoolReturn::PENDING_REVIEW_STATUSES, true))->count();
                $summary = [];
                foreach (['Assigned schools' => $expected->count(), 'Submitted or approved' => $submitted, 'Outstanding' => $expected->count() - $submitted, 'Pending review' => $pending, 'Approved' => $all->where('status', 'approved')->count(), 'Returned for correction' => $all->where('status', 'returned_for_correction')->count(), 'Drafts' => $all->where('status', 'draft')->count(), 'Not started' => $expected->count() - $all->where('status', '!=', 'not_started')->count()] as $label => $count) {
                    $summary[] = [$label, $count, $expected->count() ? round($count * 100 / $expected->count(), 1) : null];
                }
                $tables[] = $this->table('Reporting progress summary', ['Measure', 'Schools', 'Percent of assigned schools'], $summary, 'Forms without a submission timestamp remain outstanding.');
                $tables[] = $this->table('Submission progress', ['School', 'Registration number', 'Parish', 'Status', 'Submitted', 'Approved', 'Correction requested'], $rows, 'Denominator: schools assigned to the selected year collection, counted once. Draft and returned forms remain outstanding.');
                break;
            case 'enrolment':
                $rows = [];
                foreach ($returns as $r) {
                    foreach (['classes', 'nursery'] as $group) {
                        if ($group === 'nursery' && ! StatisticalForm::allowsNursery($r)) {
                            continue;
                        }
                        foreach ($r->statistical_form['enrolment'][$group] ?? [] as $key => $row) {
                            $rows[] = [...$this->prefix($r), $r->profile_snapshot['parish'] ?? $r->school->parish?->name, StatisticalForm::schoolLevel($r), StatisticalForm::sections()['enrolment']['tables'][$group]['rows'][$key] ?? $key, $this->countValue($row['boys'] ?? null), $this->countValue($row['girls'] ?? null), isset($row['boys'],$row['girls']) ? (int) $row['boys'] + (int) $row['girls'] : null];
                        }
                    }
                }
                $tables[] = $this->table('Enrolment', [...$base, 'Parish', 'School level', 'Class or year', 'Boys', 'Girls', 'Total'], $rows, 'Nursery rows are included once in a school total, not added again as a separate school.');
                break;
            case 'enrolment rankings':
                $rows = $returns->map(fn ($r) => [...$this->prefix($r), StatisticalForm::schoolLevel($r), $this->learners($r)])->filter(fn ($r) => $r[5] !== null)->groupBy(fn ($r) => $r[4])->sortKeys()->flatMap(fn ($group) => $group->sortByDesc(fn ($r) => $r[5])->values()->map(fn ($r, $i) => [$i + 1, ...$r]))->values()->all();
                $tables[] = $this->table('Enrolment rankings', ['Rank within level', ...$base, 'Level', 'Learners'], $rows, 'All reported schools ranked within their own level. No cross-level performance ranking is implied.');
                break;
            case 'staff': case 'support staff':
                $section = $type === 'staff' ? 'teaching' : 'support';
                $schema = StatisticalForm::sections()[$section]['tables']['members'];
                $rows = [];
                $groups = [];
                foreach ($returns as $r) {
                    foreach ($r->statistical_form[$section]['members'] ?? [] as $row) {
                        $values = [];
                        foreach ($schema['columns'] as $key => $field) {
                            $values[] = $key === 'age' ? $this->countValue($row[$key] ?? null) : ($row[$key] ?? null);
                        }
                        $rows[] = [...$this->prefix($r), ...$values];
                        if (is_numeric($row['age'] ?? null)) {
                            $band = (int) floor($row['age'] / 10) * 10;
                            $ageLabel = $band.'-'.($band + 9);
                        } else {
                            $ageLabel = 'Not reported';
                        }
                        $groups['age band'][$ageLabel] = ($groups['age band'][$ageLabel] ?? 0) + 1;
                        foreach (['gender', 'payroll', 'qualification', 'religion', 'responsibility'] as $key) {
                            $value = $row[$key] ?? 'Not reported';
                            $groups[$key][$value] = ($groups[$key][$value] ?? 0) + 1;
                        }
                    }
                }
                $tables[] = $this->table('Staff details', [...$base, ...array_map(fn ($c) => $c['display_label'] ?? $c['label'], $schema['columns'])], $rows, 'Staff entries are records, not unique people across schools. Registration or licence numbers are reported values, not independently verified.');
                $summary = [];
                foreach ($groups as $key => $values) {
                    foreach ($values as $label => $count) {
                        $summary[] = [ucfirst($key), $label, $count];
                    }
                }
                $tables[] = $this->table('Staff summary', ['Measure', 'Category', 'Recorded staff'], $summary);
                break;
            case 'religious affiliation':
                $rows = [];
                $schema = StatisticalForm::sections()['religion']['tables']['community'];
                foreach ($returns as $r) {
                    foreach ($schema['rows'] as $key => $label) {
                        $row = $r->statistical_form['religion']['community'][$key] ?? [];
                        $values = [];
                        foreach ($schema['columns'] as $col => $field) {
                            $values[] = $this->countValue($row[$col] ?? null);
                        }
                        $rows[] = [...$this->prefix($r), $label, ...$values, ! in_array(null, $values, true) ? array_sum($values) : null];
                    }
                }
                $tables[] = $this->table('Religious affiliation', [...$base, 'Community group', ...array_map(fn ($c) => $c['label'], $schema['columns']), 'Total'], $rows);
                break;
            case 'examinations':
                foreach (StatisticalForm::sections()['academic']['tables'] as $key => $table) {
                    $rows = [];
                    foreach ($returns as $r) {
                        if (! in_array($key, StatisticalForm::academicTables($r), true)) {
                            continue;
                        }
                        $data = $r->statistical_form['academic'][$key] ?? [];
                        foreach ($table['rows'] ?? array_fill_keys(array_keys($data), '') as $rowKey => $label) {
                            $row = $data[$rowKey] ?? [];
                            $values = [];
                            foreach ($table['columns'] as $col => $field) {
                                $values[] = $field['type'] === 'integer' ? $this->countValue($row[$col] ?? null) : ($row[$col] ?? null);
                            }
                            $rows[] = [...$this->prefix($r), $label !== '' ? (string) $label : (is_numeric($rowKey) ? $rowKey + 1 : $rowKey), ...$values];
                        }
                    }
                    if (isset($table['rows'])) {
                        $eligible = $returns->filter(fn ($r) => in_array($key, StatisticalForm::academicTables($r), true));
                        $complete = $eligible->filter(function ($r) use ($key, $table) {
                            foreach ($table['rows'] as $rk => $label) {
                                foreach ($table['columns'] as $column => $field) {
                                    if (! is_numeric($r->statistical_form['academic'][$key][$rk][$column] ?? null)) {
                                        return false;
                                    }
                                }
                            }

                            return true;
                        });
                        $summary = [];
                        foreach ($table['rows'] as $rk => $label) {
                            $column = array_key_first($table['columns']);
                            $summary[] = [$label, $complete->count() ? $complete->sum(fn ($r) => (int) $r->statistical_form['academic'][$key][$rk][$column]) : null, $complete->count(), $eligible->count()];
                        }
                        $tables[] = $this->table($table['label'].' distribution', ['Category', 'Recorded count', 'Schools with complete entries', 'Applicable schools with returns'], $summary, 'Only complete category entries are combined. Zero candidates does not mean poor performance. UCE score counts are not candidate counts.');
                    }
                    $tables[] = $this->table($table['label'], [...$base, 'Category', ...array_map(fn ($c) => $c['label'], $table['columns'])], $rows, 'Examination year is the year stated in the questionnaire title. UCE scores and student result categories are separate measures; vocational grading systems are not combined.');
                } break;
            case 'staff study programmes':
                $rows = [];
                foreach ($returns as $r) {
                    foreach ($r->statistical_form['studies']['programmes'] ?? [] as $row) {
                        $rows[] = [...$this->prefix($r), $row['name'] ?? null, $row['course'] ?? null, $row['institution'] ?? null];
                    }
                }
                $tables[] = $this->table('Staff study programmes', [...$base, 'Staff member', 'Course', 'Institution'], $rows);
                break;
            case 'infrastructure':
                $rows = [];
                $summary = [];
                foreach (StatisticalForm::sections()['facilities']['tables']['items']['rows'] as $key => $label) {
                    $sum = 0;
                    $reported = 0;
                    foreach ($returns as $r) {
                        $row = $r->statistical_form['facilities']['items'][$key] ?? [];
                        $qty = $this->countValue($row['quantity'] ?? null);
                        if ($qty !== null) {
                            $sum += $qty;
                            $reported++;
                        }
                        $rows[] = [...$this->prefix($r), $label, $qty, $row['remarks'] ?? null];
                    }
                    $summary[] = [$label, $reported ? $sum : null, $reported, $expected->count()];
                }
                $tables[] = $this->table('Facility summary', ['Facility', 'Recorded quantity', 'Schools reporting quantity', 'Assigned schools'], $summary, 'Rows use different units; do not add land, rooms and equipment into a single total.');
                $tables[] = $this->table('Facility details', [...$base, 'Facility', 'Quantity', 'Remarks'], $rows, 'Quantities do not establish condition, capacity or functionality. All 20 questionnaire items and their remarks are included.');
                break;
            case 'utilities':
                $rows = [];
                foreach ($returns as $r) {
                    $d = $r->statistical_form['utilities'] ?? [];
                    $rows[] = [...$this->prefix($r), isset($d['water_sources']) ? implode(', ', $d['water_sources']) : null, $d['water_reliable'] ?? null, ($d['water_reliable'] ?? null) === 'No' ? ($d['water_explanation'] ?? null) : null, isset($d['power_sources']) ? implode(', ', $d['power_sources']) : null, $d['other_power'] ?? null];
                }
                $tables[] = $this->table('Water and electricity', [...$base, 'Water sources', 'Reliable water', 'Explanation if No', 'Electricity sources', 'Other electricity source'], $rows, 'Sources may overlap. A source list does not establish reliability.');
                break;
            case 'planning indicators':
                $rows = [];
                foreach ($returns as $r) {
                    $n = $this->learners($r);
                    $teachers = $this->teacherCount($r);
                    $desk = $this->number($r, 'desk_capacity');
                    $boys = [];
                    $girls = [];
                    foreach (array_merge(array_values($r->statistical_form['enrolment']['classes'] ?? []), StatisticalForm::allowsNursery($r) ? array_values($r->statistical_form['enrolment']['nursery'] ?? []) : []) as $row) {
                        $boys[] = $row['boys'] ?? null;
                        $girls[] = $row['girls'] ?? null;
                    }
                    $rows[] = [...$this->prefix($r), $n, $teachers, $this->ratio($n, $teachers), $this->ratio($n, $this->number($r, 'permanent_classrooms')), $this->ratio($this->number($r, 'library_books'), $n), $this->ratio($n, $this->number($r, 'computers')), $this->ratio($boys && ! in_array(null, $boys, true) ? array_sum($boys) : null, $this->number($r, 'boys_toilets')), $this->ratio($girls && ! in_array(null, $girls, true) ? array_sum($girls) : null, $this->number($r, 'girls_toilets')), $n !== null && $desk !== null ? max(0, $n - $desk) : null];
                }
                $tables[] = $this->table('Planning indicators', [...$base, 'Learners', 'Teachers', 'Learners per teacher', 'Learners per permanent classroom', 'Books per learner', 'Learners per computer', 'Boys per toilet stance', 'Girls per toilet stance', 'Missing seating places'], $rows, 'Each ratio uses only the figures reported by that school. A missing or zero denominator gives no ratio. These are planning prompts, not findings against a staffing or safety standard.');
                break;
            case 'year comparison':
                $previous = (int) ($filters['comparison_year'] ?? $year - 1);
                $old = $this->returns($schools, $previous, $basis)->keyBy('school_id');
                $current = $returns->keyBy('school_id');
                $rows = [];
                $before = 0;
                $after = 0;
                $paired = 0;
                foreach ($schools as $s) {
                    $a = $old->get($s->id);
                    $b = $current->get($s->id);
                    $na = $a ? $this->learners($a) : null;
                    $nb = $b ? $this->learners($b) : null;
                    $match = $na !== null && $nb !== null;
                    if ($match) {
                        $before += $na;
                        $after += $nb;
                        $paired++;
                    }
                    $rows[] = [$s->official_name, $s->ministry_registration_number, $na, $nb, $match ? $nb - $na : null, $match && $na > 0 ? round(($nb - $na) * 100 / $na, 2) : null, $match ? 'Matched' : 'Not comparable', $a ? $this->teacherCount($a) : null, $b ? $this->teacherCount($b) : null, $a ? $this->number($a, 'permanent_classrooms') : null, $b ? $this->number($b, 'permanent_classrooms') : null, $a && array_key_exists('support', $a->statistical_form ?? []) ? count($a->statistical_form['support']['members'] ?? []) : null, $b && array_key_exists('support', $b->statistical_form ?? []) ? count($b->statistical_form['support']['members'] ?? []) : null];
                }
                $tables[] = $this->table('Comparable enrolment', ['Matched schools', (string) $previous, (string) $year, 'Change', 'Change percent'], [[$paired, $paired ? $before : null, $paired ? $after : null, $paired ? $after - $before : null, $before > 0 ? round(($after - $before) * 100 / $before, 2) : null]], 'Summary enrolment uses only schools with reported enrolment in both years.');
                $tables[] = $this->table('School year comparison', ['School', 'Registration number', "Learners $previous", "Learners $year", 'Learner change', 'Change percent', 'Comparability', "Teachers $previous", "Teachers $year", "Permanent classrooms $previous", "Permanent classrooms $year", "Support staff $previous", "Support staff $year"], $rows);
                $facilities = [];
                foreach ($schools as $s) {
                    $a = $old->get($s->id);
                    $b = $current->get($s->id);
                    foreach (StatisticalForm::sections()['facilities']['tables']['items']['rows'] as $item => $label) {
                        $qa = $a ? $this->number($a, $item) : null;
                        $qb = $b ? $this->number($b, $item) : null;
                        $facilities[] = [$s->official_name, $label, $qa, $qb, $qa !== null && $qb !== null ? $qb - $qa : null];
                    }
                }
                $tables[] = $this->table('Facility year comparison', ['School', 'Facility', (string) $previous, (string) $year, 'Change'], $facilities, 'Each facility change requires a reported quantity in both years; blank is not zero.');
                break;
            case 'announcements':
                $rows = [];
                $summary = [];
                foreach (Announcement::whereYear('published_at', $year)->with(['deliveries' => fn ($q) => $q->whereIn('school_id', $schools->pluck('id'))->with('school')])->get() as $a) {
                    $grouped = $a->deliveries->groupBy('school_id');
                    $targetIds = collect($a->target_filters['school_ids'] ?? [])->merge($grouped->keys())->unique()->intersect($schools->pluck('id'));
                    $delivered = 0;
                    $acknowledged = 0;
                    foreach ($targetIds as $id) {
                        $deliveries = $grouped->get($id, collect());
                        $read = $deliveries->whereNotNull('read_at')->sortBy('read_at')->first();
                        if ($deliveries->count()) {
                            $delivered++;
                        }if ($read) {
                            $acknowledged++;
                        }
                        $rows[] = [$a->title, $a->body, $a->published_at?->timezone('Africa/Kampala')->format('Y-m-d H:i'), $schools->firstWhere('id', $id)?->official_name, $read ? 'Acknowledged' : ($deliveries->count() ? 'Awaiting acknowledgement' : 'No active account at publication'), $read?->read_at?->timezone('Africa/Kampala')->format('Y-m-d H:i')];
                    }
                    if ($targetIds->count()) {
                        $summary[] = [$a->title, $a->published_at?->timezone('Africa/Kampala')->format('Y-m-d H:i'), $targetIds->count(), $delivered, $acknowledged, $delivered - $acknowledged, $targetIds->count() - $delivered];
                    }
                }
                $tables[] = $this->table('Announcement summary', ['Announcement', 'Published', 'Audience schools', 'Delivered', 'Acknowledged', 'Awaiting acknowledgement', 'Not reached'], $summary, 'Audience and acknowledgement counts respect the selected school filters.');
                $tables[] = $this->table('Announcement acknowledgement', ['Announcement', 'Message', 'Published', 'School', 'Acknowledgement', 'Acknowledged at'], $rows, 'Publication year filter. Acknowledgements are counted once per school per announcement.');
                break;
            case 'review history':
                $all = SchoolReturn::whereIn('school_id', $schools->pluck('id'))->whereHas('reportingPeriod', fn ($q) => $q->where('year', $year)->where('is_archived', false))->with('school')->get()->keyBy('id');
                $rows = [];
                foreach (AuditEvent::where('auditable_type', SchoolReturn::class)->whereIn('auditable_id', $all->keys())->whereIn('action', ['submission.submit', 'submission.approve', 'submission.return_for_correction'])->with('user')->oldest()->get() as $e) {
                    $rows[] = [$all[$e->auditable_id]->school->official_name, 'MDES-'.$e->auditable_id, $e->action, $e->user?->name, $e->created_at->timezone('Africa/Kampala')->format('Y-m-d H:i'), $e->reason];
                }
                $tables[] = $this->table('Submission review history', ['School', 'Reference', 'Action', 'Responsible user', 'Date EAT', 'Reason'], $rows);
                break;
            case 'support requests':
                $rows = [];
                foreach (HelpdeskTicket::whereIn('school_id', $schools->pluck('id'))->whereYear('created_at', $year)->with('messages')->get() as $t) {
                    $s = $schools->firstWhere('id', $t->school_id);
                    $rows[] = [$t->id, $s?->official_name, $t->category, $t->description, $t->status, $t->created_at->timezone('Africa/Kampala')->format('Y-m-d H:i'), $t->messages->map(fn ($m) => $m->created_at->timezone('Africa/Kampala')->format('Y-m-d H:i').' '.$m->message)->implode("\n")];
                }
                $tables[] = $this->table('Support requests', ['Ticket', 'School', 'Category', 'Issue', 'Status', 'Opened', 'Responses'], $rows, 'Request creation year filter.');
                break;
            case 'user accounts':
                $rows = User::with('school', 'roles')->where(fn ($q) => $q->whereIn('school_id', $schools->pluck('id'))->when(! array_intersect(array_keys($filters), ['school_id', 'parish_id', 'district_id', 'level', 'funding', 'gender', 'residence']), fn ($q) => $q->orWhereNull('school_id')))->get()->map(fn ($u) => [$u->name, $u->username, $u->email, $u->telephone, $u->school?->official_name, $u->roles->pluck('name')->implode(', '), $u->status, $u->hasValidPortalRole() ? 'Valid' : 'Needs administrator correction', $u->last_login_at?->timezone('Africa/Kampala')->format('Y-m-d H:i')])->all();
                $tables[] = $this->table('User accounts', ['Name', 'Username', 'Email', 'Telephone', 'School', 'Account type', 'Status', 'Assignment check', 'Last login EAT'], $rows, 'Current accounts. Passwords, reset tokens and session information are never included.');
                break;
            case 'complete form':
                foreach ($returns as $r) {
                    $tables = [...$tables, ...$this->formTables($r)];
                }
                break;
            case 'annual summary':
                $learnerReturns = $returns->filter(fn ($r) => $this->learners($r) !== null);
                $teacherReturns = $returns->filter(fn ($r) => $this->teacherCount($r) !== null);
                $paired = $returns->filter(fn ($r) => $this->learners($r) !== null && ($this->teacherCount($r) ?? 0) > 0);
                $tables[] = $this->table('Diocesan summary', ['Indicator', 'Recorded value', 'Schools providing figures'], [
                    ['Registered schools', $schools->count(), $schools->count()], ['Assigned schools', $expected->count(), $expected->count()], ['Returns in selected data basis', $returns->count(), $returns->count()],
                    ['Learners', $learnerReturns->isNotEmpty() ? $learnerReturns->sum(fn ($r) => $this->learners($r)) : null, $learnerReturns->count()],
                    ['Teaching staff', $teacherReturns->isNotEmpty() ? $teacherReturns->sum(fn ($r) => $this->teacherCount($r)) : null, $teacherReturns->count()],
                    ['Learners per teacher in paired schools', $this->ratio($paired->sum(fn ($r) => $this->learners($r)), $paired->sum(fn ($r) => $this->teacherCount($r))), $paired->count()],
                ]);
                foreach (['parish', 'level'] as $group) {
                    $rows = [];
                    foreach ($schools->groupBy(fn ($s) => $group === 'parish' ? ($s->parish?->name ?? 'Not recorded') : $s->school_level) as $label => $ss) {
                        $rr = $returns->whereIn('school_id', $ss->pluck('id'));
                        $valid = $rr->filter(fn ($r) => $this->learners($r) !== null);
                        $rows[] = [$label, $ss->count(), $expected->whereIn('id', $ss->pluck('id'))->count(), $rr->count(), $valid->count() ? $valid->sum(fn ($r) => $this->learners($r)) : null, $valid->count()];
                    }
                    $tables[] = $this->table(ucfirst($group).' summary', [$group, 'Registered', 'Assigned', 'Returns included', 'Learners', 'Enrolment coverage'], $rows);
                }
                foreach (['religious affiliation', 'examinations', 'staff', 'support staff', 'infrastructure', 'utilities', 'staff study programmes'] as $part) {
                    $child = $this->build($user, $part, $filters);
                    $tables = [...$tables, ...$child['tables']];
                }
                break;
        }

        return ['title' => ReportCatalog::TYPES[$type], 'metadata' => $meta, 'notes' => $notes, 'tables' => $tables, 'school_ids' => $schools->pluck('id')->all()];
    }

    public function formTables(SchoolReturn $r, ?array $schema = null): array
    {
        $tables = [];
        $form = $r->statistical_form ?? [];
        foreach ($schema ?? StatisticalForm::sectionsForReturn($r) as $key => $section) {
            if ($key === 'declaration') {
                continue;
            }
            $data = $form[$key] ?? [];
            if ($key === 'enrolment') {
                $data['community'] = $form['religion']['community'] ?? [];
            }
            if (! empty($section['fields'])) {
                $rows = [];
                foreach ($section['fields'] as $field => $def) {
                    if ($key === 'utilities' && $field === 'water_explanation' && ($data['water_reliable'] ?? null) !== 'No') {
                        continue;
                    }
                    $value = $data[$field] ?? null;
                    $rows[] = [$def['display_label'] ?? $def['label'], is_array($value) ? implode(', ', $value) : $value];
                }
                $tables[] = $this->table($key === 'identity' ? 'School profile' : $section['title'], ['Question', 'Answer'], $rows);
            }
            foreach ($section['tables'] ?? [] as $tk => $table) {
                $rows = [];
                $values = $data[$tk] ?? [];
                foreach ($table['rows'] ?? array_fill_keys(array_keys($values), '') as $rk => $label) {
                    $row = $values[$rk] ?? [];
                    $v = [];
                    foreach ($table['columns'] as $col => $def) {
                        $v[] = in_array($def['type'] ?? null, ['integer', 'decimal', 'age']) ? $this->countValue($row[$col] ?? null) : ($row[$col] ?? null);
                    }
                    $rows[] = [$label !== '' ? (string) $label : (is_numeric($rk) ? $rk + 1 : $rk), ...$v];
                }
                $headers = [];
                foreach ($table['columns'] as $def) {
                    $headers[] = is_array($def) ? ($def['display_label'] ?? $def['label']) : $def;
                }
                if ($key === 'enrolment' && in_array($tk, ['classes', 'nursery'], true)) {
                    $boys = null;
                    $girls = null;
                    foreach ($rows as &$entry) {
                        if ($entry[1] !== null && $entry[2] !== null) {
                            $entry[] = $entry[1] + $entry[2];
                            $boys = ($boys ?? 0) + $entry[1];
                            $girls = ($girls ?? 0) + $entry[2];
                        } else {
                            $entry[] = null;
                        }
                    }unset($entry);
                    $headers[] = 'Total';
                    $rows[] = ['Recorded total', $boys, $girls, $boys !== null && $girls !== null ? $boys + $girls : null];
                }
                $tables[] = $this->table($table['label'], [$table['row_heading'] ?? 'Category', ...$headers], $rows);
            }
        }
        $ack = $form['declaration'] ?? [];
        $tables[] = $this->table('Acknowledgement', ['Item', 'Recorded value'], [['Acknowledged', ! empty($ack['acknowledged']) ? 'Yes' : 'Not yet acknowledged'], ['Acknowledged at', $ack['acknowledged_at'] ?? null], ['Submission reference', 'MDES-'.$r->id]]);

        return $tables;
    }
}
