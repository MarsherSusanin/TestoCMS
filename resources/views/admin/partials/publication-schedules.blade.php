@if($entity->status === 'scheduled' && !$entity->schedules()->pending()->where('action', 'publish')->exists())
    <div class="alert alert-warning" role="alert">Нужно проверить расписание: материал скрыт и не имеет активной задачи публикации. Автоматическая повторная публикация не выполняется.</div>
@endif
@forelse($schedules as $schedule)
    <div class="panel" style="padding:10px; margin-bottom:8px;">
        <div>#{{ $schedule->id }} · {{ $schedule->action === 'publish' ? 'Публикация' : 'Снятие с публикации' }} · {{ $schedule->due_at->format('d.m.Y H:i') }} ({{ config('app.timezone') }})</div>
        <div class="muted" style="font-size:12px;">
            @if($schedule->cancelled_at)
                Отменено {{ $schedule->cancelled_at->format('d.m.Y H:i') }} · {{ $schedule->cancellation_reason }}
            @elseif($schedule->executed_at)
                Выполнено {{ $schedule->executed_at->format('d.m.Y H:i') }}
            @else
                Ожидает выполнения
            @endif
        </div>
        @if(!$schedule->executed_at && !$schedule->cancelled_at)
            @can('publish', $entity)
                <button type="submit" form="{{ $entityType }}-schedule-cancel-{{ $schedule->id }}" class="btn btn-secondary" style="margin-top:6px;">Отменить расписание</button>
            @endcan
        @endif
    </div>
@empty
    <p class="muted">Расписаний пока нет.</p>
@endforelse
