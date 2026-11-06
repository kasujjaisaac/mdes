@props(['label', 'value', 'meta' => null, 'tone' => 'blue'])

<article class="stat-card stat-{{ $tone }}">
    <div class="stat-symbol" aria-hidden="true"><x-ui.nav-icon :name="match($label) { 'registered schools' => 'school', 'active periods' => 'calendar', 'locked returns' => 'clipboard', 'quality alerts' => 'quality', 'learners captured' => 'users', 'filled staff' => 'staff', default => 'chart' }" /></div>
    <span>{{ $label }}</span>
    <strong>{{ $value }}</strong>
    @if ($meta)
        <small>{{ $meta }}</small>
    @endif
</article>
