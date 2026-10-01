@extends('cms.layout')

@section('body_class', 'page-stage-preview')

@push('head')
    <style>
        .page-stage-preview .preview-banner { display: none; }
        .page-stage-preview .hero-shell { display: none; }
        .page-stage-preview .content-shell { width: 100%; }
        .page-stage-preview .surface { margin-top: 8px; }
        .page-stage-preview [data-builder-preview-root] { position: relative; }
        .page-stage-preview .cms-builder-node-wrapper { display: contents; }
        .page-stage-preview [data-builder-node-id],
        .page-stage-preview [data-builder-column-id] {
            position: relative;
        }
    </style>
@endpush

@section('content')
    @include('cms.partials.page-content', [
        'renderedHtml' => (string) ($stageRenderedHtml ?? $translation->rendered_html ?? ''),
        'builderPreview' => true,
    ])
@endsection
