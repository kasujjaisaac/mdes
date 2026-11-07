<section data-source-table="{{ $tableKey }}" class="enrolment-entry-section"><h3>{{ $table['label'] }}</h3>
<div class="table-wrap"><table class="data-table source-data-table enrolment-entry-table">
    <colgroup><col class="enrolment-class-col"><col span="{{ count($table['columns']) }}" class="enrolment-gender-col"><col class="enrolment-total-col"></colgroup>
    <thead>
        <tr><th scope="col" rowspan="2">No.</th><th scope="colgroup" colspan="{{ count($table['columns']) }}">Gender</th><th scope="col" rowspan="2">TOTAL</th></tr>
        <tr>@foreach($table['columns'] as $column)<th scope="col">{{ $column['display_label'] ?? $column['label'] }} <x-ui.required-mark/></th>@endforeach</tr>
    </thead>
    <tbody>
    @foreach($rowLabels as $rowKey => $label)
        <tr><th scope="row">{{ $label }}</th>
        @foreach($table['columns'] as $columnKey => $column)
            <td><x-ui.source-field :field="array_merge($column, ['label'=>$label.' '.($column['display_label'] ?? $column['label'])])" :name="'data['.$tableKey.']['.$rowKey.']['.$columnKey.']'" :value="$rows[$rowKey][$columnKey] ?? null" :disabled="!$editable" /></td>
        @endforeach
        <td class="source-total">{{ collect($rows[$rowKey] ?? [])->contains(fn($value) => $value !== null && $value !== '') ? collect($table['columns'])->keys()->sum(fn($column) => (int)($rows[$rowKey][$column] ?? 0)) : '-' }}</td></tr>
    @endforeach
    </tbody>
    <tfoot><tr><th scope="row">TOTAL</th>@foreach($table['columns'] as $columnKey => $column)<td>{{ collect($rows)->sum($columnKey) }}</td>@endforeach<td>{{ collect($rows)->sum(fn($row) => collect($table['columns'])->keys()->sum(fn($column) => (int)($row[$column] ?? 0))) }}</td></tr></tfoot>
</table></div>
</section>
