<x-layouts.app :title="$title" :breadcrumbs="[['label' => 'helpdesk', 'url' => route('helpdesk.index')], ['label' => 'open ticket']]">
    <x-ui.card title="helpdesk ticket" subtitle="support request with retained history">
        @if ($errors->any()) <x-ui.alert tone="info">{{ $errors->first() }}</x-ui.alert> @endif
        <form class="resource-form" method="POST" action="{{ route('helpdesk.store') }}">
            @csrf
            <div class="form-grid">
                <label>category<input name="category" value="{{ old('category', 'support') }}"></label>
                <label>priority<select name="priority">@foreach (['low','normal','high','urgent'] as $value)<option>{{ $value }}</option>@endforeach</select></label>
            </div>
            <label class="wide-field">description<textarea name="description" required>{{ old('description') }}</textarea></label>
            <div class="form-actions"><a class="button button-secondary" href="{{ route('helpdesk.index') }}">cancel</a><button class="button button-primary">open ticket</button></div>
        </form>
    </x-ui.card>
</x-layouts.app>
