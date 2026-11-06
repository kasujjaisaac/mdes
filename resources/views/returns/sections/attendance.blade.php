<x-layouts.app :title="$title" :breadcrumbs="[['label' => 'returns', 'url' => route('returns.show', $return)], ['label' => 'attendance']]">
    <x-ui.card title="attendance and welfare" subtitle="aggregated attendance, retention and welfare indicators">
        @if ($errors->any()) <x-ui.alert tone="info">{{ $errors->first() }}</x-ui.alert> @endif
        @php($item = $return->attendanceWelfareStatistic)
        <form class="resource-form" method="POST" action="{{ route('returns.attendance.save', $return) }}">
            @csrf
            <div class="form-grid">
                @foreach (['enrolled_learners','expected_attendance','aggregate_attendance','retained_learners','dropouts','re_entries','transitioned_learners','welfare_cases_aggregated'] as $field)
                    <label>{{ str_replace('_', ' ', $field) }}<input type="number" min="0" name="{{ $field }}" value="{{ old($field, $item?->{$field} ?? 0) }}"></label>
                @endforeach
            </div>
            <label class="wide-field">trend explanation<textarea name="trend_explanation">{{ old('trend_explanation', $item?->trend_explanation) }}</textarea></label>
            <div class="form-actions">
                <a class="button button-secondary" href="{{ route('returns.show', $return) }}">cancel</a>
                <button class="button button-primary" type="submit">save attendance</button>
            </div>
        </form>
    </x-ui.card>
</x-layouts.app>
