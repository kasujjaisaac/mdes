<x-layouts.app :title="$title" :breadcrumbs="$breadcrumbs">
    <x-ui.card title="assign jurisdiction" subtitle="assign a user to a parish or education cluster with effective dates">
        <form class="resource-form" method="POST" action="{{ route('diocesan-structure.assignments.store') }}">
            @csrf
            @if ($errors->any())
                <x-ui.alert tone="info">check the highlighted assignment fields and try again.</x-ui.alert>
            @endif
            <div class="form-grid">
                <label>
                    user
                    <select name="user_id" required>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected((int) old('user_id') === $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    assignment type
                    <select name="assignment_type" required>
                        @foreach ($types as $value => $label)
                            <option value="{{ $value }}" @selected(old('assignment_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    parish
                    <select name="parish_id">
                        <option value="">none</option>
                        @foreach ($parishes as $parish)
                            <option value="{{ $parish->id }}" @selected((int) old('parish_id') === $parish->id)>{{ $parish->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    cluster
                    <select name="education_cluster_id">
                        <option value="">none</option>
                        @foreach ($clusters as $cluster)
                            <option value="{{ $cluster->id }}" @selected((int) old('education_cluster_id') === $cluster->id)>{{ $cluster->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    effective from
                    <input type="date" name="effective_from" value="{{ old('effective_from', now()->toDateString()) }}" required>
                </label>
                <label>
                    effective to
                    <input type="date" name="effective_to" value="{{ old('effective_to') }}">
                </label>
                <label class="checkbox-row">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" checked>
                    active
                </label>
            </div>
            <label class="wide-field">
                notes
                <textarea name="notes">{{ old('notes') }}</textarea>
            </label>
            <div class="form-actions">
                <a class="button button-secondary" href="{{ route('diocesan-structure.index') }}">cancel</a>
                <button class="button button-primary" type="submit">save assignment</button>
            </div>
        </form>
    </x-ui.card>
</x-layouts.app>
