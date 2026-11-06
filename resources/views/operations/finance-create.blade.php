<x-layouts.app :title="$title" :breadcrumbs="[['label' => 'financial statistics', 'url' => route('financial-statistics.index')], ['label' => 'capture']]">
    <x-ui.card title="financial statistics" subtitle="high-level summary only, not a general ledger">
        @if ($errors->any()) <x-ui.alert tone="info">{{ $errors->first() }}</x-ui.alert> @endif
        <form class="resource-form" method="POST" action="{{ route('financial-statistics.store') }}">
            @csrf
            <div class="form-grid">
                <label>school<select name="school_id">@foreach ($schools as $school)<option value="{{ $school->id }}">{{ $school->name }}</option>@endforeach</select></label>
                <label>budget year<input type="number" name="budget_year" value="{{ old('budget_year', date('Y')) }}"></label>
                <label>tuition budget<input type="number" step="0.01" min="0" name="budget_categories[tuition]" value="0"></label>
                <label>grant income<input type="number" step="0.01" min="0" name="income_categories[grants]" value="0"></label>
                <label>operations expenditure<input type="number" step="0.01" min="0" name="expenditure_categories[operations]" value="0"></label>
                <label>audit status<input name="audit_status" value="{{ old('audit_status') }}"></label>
                <label>audit date<input type="date" name="audit_date" value="{{ old('audit_date') }}"></label>
            </div>
            <div class="checkbox-grid"><label><input type="checkbox" name="audited_accounts_available" value="1"> audited accounts available</label></div>
            <label class="wide-field">variance explanation<textarea name="variance_explanation">{{ old('variance_explanation') }}</textarea></label>
            <div class="form-actions"><a class="button button-secondary" href="{{ route('financial-statistics.index') }}">cancel</a><button class="button button-primary">save finance summary</button></div>
        </form>
    </x-ui.card>
</x-layouts.app>
