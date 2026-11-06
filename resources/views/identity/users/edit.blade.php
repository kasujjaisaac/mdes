<x-layouts.app :title="$title" :hide-heading="true">
<div class="user-account-editor"><header class="user-editor-heading"><div><a class="user-editor-back" href="{{ route('administration.users.index') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m12 5-7 7 7 7M5 12h14"/></svg><span>Back to users</span></a><h1>Edit user</h1><p>Update account details, school assignment and access.</p></div><span class="user-required-note"><b>*</b> Required fields</span></header>
<form data-protect-changes class="user-account-form" method="POST" action="{{ route('administration.users.update', $user) }}">
@csrf
@method('PUT')
@include('identity.users.partials.form', ['user' => $user])
<div class="user-editor-actions"><span class="user-save-state" data-save-state>No unsaved changes</span><a class="button button-secondary" href="{{ route('administration.users.index') }}">Cancel</a><button class="button button-primary" type="submit">Save changes</button></div>
</form></div>
</x-layouts.app>
