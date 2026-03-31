@extends('admin.layout')
@section('title', $isEdit ? 'Редактирование ресурса' : 'Новый ресурс')
@section('content')
    @include('booking-module::admin.partials.header', [
        'title' => $isEdit ? 'Редактирование ресурса' : 'Новый ресурс',
        'description' => 'Ресурс можно использовать как специалиста, комнату или оборудование.',
        'actions' => '<a class="btn" href="'.route('booking.admin.resources.index').'">Назад</a>',
    ])
    <form method="POST" action="{{ $isEdit ? route('booking.admin.resources.update', $resource) : route('booking.admin.resources.store') }}" class="panel booking-stack">@csrf @if($isEdit) @method('PUT') @endif
        <div class="booking-form-grid">
            <label><span>Название</span><input type="text" name="name" value="{{ old('name', $resource->name) }}" required></label>
            <label><span>Тип ресурса</span><input type="text" name="resource_type" value="{{ old('resource_type', $resource->resource_type) }}" required></label>
            <label><span>Локация</span><select name="location_id"><option value="">—</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected((string) old('location_id', $resource->location_id) === (string) $location->id)>{{ $location->name }}</option>@endforeach</select></label>
            <label><span>Вместимость</span><input type="number" min="1" name="capacity" value="{{ old('capacity', $resource->capacity) }}" required></label>
            <label class="full"><span>Описание</span><textarea name="description" rows="5">{{ old('description', $resource->description) }}</textarea></label>
            <label class="full"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $resource->is_active))> Активен</label>
        </div>
        <div class="inline-actions"><button class="btn btn-primary" type="submit">{{ $isEdit ? 'Сохранить' : 'Создать' }}</button></div>
    </form>
@endsection
@push('head')@include('booking-module::admin.partials.styles')@endpush
