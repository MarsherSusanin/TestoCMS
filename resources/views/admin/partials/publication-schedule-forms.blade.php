@foreach($schedules as $schedule)
    @if(!$schedule->executed_at && !$schedule->cancelled_at)
        @can('publish', $entity)
            <form id="{{ $entityType }}-schedule-cancel-{{ $schedule->id }}" method="POST" action="{{ route('admin.'.$entityType.'s.schedules.cancel', [$entity, $schedule]) }}" hidden>
                @csrf
                @method('DELETE')
            </form>
        @endcan
    @endif
@endforeach
