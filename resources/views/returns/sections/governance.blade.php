<x-layouts.app :title="$title" :breadcrumbs="[['label' => 'returns', 'url' => route('returns.show', $return)], ['label' => 'governance']]">
    <x-ui.card title="governance and compliance" subtitle="leadership, policies and compliance summary">
        @if ($errors->any()) <x-ui.alert tone="info">{{ $errors->first() }}</x-ui.alert> @endif
        @php($item = $return->governanceComplianceStatistic)
        <form class="resource-form" method="POST" action="{{ route('returns.governance.save', $return) }}">
            @csrf
            <div class="form-grid">
                <label>last board meeting date<input type="date" name="last_board_meeting_date" value="{{ old('last_board_meeting_date', $item?->last_board_meeting_date?->format('Y-m-d')) }}"></label>
                <label>board meetings held<input type="number" min="0" name="board_meetings_held" value="{{ old('board_meetings_held', $item?->board_meetings_held ?? 0) }}"></label>
                <label>open compliance issues<input type="number" min="0" name="open_compliance_issues" value="{{ old('open_compliance_issues', $item?->open_compliance_issues ?? 0) }}"></label>
            </div>
            <div class="checkbox-grid">
                @foreach (['board_active','pta_active','child_protection_policy_available','financial_policy_available','risk_register_available','mandatory_reports_submitted'] as $field)
                    <label><input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $item?->{$field} ?? false))> {{ str_replace('_', ' ', $field) }}</label>
                @endforeach
            </div>
            <label class="wide-field">leadership notes<textarea name="leadership_notes">{{ old('leadership_notes', $item?->leadership_notes) }}</textarea></label>
            <label class="wide-field">compliance notes<textarea name="compliance_notes">{{ old('compliance_notes', $item?->compliance_notes) }}</textarea></label>
            <div class="form-actions"><a class="button button-secondary" href="{{ route('returns.show', $return) }}">cancel</a><button class="button button-primary" type="submit">save governance</button></div>
        </form>
    </x-ui.card>
</x-layouts.app>
