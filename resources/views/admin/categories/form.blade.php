@extends('admin.layout')

@section('title', $isEdit ? 'Редактирование категории' : 'Создание категории')

@section('content')
    <div class="page-header">
        <div>
            <h1>{{ $isEdit ? 'Редактирование категории' : 'Создание категории' }}</h1>
            <p>{{ $isEdit ? 'Категория #'.$category->id : 'Создание локализованной категории.' }}</p>
        </div>
        <div class="actions">
            <a href="{{ route('admin.categories.index') }}" class="btn">Назад к категориям</a>
        </div>
    </div>

    <form method="POST" action="{{ $isEdit ? route('admin.categories.update', $category) : route('admin.categories.store') }}">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif

        <div class="split">
            <section class="panel">
                <h2 style="margin-top:0;">Локализованный контент</h2>

                @foreach($locales as $locale)
                    @php $t = $translationsByLocale[$locale] ?? null; @endphp
                    <details class="panel" style="margin-top:12px;" {{ $loop->first ? 'open' : '' }}>
                        <summary>Перевод {{ strtoupper($locale) }}</summary>
                        <div style="margin-top:12px;">
                            <div class="grid cols-2">
                                <div class="field">
                                    <label>Title</label>
                                    <input type="text" name="translations[{{ $locale }}][title]" value="{{ old('translations.'.$locale.'.title', $t?->title ?? '') }}">
                                </div>
                                <div class="field">
                                    <label>Slug</label>
                                    <input type="text" name="translations[{{ $locale }}][slug]" value="{{ old('translations.'.$locale.'.slug', $t?->slug ?? '') }}">
                                </div>
                            </div>
                            <div class="field">
                                <label>Описание</label>
                                <textarea name="translations[{{ $locale }}][description]" rows="4">{{ old('translations.'.$locale.'.description', $t?->description ?? '') }}</textarea>
                            </div>
                            <div class="grid cols-2">
                                <div class="field">
                                    <label>Meta Title</label>
                                    <input type="text" name="translations[{{ $locale }}][meta_title]" value="{{ old('translations.'.$locale.'.meta_title', $t?->meta_title ?? '') }}">
                                </div>
                                <div class="field">
                                    <label>Canonical URL</label>
                                    <input type="text" name="translations[{{ $locale }}][canonical_url]" value="{{ old('translations.'.$locale.'.canonical_url', $t?->canonical_url ?? '') }}">
                                </div>
                            </div>
                            <div class="field">
                                <label>Meta Description</label>
                                <textarea name="translations[{{ $locale }}][meta_description]" rows="3">{{ old('translations.'.$locale.'.meta_description', $t?->meta_description ?? '') }}</textarea>
                            </div>
                        </div>
                    </details>
                @endforeach
            </section>

            <div>
                <section class="panel">
                    <h2 style="margin-top:0;">Настройки категории</h2>
                    <div class="field">
                        <label for="parent_id">Родительская категория</label>
                        <select id="parent_id" name="parent_id">
                            <option value="">— нет —</option>
                            @foreach($allCategories as $candidate)
                                @php $ct = $candidate->translations->firstWhere('locale', config('cms.default_locale', 'en')) ?? $candidate->translations->first(); @endphp
                                <option value="{{ $candidate->id }}" @selected((string) old('parent_id', $category->parent_id) === (string) $candidate->id)>#{{ $candidate->id }} {{ $ct?->title ?? 'Untitled' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        @php
                            $coverAssetValue = old('cover_asset_id', $category->cover_asset_id);
                        @endphp
                        @include('admin.partials.asset-selector', [
                            'name' => 'cover_asset_id',
                            'id' => 'cover_asset_id',
                            'label' => 'Обложка (файл)',
                            'assets' => $assets,
                            'selectedValue' => $coverAssetValue,
                            'selectedAsset' => $assets->firstWhere('id', (int) $coverAssetValue),
                            'accept' => 'image',
                            'pickerTitle' => 'Выбор обложки категории',
                            'pickerSubtitle' => 'Выберите изображение из Files для обложки категории.',
                            'pickerUploadTitle' => 'Загрузка обложки категории',
                            'pickerUploadSubtitle' => 'Загрузите изображение и сразу назначьте его категории.',
                            'emptyLabel' => 'Обложка не выбрана',
                        ])
                    </div>
                    <div class="field">
                        <label class="checkbox">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $category->is_active ?? true))>
                            Активная категория (видна на публичном сайте)
                        </label>
                    </div>
                    <div class="actions">
                        <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Сохранить изменения' : 'Создать категорию' }}</button>
                    </div>
                </section>
            </div>
        </div>
    </form>

    @if($isEdit)
        <section class="panel" style="margin-top:14px;">
            <h2 style="margin-top:0; color:#b42318;">Опасная зона</h2>
            <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" data-confirm="Удалить эту категорию?">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger">Удалить категорию</button>
            </form>
        </section>
    @endif

    @include('admin.partials.media-picker', ['mediaPickerAssets' => $assets])
@endsection
