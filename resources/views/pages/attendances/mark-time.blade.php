@php
    $regularizedLunch = $row['adjusted'] && in_array($type, ['break_out', 'break_in'], true);
    $displayTime = $regularizedLunch
        ? ($type === 'break_out' ? $row['adjustment']->lunch_start : $row['adjustment']->lunch_end)
        : $mark?->effectiveTime();
@endphp
<span class="{{ $displayTime ? 'fw-semibold' : 'text-muted' }}">{{ $time($displayTime) }}</span>
@if($regularizedLunch)
    <div class="small text-muted">Regularizado · {{ $mark ? 'Original: '.$time($mark->mark_time) : 'Sin marcación original' }}</div>
@elseif($mark?->is_manual)
    <div class="small text-muted">Registro manual</div>
@elseif($mark?->corrected_time)
    <div class="small text-muted">Corregido · Original: {{ $time($mark->mark_time) }}</div>
@endif
