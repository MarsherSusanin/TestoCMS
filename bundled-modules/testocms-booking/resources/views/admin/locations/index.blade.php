@extends('admin.layout')
@section('title', 'Локации')
@section('content')
    @include('booking-module::admin.partials.header', [
        'title' => 'Локации',
        'description' => 'Где оказываются услуги и в каком часовом поясе работают слоты.',
        'primaryAction' => [
            'type' => 'link',
            'label' => 'Новая локация',
            'href' => route('booking.admin.locations.create'),
            'class' => 'btn-primary',
        ],
    ])
    @if($locations->count() === 0)
        <div class="empty-state">Локаций пока нет. Добавьте первую локацию, чтобы привязывать к ней услуги и ресурсы.</div>
    @else
        <section class="panel">
            <table>
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Название</th>
                    <th>Timezone</th>
                    <th>Контакты</th>
                    <th>Статус</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($locations as $location)
                    <tr>
                        <td>#{{ $location->id }}</td>
                        <td>{{ $location->name }}</td>
                        <td>{{ $location->timezone }}</td>
                        <td>{{ $location->contact_email ?: $location->contact_phone ?: '—' }}</td>
                        <td><span class="status-pill">{{ $location->is_active ? 'active' : 'disabled' }}</span></td>
                        <td>
                            @include('admin.partials.row-action-menu', [
                                'primary' => [
                                    'type' => 'link',
                                    'label' => 'Редактировать',
                                    'href' => route('booking.admin.locations.edit', $location),
                                ],
                                'items' => [[
                                    'type' => 'form',
                                    'label' => 'Удалить',
                                    'action' => route('booking.admin.locations.destroy', $location),
                                    'method' => 'DELETE',
                                    'confirm' => 'Удалить локацию?',
                                    'danger' => true,
                                ]],
                                'menuLabel' => 'Действия с локацией',
                            ])
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            {{ $locations->links() }}
        </section>
    @endif
@endsection
@push('head')@include('booking-module::admin.partials.styles')@endpush
