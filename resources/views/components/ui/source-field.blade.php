@props(['field', 'name', 'value' => null, 'disabled' => false])
<?php $errorKey = trim(str_replace(['][', '[', ']'], ['.', '.', ''], $name), '.'); $errorId='field-error-'.md5($name); ?>
@if($field['type'] === 'select')
<select id="control-{{ md5($name) }}" name="{{ $name }}" @if($errors->has($errorKey)) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif aria-label="{{ $field['label'] }}" @disabled($disabled)><option value="">Not recorded / not applicable</option>@foreach($field['options'] as $option)<option value="{{ $option }}" @selected($value === $option)>{{ $option }}</option>@endforeach</select>
@elseif($field['type'] === 'multiple')
<div class="source-checkboxes">@foreach($field['options'] as $option)<label><input type="checkbox" name="{{ $name }}[]" value="{{ $option }}" @checked(in_array($option, (array) $value)) @disabled($disabled)> {{ $option }}</label>@endforeach</div>
@elseif($field['type'] === 'textarea')
<textarea id="control-{{ md5($name) }}" name="{{ $name }}" @if($errors->has($errorKey)) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif aria-label="{{ $field['label'] }}" rows="2" maxlength="3000" @disabled($disabled)>{{ $value }}</textarea>
@else
<input id="control-{{ md5($name) }}" name="{{ $name }}" @if($errors->has($errorKey)) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif aria-label="{{ $field['label'] }}" value="{{ $value }}" type="{{ in_array($field['type'], ['integer','decimal','age']) ? 'number' : $field['type'] }}" @if(in_array($field['type'], ['integer','decimal','age'])) min="{{ $field['type'] === 'age' ? 16 : 0 }}" step="{{ $field['type'] === 'decimal' ? 'any' : 1 }}" @else maxlength="255" @endif @disabled($disabled)>
@endif

@error($errorKey)<small id="{{ $errorId }}" class="source-inline-error">{{ $message }}</small>@enderror
