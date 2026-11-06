<x-layouts.app :title="$title" :breadcrumbs="[['label' => 'returns', 'url' => route('returns.show', $return)], ['label' => 'infrastructure']]">
    <x-ui.card title="infrastructure" subtitle="aggregate facilities, utilities and safety indicators">
        @if ($errors->any()) <x-ui.alert tone="info">{{ $errors->first() }}</x-ui.alert> @endif
        @php($rows = old('rows', $return->infrastructureStatistics->toArray() ?: [['facility_type' => 'classrooms', 'total' => 0, 'functional' => 0, 'damaged' => 0, 'required' => 0, 'condition' => 'fair', 'priority' => 'normal']]))
        <form class="resource-form" method="POST" action="{{ route('returns.infrastructure.save', $return) }}">
            @csrf
            @foreach ($rows as $index => $row)
                <div class="form-grid">
                    <label>facility type<input name="rows[{{ $index }}][facility_type]" value="{{ $row['facility_type'] ?? 'classrooms' }}"></label>
                    @foreach (['total','functional','damaged','required','computers'] as $field)
                        <label>{{ $field }}<input type="number" min="0" name="rows[{{ $index }}][{{ $field }}]" value="{{ $row[$field] ?? 0 }}"></label>
                    @endforeach
                    <label>condition<select name="rows[{{ $index }}][condition]">@foreach (['good','fair','poor','critical'] as $value)<option @selected(($row['condition'] ?? 'fair') === $value)>{{ $value }}</option>@endforeach</select></label>
                    <label>priority<select name="rows[{{ $index }}][priority]">@foreach (['low','normal','high','urgent'] as $value)<option @selected(($row['priority'] ?? 'normal') === $value)>{{ $value }}</option>@endforeach</select></label>
                    <label>water source<input name="rows[{{ $index }}][water_source]" value="{{ $row['water_source'] ?? '' }}"></label>
                    <label>water reliability<input name="rows[{{ $index }}][water_reliability]" value="{{ $row['water_reliability'] ?? '' }}"></label>
                </div>
                <div class="checkbox-grid">
                    @foreach (['has_electricity','has_internet','fire_safety_available','accessibility_available','security_fenced','high_risk_condition'] as $field)
                        <label><input type="checkbox" name="rows[{{ $index }}][{{ $field }}]" value="1" @checked($row[$field] ?? false)> {{ str_replace('_', ' ', $field) }}</label>
                    @endforeach
                </div>
                <label class="wide-field">notes<textarea name="rows[{{ $index }}][notes]">{{ $row['notes'] ?? '' }}</textarea></label>
            @endforeach
            <div class="form-actions"><a class="button button-secondary" href="{{ route('returns.show', $return) }}">cancel</a><button class="button button-primary" type="submit">save infrastructure</button></div>
        </form>
    </x-ui.card>
</x-layouts.app>
