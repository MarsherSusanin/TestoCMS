<section class="cms-a11y-ribbon" id="cms-a11y-ribbon" data-a11y-ui data-a11y-ribbon aria-label="{{ $a11y['labels']['panel_title'] }}">
    <div class="cms-a11y-ribbon__inner">
        <div class="cms-a11y-group cms-a11y-group--compact">
            <span class="cms-a11y-group__title">{{ $a11y['labels']['font_size'] }}</span>
            <div class="cms-a11y-stepper" role="group" aria-label="{{ $a11y['labels']['font_size'] }}">
                <button type="button" data-a11y-action="font-decrease" aria-label="{{ $a11y['labels']['font_size_decrease'] }}">A-</button>
                <output data-a11y-readout="fontScale">{{ $a11y['default_state']['fontScale'] }}%</output>
                <button type="button" data-a11y-action="font-increase" aria-label="{{ $a11y['labels']['font_size_increase'] }}">A+</button>
            </div>
        </div>

        <div class="cms-a11y-group">
            <span class="cms-a11y-group__title">{{ $a11y['labels']['contrast'] }}</span>
            <div class="cms-a11y-chip-group">
                @foreach($a11y['contrast_presets'] as $key => $preset)
                    <button type="button" data-a11y-setting="contrast" data-a11y-value="{{ $key }}">{{ $preset['label'] }}</button>
                @endforeach
            </div>
        </div>

        <div class="cms-a11y-group">
            <span class="cms-a11y-group__title">{{ $a11y['labels']['images'] }}</span>
            <div class="cms-a11y-chip-group">
                @foreach($a11y['image_modes'] as $key => $mode)
                    <button type="button" data-a11y-setting="imageMode" data-a11y-value="{{ $key }}">{{ $mode['label'] }}</button>
                @endforeach
            </div>
        </div>

        <div class="cms-a11y-group">
            <span class="cms-a11y-group__title">{{ $a11y['labels']['speech'] }}</span>
            <div class="cms-a11y-chip-group">
                <button type="button" data-a11y-setting="speechEnabled" data-a11y-value="1">{{ $a11y['labels']['on'] }}</button>
                <button type="button" data-a11y-setting="speechEnabled" data-a11y-value="0">{{ $a11y['labels']['off'] }}</button>
            </div>
            <div class="cms-a11y-inline-actions">
                <button type="button" data-a11y-action="speech-play">{{ $a11y['labels']['play'] }}</button>
                <button type="button" data-a11y-action="speech-pause">{{ $a11y['labels']['pause'] }}</button>
                <button type="button" data-a11y-action="speech-stop">{{ $a11y['labels']['stop'] }}</button>
            </div>
            <div class="cms-a11y-status" data-a11y-speech-status>{{ $a11y['labels']['speech_status_off'] }}</div>
        </div>

        <div class="cms-a11y-group">
            <span class="cms-a11y-group__title">{{ $a11y['labels']['letter_spacing'] }}</span>
            <div class="cms-a11y-chip-group">
                @foreach($a11y['letter_spacing_options'] as $key => $option)
                    <button type="button" data-a11y-setting="letterSpacing" data-a11y-value="{{ $key }}">{{ $option['label'] }}</button>
                @endforeach
            </div>
        </div>

        <div class="cms-a11y-group">
            <span class="cms-a11y-group__title">{{ $a11y['labels']['line_height'] }}</span>
            <div class="cms-a11y-chip-group">
                @foreach($a11y['line_height_options'] as $key => $option)
                    <button type="button" data-a11y-setting="lineHeight" data-a11y-value="{{ $key }}">{{ $option['label'] }}</button>
                @endforeach
            </div>
        </div>

        <div class="cms-a11y-group">
            <span class="cms-a11y-group__title">{{ $a11y['labels']['font_family'] }}</span>
            <div class="cms-a11y-chip-group">
                @foreach($a11y['font_families'] as $key => $family)
                    <button type="button" data-a11y-setting="fontFamily" data-a11y-value="{{ $key }}">{{ $family['label'] }}</button>
                @endforeach
            </div>
        </div>

        <div class="cms-a11y-group">
            <span class="cms-a11y-group__title">{{ $a11y['labels']['embeds'] }}</span>
            <div class="cms-a11y-chip-group">
                <button type="button" data-a11y-setting="embedsEnabled" data-a11y-value="1">{{ $a11y['labels']['on'] }}</button>
                <button type="button" data-a11y-setting="embedsEnabled" data-a11y-value="0">{{ $a11y['labels']['off'] }}</button>
            </div>
        </div>

        <div class="cms-a11y-group cms-a11y-group--exit">
            <button type="button" class="cms-a11y-exit" data-a11y-action="exit-normal">{{ $a11y['labels']['exit_normal'] }}</button>
        </div>
    </div>
</section>
