@php
    $fieldId = trim((string) ($id ?? $name ?? 'asset_id'));
    $fieldName = trim((string) ($name ?? 'asset_id'));
    $fieldLabel = trim((string) ($label ?? 'Файл'));
    $fieldAccept = trim((string) ($accept ?? 'all'));
    $pickerTitle = trim((string) ($pickerTitle ?? 'Медиатека'));
    $pickerSubtitle = trim((string) ($pickerSubtitle ?? 'Выберите файл из Assets'));
    $pickerUploadTitle = trim((string) ($pickerUploadTitle ?? $pickerTitle));
    $pickerUploadSubtitle = trim((string) ($pickerUploadSubtitle ?? 'Загрузите файл и сразу выберите его'));
    $emptyLabel = trim((string) ($emptyLabel ?? 'Файл не выбран'));
    $assetsCollection = collect($assets ?? [])->filter();
    $selectedValue = trim((string) ($selectedValue ?? old($fieldName, '')));
    $selectedAsset = $selectedAsset ?? ($selectedValue !== '' ? $assetsCollection->firstWhere('id', (int) $selectedValue) : null);
@endphp

@once
    @push('head')
        <style>
            .asset-selector {
                display: grid;
                gap: 10px;
            }
            .asset-selector-ui {
                display: grid;
                gap: 10px;
                border: 1px solid #d0d5dd;
                border-radius: 14px;
                background: linear-gradient(180deg, #fcfdff, #f8fafc);
                padding: 12px;
            }
            .asset-selector-preview {
                display: grid;
                grid-template-columns: 72px minmax(0, 1fr);
                gap: 12px;
                align-items: center;
            }
            .asset-selector-thumb {
                width: 72px;
                height: 72px;
                border-radius: 12px;
                border: 1px solid #dbe3ef;
                background: #fff;
                overflow: hidden;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #667085;
                font-size: 12px;
                text-align: center;
            }
            .asset-selector-thumb img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }
            .asset-selector-meta {
                min-width: 0;
            }
            .asset-selector-title {
                margin: 0;
                font-size: 14px;
                font-weight: 700;
                line-height: 1.25;
                color: #101828;
            }
            .asset-selector-note {
                margin: 4px 0 0;
                font-size: 12px;
                color: #667085;
                line-height: 1.35;
                word-break: break-word;
            }
            .asset-selector-actions {
                display: flex;
                gap: 8px;
                flex-wrap: wrap;
            }
            .asset-selector-fallback[hidden] {
                display: none !important;
            }
        </style>
    @endpush
@endonce

<div
    class="asset-selector"
    data-asset-selector
    data-accept="{{ $fieldAccept }}"
    data-picker-title="{{ $pickerTitle }}"
    data-picker-subtitle="{{ $pickerSubtitle }}"
    data-picker-upload-title="{{ $pickerUploadTitle }}"
    data-picker-upload-subtitle="{{ $pickerUploadSubtitle }}"
    data-empty-label="{{ $emptyLabel }}"
>
    <label for="{{ $fieldId }}">{{ $fieldLabel }}</label>

    <div class="asset-selector-ui" data-asset-selector-ui hidden>
        <div class="asset-selector-preview" data-asset-selector-preview></div>
        <div class="asset-selector-actions">
            <button type="button" class="btn btn-small" data-asset-selector-open>Выбрать</button>
            <button type="button" class="btn btn-small" data-asset-selector-upload>Загрузить</button>
            <button type="button" class="btn btn-small" data-asset-selector-clear>Очистить</button>
        </div>
    </div>

    <div class="asset-selector-fallback" data-asset-selector-fallback>
        <select id="{{ $fieldId }}" name="{{ $fieldName }}" data-asset-selector-source>
            <option value="">— нет —</option>
            @foreach($assetsCollection as $asset)
                <option value="{{ $asset->id }}" @selected($selectedValue !== '' && $selectedValue === (string) $asset->id)>
                    #{{ $asset->id }} · {{ $asset->title ?: $asset->public_url ?: $asset->storage_path }}
                </option>
            @endforeach
        </select>
    </div>

    @if($selectedAsset instanceof \App\Models\Asset)
        <script type="application/json" data-asset-selector-current>@json(\App\Support\AdminAssetPickerPayload::fromAsset($selectedAsset))</script>
    @endif
</div>
