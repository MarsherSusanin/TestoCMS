@extends('cms.layout')

@section('body_class', ($translation->slug ?? '') === 'home' ? 'page-home' : 'page-standard')

@section('content')
    @include('cms.partials.page-content', ['renderedHtml' => (string) ($publicRenderedHtml ?? $translation->rendered_html ?? '')])
@endsection
