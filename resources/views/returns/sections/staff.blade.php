<x-layouts.app :title="$title" :breadcrumbs="[['label' => 'returns', 'url' => route('returns.show', $return)], ['label' => 'staff']]">
    <x-ui.card title="staff statistics" subtitle="aggregate workforce and establishment figures">
        @if ($errors->any()) <x-ui.alert tone="info">{{ $errors->first() }}</x-ui.alert> @endif
        @php($item = $return->staffStatistic)
        <form class="resource-form" method="POST" action="{{ route('returns.staff.save', $return) }}">
            @csrf
            <div class="form-grid">
                @foreach (['male_teaching_staff','female_teaching_staff','male_non_teaching_staff','female_non_teaching_staff','qualified_teachers','unqualified_teachers','government_payroll_staff','private_payroll_staff','approved_establishment','filled_positions'] as $field)
                    <label>{{ str_replace('_', ' ', $field) }}<input type="number" min="0" name="{{ $field }}" value="{{ old($field, $item?->{$field} ?? 0) }}"></label>
                @endforeach
            </div>
            <label class="wide-field">training needs<textarea name="training_needs">{{ old('training_needs', $item?->training_needs) }}</textarea></label>
            <label class="wide-field">housing gaps<textarea name="housing_gaps">{{ old('housing_gaps', $item?->housing_gaps) }}</textarea></label>
            <div class="form-actions">
                <a class="button button-secondary" href="{{ route('returns.show', $return) }}">cancel</a>
                <button class="button button-primary" type="submit">save staff</button>
            </div>
        </form>
    </x-ui.card>
</x-layouts.app>
