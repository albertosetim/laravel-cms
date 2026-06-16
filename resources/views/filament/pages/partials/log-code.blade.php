@props([
    'content' => '',
    'title' => null,
])

@php
    $lines = preg_split('/\r\n|\r|\n/', (string) $content);
    $mono = "ui-monospace, SFMono-Regular, 'SF Mono', Menlo, Consolas, 'Liberation Mono', monospace";
@endphp

<div style="overflow:hidden;border-radius:0.75rem;border:1px solid #30363d;background:#0d1117;">
    <div style="display:flex;align-items:center;gap:0.5rem;padding:0.625rem 1rem;border-bottom:1px solid #21262d;background:#161b22;">
        <span style="display:flex;gap:0.375rem;">
            <span style="width:0.75rem;height:0.75rem;border-radius:9999px;background:#ff5f56;"></span>
            <span style="width:0.75rem;height:0.75rem;border-radius:9999px;background:#ffbd2e;"></span>
            <span style="width:0.75rem;height:0.75rem;border-radius:9999px;background:#27c93f;"></span>
        </span>
        @if ($title)
            <span style="margin-left:0.5rem;font-family:{{ $mono }};font-size:0.75rem;color:#8b949e;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $title }}</span>
        @endif
    </div>

    <div style="max-height:60vh;overflow:auto;">
        <table style="width:100%;border-collapse:collapse;font-family:{{ $mono }};font-size:0.75rem;line-height:1.6;">
            <tbody>
                @foreach ($lines as $i => $line)
                    @php
                        $isFrame = preg_match('/^\s*#\d+\s/', $line) === 1;
                        $isHeader = str_contains($line, 'Stack trace:');
                        $color = $isFrame ? '#79c0ff' : ($isHeader ? '#f0883e' : '#c9d1d9');
                    @endphp
                    <tr>
                        <td style="user-select:none;white-space:nowrap;border-right:1px solid #21262d;padding:0.0625rem 0.75rem;text-align:right;vertical-align:top;color:#6e7681;font-variant-numeric:tabular-nums;">{{ $i + 1 }}</td>
                        <td style="white-space:pre;padding:0.0625rem 1rem;vertical-align:top;color:{{ $color }};{{ $isHeader ? 'font-weight:600;' : '' }}">{{ $line === '' ? ' ' : $line }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
