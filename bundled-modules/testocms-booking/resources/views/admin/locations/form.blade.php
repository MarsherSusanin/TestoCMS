@extends('admin.layout')
@section('title', $isEdit ? 'Редактирование локации' : 'Новая локация')
@section('content')
    @include('booking-module::admin.partials.header', [
        'title' => $isEdit ? 'Редактирование локации' : 'Новая локация',
        'description' => 'Локация задаёт timezone и контактные данные для услуги.',
        'actions' => '<a class="btn" href="'.route('booking.admin.locations.index').'">Назад</a>',
    ])
    <form method="POST" action="{{ $isEdit ? route('booking.admin.locations.update', $location) : route('booking.admin.locations.store') }}" class="panel booking-stack">@csrf @if($isEdit) @method('PUT') @endif
        <div class="booking-form-grid">
            <label><span>Название</span><input type="text" name="name" value="{{ old('name', $location->name) }}" required></label>
            <label><span>Timezone</span><input type="text" name="timezone" value="{{ old('timezone', $location->timezone) }}" required></label>
            <label class="full"><span>Адрес</span><input type="text" name="address" value="{{ old('address', $location->address) }}"></label>
            <label><span>Email</span><input type="email" name="contact_email" value="{{ old('contact_email', $location->contact_email) }}"></label>
            <label><span>Телефон</span><input type="text" name="contact_phone" value="{{ old('contact_phone', $location->contact_phone) }}"></label>
            <label class="full"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $location->is_active))> Активна</label>
        </div>
        <div class="inline-actions"><button class="btn btn-primary" type="submit">{{ $isEdit ? 'Сохранить' : 'Создать' }}</button></div>
    </form>
@endsection
@push('head')@include('booking-module::admin.partials.styles')@endpush
