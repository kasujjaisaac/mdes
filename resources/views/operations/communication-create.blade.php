<x-layouts.app :title="$title" :breadcrumbs="[['label' => 'communications', 'url' => route('communications.index')], ['label' => 'publish']]">
    <x-ui.card title="announcement" subtitle="targeted school communication">
        @if ($errors->any()) <x-ui.alert tone="info">{{ $errors->first() }}</x-ui.alert> @endif
        <form class="resource-form" method="POST" action="{{ route('communications.store') }}">
            @csrf
            <div class="form-grid">
                <label>title<input name="title" value="{{ old('title') }}" required></label>
                <label>target scope<select name="target_scope">@foreach (['all_schools','parish','school_level','selected_schools','selected_roles'] as $scope)<option>{{ $scope }}</option>@endforeach</select></label>
            </div>
            <div class="checkbox-grid"><label><input type="checkbox" name="requires_acknowledgement" value="1"> requires acknowledgement</label></div>
            <label class="wide-field">body<textarea name="body" required>{{ old('body') }}</textarea></label>
            <div class="form-actions"><a class="button button-secondary" href="{{ route('communications.index') }}">cancel</a><button class="button button-primary">publish</button></div>
        </form>
    </x-ui.card>
</x-layouts.app>
