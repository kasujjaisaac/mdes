<x-layouts.app :title="$title" :breadcrumbs="[['label' => 'returns', 'url' => route('returns.show', $return)], ['label' => 'enrolment']]">
    <x-ui.card title="enrolment" subtitle="aggregated learner counts by class">
        @if ($errors->any()) <x-ui.alert tone="info">{{ $errors->first() }}</x-ui.alert> @endif
        <form class="resource-form" method="POST" action="{{ route('returns.enrolment.save', $return) }}">
            @csrf
            @php($rows = old('rows', $return->enrolmentStatistics->isNotEmpty() ? $return->enrolmentStatistics->toArray() : [['class_level' => 'primary one']]))
            <x-ui.table :headers="['class', 'boys', 'girls', 'day', 'boarding', 'repeaters', 'dropouts']">
                @foreach ($rows as $index => $row)
                    <tr>
                        <td><input name="rows[{{ $index }}][class_level]" value="{{ $row['class_level'] ?? '' }}" required></td>
                        <td><input type="number" min="0" name="rows[{{ $index }}][boys]" value="{{ $row['boys'] ?? 0 }}"></td>
                        <td><input type="number" min="0" name="rows[{{ $index }}][girls]" value="{{ $row['girls'] ?? 0 }}"></td>
                        <td><input type="number" min="0" name="rows[{{ $index }}][day_scholars]" value="{{ $row['day_scholars'] ?? 0 }}"></td>
                        <td><input type="number" min="0" name="rows[{{ $index }}][boarders]" value="{{ $row['boarders'] ?? 0 }}"></td>
                        <td><input type="number" min="0" name="rows[{{ $index }}][repeaters]" value="{{ $row['repeaters'] ?? 0 }}"></td>
                        <td><input type="number" min="0" name="rows[{{ $index }}][dropouts]" value="{{ $row['dropouts'] ?? 0 }}"></td>
                    </tr>
                @endforeach
            </x-ui.table>
            <div class="form-actions">
                <a class="button button-secondary" href="{{ route('returns.show', $return) }}">cancel</a>
                <button class="button button-primary" type="submit">save enrolment</button>
            </div>
        </form>
    </x-ui.card>
</x-layouts.app>
