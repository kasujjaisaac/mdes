<x-layouts.app :title="$title" :breadcrumbs="[['label' => 'improvement plans', 'url' => route('improvement-plans.index')], ['label' => 'create']]">
    <x-ui.card title="improvement plan" subtitle="challenge, target and success indicator">
        @if ($errors->any()) <x-ui.alert tone="info">{{ $errors->first() }}</x-ui.alert> @endif
        <form class="resource-form" method="POST" action="{{ route('improvement-plans.store') }}">
            @csrf
            <div class="form-grid">
                <label>school<select name="school_id">@foreach ($schools as $school)<option value="{{ $school->id }}">{{ $school->name }}</option>@endforeach</select></label>
                <label>domain<input name="domain" value="{{ old('domain', 'academic') }}"></label>
                <label>baseline<input name="baseline" value="{{ old('baseline') }}"></label>
                <label>target<input name="target" value="{{ old('target') }}"></label>
                <label>success indicator<input name="success_indicator" value="{{ old('success_indicator') }}"></label>
                <label>due date<input type="date" name="due_date" value="{{ old('due_date') }}"></label>
            </div>
            <label class="wide-field">problem statement<textarea name="problem_statement" required>{{ old('problem_statement') }}</textarea></label>
            <div class="form-actions"><a class="button button-secondary" href="{{ route('improvement-plans.index') }}">cancel</a><button class="button button-primary">create plan</button></div>
        </form>
    </x-ui.card>
</x-layouts.app>
