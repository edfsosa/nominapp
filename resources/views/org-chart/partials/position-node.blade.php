<li>
    <div class="position-node">
        <div class="position-header">{{ $position['name'] }}</div>
        <div class="position-employees">
            @if (count($position['employees']) > 0)
                @foreach ($position['employees'] as $employee)
                    <div class="employee-item">
                        @if ($employee['photo'])
                            <img src="{{ $employee['photo'] }}" alt="{{ $employee['name'] }}" class="employee-photo">
                        @else
                            <div class="employee-photo-placeholder">
                                {{ strtoupper(substr($employee['name'], 0, 1)) }}
                            </div>
                        @endif
                        <span class="employee-name">{{ $employee['name'] }}</span>
                    </div>
                @endforeach
            @else
                <div class="no-employees">Sin empleados</div>
            @endif
        </div>
    </div>

    @if (count($position['children']) > 0)
        <ul>
            @foreach ($position['children'] as $child)
                @include('org-chart.partials.position-node', ['position' => $child])
            @endforeach
        </ul>
    @endif
</li>
