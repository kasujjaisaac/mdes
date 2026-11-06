<x-layouts.app :title="$title" :breadcrumbs="[['label' => 'returns', 'url' => route('returns.show', $return)], ['label' => 'academic performance']]">
    <x-ui.card title="academic performance" subtitle="candidate and aggregated examination outcomes">
        @if ($errors->any()) <x-ui.alert tone="info">{{ $errors->first() }}</x-ui.alert> @endif
        @php($rows = old('rows', $return->academicPerformanceStatistics->map(fn ($item) => [
            'examination_type_id' => $item->examination_type_id,
            'registered_candidates' => $item->registered_candidates,
            'candidates_sat' => $item->candidates_sat,
            'absentees' => $item->absentees,
            'male_candidates' => $item->male_candidates,
            'female_candidates' => $item->female_candidates,
            'grade_distribution' => $item->grade_distribution ?? [],
            'weaknesses' => $item->weaknesses,
            'intervention_notes' => $item->intervention_notes,
        ])->all() ?: [['registered_candidates' => 0, 'candidates_sat' => 0, 'absentees' => 0, 'male_candidates' => 0, 'female_candidates' => 0, 'grade_distribution' => []]]))
        <form class="resource-form" method="POST" action="{{ route('returns.academic.save', $return) }}">
            @csrf
            @foreach ($rows as $index => $row)
                <div class="form-grid">
                    <label>examination type<select name="rows[{{ $index }}][examination_type_id]">@foreach ($examinationTypes as $type)<option value="{{ $type->id }}" @selected(($row['examination_type_id'] ?? null) == $type->id)>{{ strtolower($type->name) }}</option>@endforeach</select></label>
                    @foreach (['registered_candidates','candidates_sat','absentees','male_candidates','female_candidates'] as $field)
                        <label>{{ str_replace('_', ' ', $field) }}<input type="number" min="0" name="rows[{{ $index }}][{{ $field }}]" value="{{ $row[$field] ?? 0 }}"></label>
                    @endforeach
                </div>
                <div class="form-grid">
                    @foreach (($examinationTypes->first()?->gradingScales ?? collect()) as $grade)
                        <label>grade {{ strtolower($grade->grade) }}<input type="number" min="0" name="rows[{{ $index }}][grade_distribution][{{ $grade->grade }}]" value="{{ $row['grade_distribution'][$grade->grade] ?? 0 }}"></label>
                    @endforeach
                </div>
                <label class="wide-field">weaknesses<textarea name="rows[{{ $index }}][weaknesses]">{{ $row['weaknesses'] ?? '' }}</textarea></label>
                <label class="wide-field">intervention notes<textarea name="rows[{{ $index }}][intervention_notes]">{{ $row['intervention_notes'] ?? '' }}</textarea></label>
            @endforeach
            <div class="form-actions"><a class="button button-secondary" href="{{ route('returns.show', $return) }}">cancel</a><button class="button button-primary" type="submit">save academic performance</button></div>
        </form>
    </x-ui.card>
</x-layouts.app>
