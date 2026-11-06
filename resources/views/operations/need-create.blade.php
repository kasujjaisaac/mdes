<x-layouts.app :title="$title" :breadcrumbs="[['label' => 'development needs', 'url' => route('development-needs.index')], ['label' => 'submit']]">
    <x-ui.card title="development need" subtitle="evidence-based school development priority">
        @if ($errors->any()) <x-ui.alert tone="info">{{ $errors->first() }}</x-ui.alert> @endif
        <form class="resource-form" method="POST" action="{{ route('development-needs.store') }}">
            @csrf
            <div class="form-grid">
                <label>school<select name="school_id">@foreach ($schools as $school)<option value="{{ $school->id }}">{{ $school->name }}</option>@endforeach</select></label>
                <label>category<input name="category" value="{{ old('category', 'infrastructure') }}"></label>
                <label>urgency<select name="urgency">@foreach (['low','normal','high','urgent'] as $value)<option>{{ $value }}</option>@endforeach</select></label>
                <label>beneficiaries<input type="number" min="0" name="beneficiaries" value="{{ old('beneficiaries', 0) }}"></label>
                <label>estimated cost<input type="number" min="0" step="0.01" name="estimated_cost" value="{{ old('estimated_cost', 0) }}"></label>
                <label>school contribution<input type="number" min="0" step="0.01" name="school_contribution" value="{{ old('school_contribution', 0) }}"></label>
            </div>
            <label class="wide-field">description<textarea name="description" required>{{ old('description') }}</textarea></label>
            <label class="wide-field">justification<textarea name="justification">{{ old('justification') }}</textarea></label>
            <div class="form-actions"><a class="button button-secondary" href="{{ route('development-needs.index') }}">cancel</a><button class="button button-primary">submit need</button></div>
        </form>
    </x-ui.card>
</x-layouts.app>
