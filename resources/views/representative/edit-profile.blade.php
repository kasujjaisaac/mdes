<x-layouts.app :title="$title" :hide-heading="true">

<div class="school-editor school-confirmation-compact mobile-profile-editor"><header class="school-editor-heading"><div><h1>Edit school profile</h1><p>{{ $school->official_name }}</p></div></header>
<form class="resource-form card" style="padding:18px" data-protect-changes method="POST" action="{{ route('representative.profile.update') }}">@csrf @method('PUT')
@include('schools.partials.form',['school'=>$school,'compact'=>true])
<p class="muted school-edit-guidance">Changes apply to your school profile. Previously submitted forms retain their original details. Contact the administrator to correct the school name.</p>
<div class="form-actions"><a class="button" href="{{ route('representative.profile') }}">Cancel</a><button class="button button-primary">Save changes</button></div></form></div>
</x-layouts.app>
