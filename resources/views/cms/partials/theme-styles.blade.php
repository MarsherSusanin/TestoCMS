@if(!empty($cms['theme_base_css_url']))
    <link rel="stylesheet" href="{{ $cms['theme_base_css_url'] }}">
@endif
@if(!empty($cms['theme_css_dynamic']))
    <style>
{!! $cms['theme_css_dynamic'] !!}
    </style>
@elseif(!empty($cms['theme_css']))
    <style>
{!! $cms['theme_css'] !!}
    </style>
@endif
