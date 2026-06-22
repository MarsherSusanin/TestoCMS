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
    <article class="surface">
        <div class="surface-body">
            <div class="content-prose" data-builder-preview-root>
                {!! (string) ($stageRenderedHtml ?? ($translation->rendered_html ?? '')) !!}
            </div>
        </div>
    </article>
@endsection
