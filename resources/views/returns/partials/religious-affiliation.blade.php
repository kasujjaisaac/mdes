<section class="religion-entry-section" data-source-table="community">
    <h3>{{ $table['label'] }}</h3><p class="questionnaire-source-description">{{ $table['description'] }}</p>
    <div class="table-wrap"><table class="data-table source-data-table religion-entry-table">
        <thead><tr><th scope="col">Religious affiliation</th>@foreach($table['rows'] as $groupLabel)<th scope="col">{{ $groupLabel }}</th>@endforeach<th scope="col">Total</th></tr></thead>
        <tbody>
        @foreach($table['columns'] as $religion => $column)
            <tr><th scope="row">{{ $column['label'] }}</th>
            @foreach($table['rows'] as $group => $groupLabel)
                <td>@if($editable)<x-ui.source-field :field="array_merge($column, ['label' => $groupLabel.' - '.$column['label']])" :name="'data[community]['.$group.']['.$religion.']'" :value="$rows[$group][$religion] ?? null"/>@else{{ $rows[$group][$religion] ?? 'Not recorded' }}@endif</td>
            @endforeach
            <td class="source-total">{{ collect($table['rows'])->keys()->sum(fn($group) => (int)($rows[$group][$religion] ?? 0)) }}</td></tr>
        @endforeach
        </tbody>
        <tfoot><tr><th scope="row">Total</th>@foreach($table['rows'] as $group => $groupLabel)<td>{{ collect($rows[$group] ?? [])->sum() }}</td>@endforeach<td>{{ collect($rows)->sum(fn($row) => collect($row)->sum()) }}</td></tr></tfoot>
    </table></div>
</section>
