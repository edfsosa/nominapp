@php
    $indentClass = 'indent-' . min($level, 4);
    $levelPrefix = str_repeat('—', $level);
@endphp

<tr class="position-row">
    <td class="{{ $indentClass }}">
        @if ($level > 0)
            <span class="hierarchy-line">{{ $levelPrefix }}</span>
        @endif
        <span class="position-name">{{ $position['name'] }}</span>
        @if ($level > 0)
            <span class="level-indicator">(Nivel {{ $level }})</span>
        @endif
    </td>
    <td>
        <span class="department-badge">{{ $position['department'] }}</span>
    </td>
    <td>
        @if (count($position['employees']) > 0)
            @foreach ($position['employees'] as $employee)
                <div class="employee-item">
                    <div class="employee-photo-cell">
                        @if ($employee['photo'])
                            <img src="{{ $employee['photo'] }}" class="employee-photo" alt="">
                        @else
                            <div class="employee-photo-placeholder">
                                {{ strtoupper(substr($employee['name'], 0, 1)) }}
                            </div>
                        @endif
                    </div>
                    <div class="employee-info-cell">
                        <span class="employee-name">{{ $employee['name'] }}</span>
                    </div>
                </div>
            @endforeach
        @else
            <span class="no-employees">Sin empleados asignados</span>
        @endif
    </td>
</tr>

@foreach ($position['children'] as $child)
    @include('org-chart.partials.position-row-pdf', ['position' => $child, 'level' => $level + 1])
@endforeach
