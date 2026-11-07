<x-layouts.app :title="$title" :hide-heading="true">
<article class="questionnaire-introduction">
    <header><small>{{ $introduction['edition'] }}</small><h1>{{ $introduction['header'] }}</h1><p>{{ $introduction['contact'] }}</p><h2>{{ $introduction['title'] }}</h2><p class="questionnaire-school-name">{{ $return->school->official_name }}</p></header>
    <div class="questionnaire-introduction-body">
        <p>{{ $introduction['introduction'] }}</p>
        <h3>{{ $introduction['objectives_title'] }}</h3>
        @foreach($introduction['objectives'] as $objective)<p class="questionnaire-objective">{{ $objective }}</p>@endforeach
    </div>
    <footer><a class="button" href="{{ route('returns.form.source', $return) }}" target="_blank" rel="noopener">View original questionnaire</a><a class="button button-primary" href="{{ auth()->user()->isSchoolRepresentative() ? ($return->profile_confirmed_at ? route('returns.form.show', $return) : route('representative.confirm', $return)) : route('returns.show', $return) }}">Continue</a></footer>
</article>
</x-layouts.app>
