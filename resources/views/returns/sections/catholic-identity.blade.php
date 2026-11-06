<x-layouts.app :title="$title" :breadcrumbs="[['label' => 'returns', 'url' => route('returns.show', $return)], ['label' => 'catholic identity']]">
    <x-ui.card title="catholic identity" subtitle="aggregate pastoral education indicators only">
        @if ($errors->any()) <x-ui.alert tone="info">{{ $errors->first() }}</x-ui.alert> @endif
        @php($item = $return->catholicIdentityStatistic)
        <form class="resource-form" method="POST" action="{{ route('returns.catholic-identity.save', $return) }}">
            @csrf
            <div class="form-grid">
                <label>pastoral contact<input name="pastoral_contact" value="{{ old('pastoral_contact', $item?->pastoral_contact) }}"></label>
                <label>mass frequency<input name="mass_frequency" value="{{ old('mass_frequency', $item?->mass_frequency) }}"></label>
                <label>retreats held<input type="number" min="0" name="retreats_held" value="{{ old('retreats_held', $item?->retreats_held ?? 0) }}"></label>
                <label>parish school engagements<input type="number" min="0" name="parish_school_engagements" value="{{ old('parish_school_engagements', $item?->parish_school_engagements ?? 0) }}"></label>
            </div>
            <div class="checkbox-grid">
                @foreach (['has_chaplaincy','catechesis_available','values_education_available','safeguarding_structure_available','counselling_structure_available'] as $field)
                    <label><input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $item?->{$field} ?? false))> {{ str_replace('_', ' ', $field) }}</label>
                @endforeach
            </div>
            <label class="wide-field">pastoral programmes<textarea name="pastoral_programmes">{{ old('pastoral_programmes', $item?->pastoral_programmes) }}</textarea></label>
            <label class="wide-field">notes<textarea name="notes">{{ old('notes', $item?->notes) }}</textarea></label>
            <div class="form-actions"><a class="button button-secondary" href="{{ route('returns.show', $return) }}">cancel</a><button class="button button-primary" type="submit">save catholic identity</button></div>
        </form>
    </x-ui.card>
</x-layouts.app>
