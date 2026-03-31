@extends('admin.layout')
@section('title', 'Локации')
@section('content')
    @include('booking-module::admin.partials.header', [
        'title' => 'Локации',
        'description' => 'Где оказываются услуги и в каком часовом поясе работают слоты.',
        'actions' => '<a class="btn btn-primary" href="'.route('booking.admin.locations.create').'">Новая локация</a>',
    ])
    @if($locations->count() === 0)
        <div class="empty-state">Локаций пока нет. Добавьте первую локацию, чтобы привязывать к ней услуги и ресурсы.</div>
    @else
        <section class="panel"><table><thead><tr><th>ID</th><th>Название</th><th>Timezone</th><th>Контакты</th><th>Статус</th><th></th></tr></thead><tbody>@foreach($locations as $location)<tr><td>#{{ $location->id }}</td><td>{{ $location->name }}</td><td>{{ $location->timezone }}</td><td>{{ $location->contact_email ?: $location->contact_phone ?: '—' }}</td><td><span class="status-pill">{{ $location->is_active ? 'active' : 'disabled' }}</span></td><td><div class="inline-actions"><a class="btn" href="{{ route('booking.admin.locations.edit', $location) }}">Редактировать</a><form method="POST" action="{{ route('booking.admin.locations.destroy', $location) }}" data-confirm="Удалить локацию?">@csrf @method('DELETE')<button class="btn btn-danger" type="submit">Удалить</button></form></div></td></tr>@endforeach</tbody></table>{{ $locations->links() }}</section>
    @endif
@endsection
@push('head')@include('booking-module::admin.partials.styles')@endpush
