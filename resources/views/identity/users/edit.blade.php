<x-layouts.app :title="$title" :breadcrumbs="$breadcrumbs">
    <x-ui.card title="edit user" subtitle="update account status, profile details and roles">
        <form data-protect-changes class="resource-form" method="POST" action="{{ route('administration.users.update', $user) }}">
            @csrf
            @method('PUT')
            @include('identity.users.partials.form', ['user' => $user])

            <div class="form-actions">
                <a class="button button-secondary" href="{{ route('administration.users.index') }}">cancel</a>
                <button class="button button-primary" type="submit">save changes</button>
            </div>
        </form>
    </x-ui.card>
</x-layouts.app>
