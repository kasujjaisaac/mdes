<x-layouts.app :title="$title" :breadcrumbs="[['label' => 'inspections', 'url' => route('inspections.index')], ['label' => 'schedule']]">
    <x-ui.card title="schedule inspection" subtitle="planned school monitoring visit">
        @if ($errors->any()) <x-ui.alert tone="info">{{ $errors->first() }}</x-ui.alert> @endif
        <form class="resource-form" method="POST" action="{{ route('inspections.store') }}">
            @csrf
            <div class="form-grid">
                <label>school<select name="school_id">@foreach ($schools as $school)<option value="{{ $school->id }}">{{ $school->name }}</option>@endforeach</select></label>
                <label>template<select name="inspection_template_id"><option value="">none</option>@foreach ($templates as $template)<option value="{{ $template->id }}">{{ strtolower($template->name) }} v{{ $template->version }}</option>@endforeach</select></label>
                <label>type<input name="type" value="{{ old('type', 'routine') }}"></label>
                <label>purpose<input name="purpose" value="{{ old('purpose') }}" required></label>
                <label>planned date<input type="date" name="planned_date" value="{{ old('planned_date') }}" required></label>
            </div>
            <div class="form-actions"><a class="button button-secondary" href="{{ route('inspections.index') }}">cancel</a><button class="button button-primary">schedule</button></div>
        </form>
    </x-ui.card>
</x-layouts.app>
