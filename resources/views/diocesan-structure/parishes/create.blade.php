<x-layouts.app :title="$title" :hide-heading="true">
<div class="parish-editor">
<header class="parish-editor-heading"><div><a href="{{ route('parishes.index') }}">&larr; Parishes</a><h1>Create parish</h1><p>Add a parish to the school directory.</p></div><span><b>*</b> Required fields</span></header>
<form data-protect-changes class="resource-form parish-editor-form" method="POST" action="{{ route('parishes.store') }}">
@csrf
@include('diocesan-structure.parishes.partials.form', ['parish' => null])
<footer class="parish-editor-actions"><span>Check the parish name and code before saving.</span><div><a class="button button-secondary" href="{{ route('parishes.index') }}">Cancel</a><button class="button button-primary" type="submit">Create parish</button></div></footer>
</form></div>
</x-layouts.app>
