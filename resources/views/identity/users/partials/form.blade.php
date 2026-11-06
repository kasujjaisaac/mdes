@php($assignedRoleIds = collect(old('role_ids', $user?->roles->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id))

@if ($errors->any())
    <x-ui.alert tone="info">
        check the highlighted fields and try again.
    </x-ui.alert>
@endif

<div class="form-grid">
<label>Assigned school (required for school representatives)<select name="school_id"><option value="">No school assignment</option>@foreach($schools as $school)<option value="{{ $school->id }}" @selected(old('school_id',$user?->school_id)==$school->id)>{{ $school->official_name }}</option>@endforeach</select>@error('school_id')<small>{{ $message }}</small>@enderror</label>
    <label>
        full name
        <input name="name" value="{{ old('name', $user?->name) }}" required>
        @error('name') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        email
        <input name="email" type="email" value="{{ old('email', $user?->email) }}" required>
        @error('email') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        telephone
        <input name="telephone" value="{{ old('telephone', $user?->telephone) }}">
        @error('telephone') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        designation
        <input name="designation" value="{{ old('designation', $user?->designation) }}">
        @error('designation') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        account status
        <select name="status" required>
            @foreach ($statuses as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $user?->status ?? 'active') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('status') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        {{ $user ? 'new password' : 'password' }}
        <input name="password" type="password" @if (! $user) required @endif>
        @error('password') <small>{{ $message }}</small> @enderror
    </label>
</div>

<fieldset class="role-picker">
    <legend>roles</legend>
    @foreach ($roles as $role)
        <label>
            <input
                type="checkbox"
                name="role_ids[]"
                value="{{ $role->id }}"
                @checked($assignedRoleIds->contains($role->id))
            >
            <span>{{ strtolower($role->name) }}</span>
        </label>
    @endforeach
    @error('role_ids') <small>{{ $message }}</small> @enderror
</fieldset>
