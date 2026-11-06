<x-layouts.app :title="$title" :breadcrumbs="$breadcrumbs" :hide-heading="true">
<div class="school-editor">
<header class="school-editor-heading"><div><a href="{{ route('schools.index') }}">&larr; Registered Schools</a><h1>Register school</h1><p>Add a school to the diocesan registry.</p></div><span class="school-required-note"><span>*</span> Required fields</span></header>
<form data-protect-changes class="resource-form school-editor-form" method="POST" action="{{ route('schools.store') }}">
@csrf
@include('schools.partials.form', ['school' => null])
<div class="school-editor-actions"><p>School registration and annual statistical returns are separate.</p><div><a class="button button-secondary" href="{{ route('schools.index') }}">Cancel</a><button class="button button-primary" type="submit">Register school</button></div></div>
</form></div>
</x-layouts.app>
