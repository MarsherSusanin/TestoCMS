@extends('admin.layout')
@section('title', 'Ресурсы')
@section('content')
    @include('booking-module::admin.partials.header', [
        'title' => 'Ресурсы',
        'description' => 'Сотрудники, комнаты и оборудование, к которым привязываются услуги и слоты.',
        'primaryAction' => [
            'type' => 'link',
            'label' => 'Новый ресурс',
            'href' => route('booking.admin.resources.create'),
            'class' => 'btn-primary',
        ],
    ])
    @if($resources->count() === 0)
        <div class="empty-state">Ресурсов пока нет. Добавьте хотя бы один ресурс для планирования слотов.</div>
    @else
        <section class="panel">
            <table>
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Название</th>
                    <th>Тип</th>
                    <th>Локация</th>
                    <th>Вместимость</th>
                    <th>Статус</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($resources as $resource)
                    <tr>
                        <td>#{{ $resource->id }}</td>
                        <td>{{ $resource->name }}</td>
                        <td>{{ $resource->resource_type }}</td>
                        <td>{{ $resource->location?->name ?? '—' }}</td>
                        <td>{{ $resource->capacity }}</td>
                        <td><span class="status-pill">{{ $resource->is_active ? 'active' : 'disabled' }}</span></td>
                        <td>
                            @include('admin.partials.row-action-menu', [
                                'primary' => [
                                    'type' => 'link',
                                    'label' => 'Редактировать',
                                    'href' => route('booking.admin.resources.edit', $resource),
                                ],
                                'items' => [[
                                    'type' => 'form',
                                    'label' => 'Удалить',
                                    'action' => route('booking.admin.resources.destroy', $resource),
                                    'method' => 'DELETE',
                                    'confirm' => 'Удалить ресурс?',
                                    'danger' => true,
                                ]],
                                'menuLabel' => 'Действия с ресурсом',
                            ])
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            {{ $resources->links() }}
        </section>
    @endif
@endsection
@push('head')@include('booking-module::admin.partials.styles')@endpush
