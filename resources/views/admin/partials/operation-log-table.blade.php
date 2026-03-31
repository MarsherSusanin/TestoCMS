@php
    $operationHeaders = is_iterable($headers ?? null) ? $headers : [];
    $operationLogs = is_iterable($logs ?? null) ? $logs : [];
    $operationEmptyMessage = (string) ($emptyMessage ?? 'Записей пока нет.');
    $operationRowView = (string) ($rowView ?? '');
    $operationRowData = is_array($rowData ?? null) ? $rowData : [];
@endphp

@if(blank($operationLogs))
    <p class="muted">{{ $operationEmptyMessage }}</p>
@else
    <table class="{{ $tableClass ?? '' }}">
        <thead>
        <tr>
            @foreach($operationHeaders as $header)
                <th>{{ $header }}</th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @foreach($operationLogs as $log)
            @include($operationRowView, array_merge($operationRowData, ['log' => $log]))
        @endforeach
        </tbody>
    </table>
@endif
