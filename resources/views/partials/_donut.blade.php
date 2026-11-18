{{-- SVG donut/pie chart. Expects: $rows = [{name,n}], $palette, $total, $thick, $size --}}
@php
    $N = fn ($v) => \App\Support\IssuePresenter::num($v);
    $c = $size / 2;
@endphp
<div style="margin-top:12px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
    <svg viewBox="0 0 {{ $size }} {{ $size }}" style="width:150px;height:150px;flex-shrink:0;" role="img">
        <circle cx="{{ $c }}" cy="{{ $c }}" r="15.9155" fill="none" stroke="#f1f5f9" stroke-width="{{ $thick }}"></circle>
        @php $off = 25; @endphp
        @foreach ($rows as $ci => $row)
            @php $pct = $total > 0 ? $row['n'] / $total * 100 : 0; @endphp
            <circle cx="{{ $c }}" cy="{{ $c }}" r="15.9155" fill="none"
                    stroke="{{ $palette[$ci % count($palette)] }}" stroke-width="{{ $thick }}"
                    stroke-dasharray="{{ $pct }} {{ 100 - $pct }}" stroke-dashoffset="{{ $off }}"></circle>
            @php $off -= $pct; @endphp
        @endforeach
        @if ($thick < 20)
            <text x="{{ $c }}" y="{{ $c }}" text-anchor="middle" dominant-baseline="central" style="font-size:7px;font-weight:800;fill:#0f172a;">{{ $N($total) }}</text>
        @endif
    </svg>
    <div style="flex:1;min-width:160px;display:flex;flex-direction:column;gap:6px;">
        @foreach ($rows as $ci => $row)
            <div style="display:flex;align-items:center;gap:8px;font-size:13px;">
                <span style="width:10px;height:10px;border-radius:3px;background:{{ $palette[$ci % count($palette)] }};flex-shrink:0;"></span>
                <span style="color:#334155;font-weight:600;flex:1;">{{ $row['name'] }}</span>
                <strong style="color:#0f172a;">{{ $N($row['n']) }}</strong>
            </div>
        @endforeach
    </div>
</div>
