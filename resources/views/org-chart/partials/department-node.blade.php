<li>
    <div class="department-node">
        <div class="department-header">{{ $department['name'] }}</div>
    </div>

    @if (count($department['positions']) > 0)
        <ul>
            @foreach ($department['positions'] as $position)
                @include('org-chart.partials.position-node', ['position' => $position])
            @endforeach
        </ul>
    @endif
</li>
