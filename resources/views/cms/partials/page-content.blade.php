<article class="surface">
    <div class="surface-body">
        <div class="content-prose" @if($builderPreview ?? false) data-builder-preview-root @endif>
            {!! $renderedHtml !!}
        </div>
    </div>
</article>
