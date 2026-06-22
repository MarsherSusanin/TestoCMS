@if(config('cms.consent.enabled'))
    <div id="cms-consent" class="cms-consent" hidden role="region" aria-label="{{ __('Уведомление о cookie') }}">
        <p class="cms-consent-text">
            {{ __('Мы используем cookie для работы сайта и аналитики.') }}
            @if(config('cms.consent.policy_url'))
                <a href="{{ config('cms.consent.policy_url') }}">{{ __('Подробнее') }}</a>
            @endif
        </p>
        <div class="cms-consent-actions">
            <button type="button" class="cms-cta" data-consent="accept">{{ __('Принять') }}</button>
            <button type="button" data-consent="decline">{{ __('Отклонить') }}</button>
        </div>
    </div>
    <script>
        (function () {
            var KEY = 'testocms_consent';
            var el = document.getElementById('cms-consent');
            if (!el) return;
            var stored = null;
            try { stored = localStorage.getItem(KEY); } catch (e) {}
            window.testoCmsConsent = stored;
            if (!stored) { el.hidden = false; }
            el.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-consent]');
                if (!btn) return;
                var v = btn.getAttribute('data-consent');
                try { localStorage.setItem(KEY, v); } catch (e) {}
                window.testoCmsConsent = v;
                el.hidden = true;
                document.dispatchEvent(new CustomEvent('testocms:consent', { detail: v }));
            });
        })();
    </script>
@endif
