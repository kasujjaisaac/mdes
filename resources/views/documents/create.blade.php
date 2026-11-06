<x-layouts.app :title="$title" :breadcrumbs="$breadcrumbs">
    <x-ui.card title="upload document" subtitle="files are stored outside public web access">
        @if ($errors->any()) <x-ui.alert tone="info">{{ $errors->first() }}</x-ui.alert> @endif
        <form class="resource-form" method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="form-grid">
                <label>title<input name="title" value="{{ old('title') }}" required></label>
                <label>category<select name="document_category_id" required>@foreach ($categories as $category)<option value="{{ $category->id }}">{{ strtolower($category->name) }}</option>@endforeach</select></label>
                <label>school<select name="school_id"><option value="">diocesan/general</option>@foreach ($schools as $school)<option value="{{ $school->id }}">{{ $school->official_name }}</option>@endforeach</select></label>
                <label>sensitivity<select name="sensitivity">@foreach (['internal','confidential','restricted','public'] as $value)<option @selected(old('sensitivity', 'internal') === $value)>{{ $value }}</option>@endforeach</select></label>
                <label>issue date<input type="date" name="issue_date" value="{{ old('issue_date') }}"></label>
                <label>expiry date<input type="date" name="expiry_date" value="{{ old('expiry_date') }}"></label>
                <label>file<input type="file" name="file" required></label>
            </div>
            <label class="wide-field">notes<textarea name="notes">{{ old('notes') }}</textarea></label>
            <div class="form-actions"><a class="button button-secondary" href="{{ route('documents.index') }}">cancel</a><button class="button button-primary" type="submit">upload document</button></div>
        </form>
    </x-ui.card>
</x-layouts.app>
