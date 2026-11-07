<x-layouts.app :title="$title" :breadcrumbs="$breadcrumbs" :hide-heading="true">
<div class="school-editor">
<header class="school-editor-heading"><div><h1>Edit school</h1><p>Update the school particulars below.</p></div><span class="school-required-note"><span>*</span> Required fields</span></header>
<form data-protect-changes class="resource-form school-editor-form" method="POST" action="{{ route('schools.update', $school) }}">
@csrf
@method('PUT')
@include('schools.partials.form', ['school' => $school])
<div class="school-editor-actions"><p>School registration and annual statistical returns are separate.</p><div><a class="button button-secondary" href="{{ route('schools.show', $school) }}">Cancel</a><button class="button button-primary" type="submit">Save changes</button></div></div>
</form></div>
</x-layouts.app>
