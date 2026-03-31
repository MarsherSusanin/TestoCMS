@extends('admin.layout')
@section('title', 'Услуги')
@section('content')
    @include('booking-module::admin.partials.header', [
        'title' => 'Услуги',
        'description' => 'Карточки услуг, локализованный контент, режим подтверждения и привязка к ресурсам.',
        'primaryAction' => [
            'type' => 'link',
            'label' => 'Новая услуга',
            'href' => route('booking.admin.services.create'),
            'class' => 'btn-primary',
        ],
    ])
    @if($services->count() === 0)
        <div class="empty-state">Услуг пока нет. Создайте первую услугу и настройте для неё слоты.</div>
    @else
        <section class="panel">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Услуга</th>
                        <th>Локация</th>
                        <th>Длительность</th>
                        <th>Подтверждение</th>
                        <th>Ресурсы</th>
                        <th>Статус</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($services as $service)
                        @php($translation = $service->current_translation)
                        <tr>
                            <td>#{{ $service->id }}</td>
                            <td>
                                <strong>{{ $translation?->title ?? '—' }}</strong>
                                <div class="muted mono">/{{ $translation?->slug ?? '' }}</div>
                            </td>
                            <td>{{ $service->location?->name ?? '—' }}</td>
                            <td>{{ $service->duration_minutes }} мин</td>
                            <td>{{ $service->confirmation_mode === 'instant' ? 'Мгновенно' : 'Вручную' }}</td>
                            <td>
                                <div>{{ $service->resource_selection_mode === 'choose_resource' ? 'Клиент выбирает' : 'Автоподбор' }}</div>
                                <div class="muted">{{ $service->resources->where('is_active', true)->count() }} активн.</div>
                            </td>
                            <td><span class="status-pill">{{ $service->is_active ? 'active' : 'disabled' }}</span></td>
                            <td>
                                @include('admin.partials.row-action-menu', [
                                    'primary' => [
                                        'type' => 'link',
                                        'label' => 'Редактировать',
                                        'href' => route('booking.admin.services.edit', $service),
                                    ],
                                    'items' => [[
                                        'type' => 'form',
                                        'label' => 'Удалить',
                                        'action' => route('booking.admin.services.destroy', $service),
                                        'method' => 'DELETE',
                                        'confirm' => 'Удалить услугу и все её слоты?',
                                        'danger' => true,
                                    ]],
                                    'menuLabel' => 'Действия с услугой',
                                ])
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $services->links() }}
        </section>
    @endif
@endsection
@push('head')@include('booking-module::admin.partials.styles')@endpush
