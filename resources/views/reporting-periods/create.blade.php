<x-layouts.app :title="$title" :breadcrumbs="$breadcrumbs">
    <x-ui.card title="New submission window" subtitle="Define the collection schedule and select participating schools.">
        <form data-protect-changes class="resource-form" method="POST" action="{{ route('reporting-periods.store') }}">
            @csrf
            @if ($errors->any()) <x-ui.alert tone="info">check the highlighted fields and try again.</x-ui.alert> @endif
            <div class="form-grid">
                <label>title <input name="title" value="{{ old('title') }}" required></label>
                <label>type
                    <select name="type" required>
                        @foreach ($types as $type)<option value="{{ $type }}" @selected(old('type') === $type)>{{ str_replace('_', ' ', $type) }}</option>@endforeach
                    </select>
                </label>
                <label>year <input name="year" type="number" value="{{ old('year', now()->year) }}" required></label>
                <label>term <input name="term" value="{{ old('term') }}"></label>
                <label>opening date <input name="opening_date" type="date" value="{{ old('opening_date', now()->toDateString()) }}" required></label>
                <label>closing date <input name="closing_date" type="date" value="{{ old('closing_date', now()->addMonth()->toDateString()) }}" required></label>
                <label>extension date <input name="extension_date" type="date" value="{{ old('extension_date') }}"></label>
                <label>lock date <input name="lock_date" type="date" value="{{ old('lock_date') }}"></label>
                <label>status
                    <select name="status">@foreach ($statuses as $status)<option value="{{ $status }}">{{ $status }}</option>@endforeach</select>
                </label>
            </div>
            <label class="wide-field">instructions <textarea name="instructions">{{ old('instructions') }}</textarea></label>
            <fieldset class="role-picker">
                <legend>participating schools</legend>
                @foreach ($schools as $school)
                    <label><input type="checkbox" name="school_ids[]" value="{{ $school->id }}" @checked(in_array($school->id, old('school_ids', [])))><span>{{ $school->official_name }}</span></label>
                @endforeach
            </fieldset>
            <div class="form-actions">
                <a class="button button-secondary" href="{{ route('reporting-periods.index') }}">cancel</a>
                <button class="button button-primary" type="submit">Create window</button>
            </div>
        </form>
    </x-ui.card>
</x-layouts.app>
