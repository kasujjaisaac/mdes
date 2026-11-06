<x-layouts.app :title="$title" :hide-heading="true">
<div class="parish-editor">
<header class="parish-editor-heading"><div><a href="{{ route('parishes.index') }}">&larr; Parishes</a><h1>Edit parish</h1><p>Update parish details and contact information.</p></div><span><b>*</b> Required fields</span></header>
<form data-protect-changes class="resource-form parish-editor-form" method="POST" action="{{ route('parishes.update', $parish) }}">
@csrf
@method('PUT')
@include('diocesan-structure.parishes.partials.form', ['parish' => $parish])
<footer class="parish-editor-actions"><span>Check the parish name and code before saving.</span><div><a class="button button-secondary" href="{{ route('parishes.index') }}">Cancel</a><button class="button button-primary" type="submit">Save changes</button></div></footer>
</form></div>
</x-layouts.app>
