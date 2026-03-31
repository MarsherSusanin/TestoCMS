<form class="site-search-form" method="GET" action="{{ $action }}">
    <input
        type="search"
        name="q"
        value="{{ $value ?? '' }}"
        placeholder="{{ $placeholder }}"
        minlength="{{ $minLength }}"
    >
    <input type="hidden" name="type" value="{{ $scopeDefault }}">
    <button type="submit">{{ $submitLabel }}</button>
</form>
