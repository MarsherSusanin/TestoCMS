@php
    $mediaPickerAssets = \App\Support\AdminAssetPickerPayload::collect(collect($mediaPickerAssets ?? [])->filter());
    $mediaPickerConfig = [
        'upload_url' => url('/api/admin/v1/assets'),
        'can_upload' => (bool) optional(auth()->user())->can('create', \App\Models\Asset::class),
    ];
@endphp

@once
    @push('head')
        @include('admin.partials.media-picker-styles')
    @endpush

    @include('admin.partials.media-picker-modal')
    @include('admin.partials.media-picker-assets', ['mediaPickerAssets' => $mediaPickerAssets])
    <script type="application/json" id="testocms-media-picker-config">@json($mediaPickerConfig)</script>
    <script src="{{ route('admin.runtime.show', ['runtime' => 'media-picker.js']) }}"></script>
    <script src="{{ route('admin.runtime.show', ['runtime' => 'asset-selector.js']) }}"></script>
@endonce
