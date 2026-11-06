<x-layouts.app :title="$title" :breadcrumbs="$breadcrumbs">
    <x-ui.card title="create user" subtitle="add an account and assign initial access">
        <form data-protect-changes class="resource-form" method="POST" action="{{ route('administration.users.store') }}">
            @csrf
            @include('identity.users.partials.form', ['user' => null])

            <div class="form-actions">
                <a class="button button-secondary" href="{{ route('administration.users.index') }}">cancel</a>
                <button class="button button-primary" type="submit">create user</button>
            </div>
        </form>
    </x-ui.card>
</x-layouts.app>
