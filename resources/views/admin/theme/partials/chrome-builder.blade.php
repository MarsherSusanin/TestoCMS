<section class="panel" style="margin-top:14px;">
    <div class="inline" style="justify-content:space-between; margin-bottom:8px;">
        <div>
            <h2 style="margin:0;">Конструктор шапки / подвала / поиска</h2>
            <p class="muted" style="margin:4px 0 0;">Управление публичной шапкой/подвалом и SSR-поиском без правки Blade-шаблонов.</p>
        </div>
        <span class="muted">Сохранение в <span class="mono">theme_settings.site_chrome</span></span>
    </div>

    @error('chrome_payload')
        <div class="flash error" style="margin-top:10px;">{{ $message }}</div>
    @enderror

    <form method="POST" action="{{ route('admin.theme.chrome.update') }}" id="site-chrome-form">
        @csrf
        @method('PUT')
        <input type="hidden" name="chrome_payload" id="chrome-payload-input" class="chrome-hidden-input" value="{{ json_encode($initialChromeBuilderState, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}">

        <div class="chrome-tabs" data-chrome-tabs>
            <button type="button" class="chrome-tab active" data-chrome-tab="header">Шапка</button>
            <button type="button" class="chrome-tab" data-chrome-tab="footer">Подвал</button>
            <button type="button" class="chrome-tab" data-chrome-tab="search">Поиск</button>
        </div>

        <div class="chrome-builder-grid">
            <div>
                <section class="chrome-panel active" data-chrome-panel="header">
                    <div class="grid cols-2">
                        <div class="field">
                            <label for="chrome-header-variant">Макет шапки</label>
                            <select id="chrome-header-variant" data-chrome-input="header.variant">
                                <option value="split_nav">Раздельная навигация</option>
                                <option value="center_logo">Логотип по центру</option>
                                <option value="stacked_compact">Компактный стек</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="chrome-header-search-placement">Размещение поиска</label>
                            <select id="chrome-header-search-placement" data-chrome-input="header.search_placement">
                                <option value="header">Только в шапке</option>
                                <option value="footer">Только в подвале</option>
                                <option value="both">В шапке и подвале</option>
                                <option value="none">Скрыть в макете</option>
                            </select>
                        </div>
                    </div>

                    <div class="chrome-check-grid" style="margin-bottom:12px;">
                        <label class="checkbox"><input type="checkbox" data-chrome-input="header.enabled"> Включить шапку</label>
                        <label class="checkbox"><input type="checkbox" data-chrome-input="header.show_brand_subtitle"> Подзаголовок бренда</label>
                        <label class="checkbox"><input type="checkbox" data-chrome-input="header.show_locale_switcher"> Переключатель языка</label>
                        <label class="checkbox"><input type="checkbox" data-chrome-input="header.show_search"> Виджет поиска (переключатель в шапке)</label>
                    </div>

                    <div class="chrome-list-shell">
                        <div class="chrome-list-header">
                            <h3>Пункты навигации шапки</h3>
                            <button type="button" class="btn btn-small" data-chrome-add="header.nav_items">Добавить пункт</button>
                        </div>
                        <div class="chrome-list-items" data-chrome-list="header.nav_items"></div>
                    </div>

                    <div class="chrome-list-shell" style="margin-top:12px;">
                        <div class="chrome-list-header">
                            <h3>CTA-кнопки шапки</h3>
                            <button type="button" class="btn btn-small" data-chrome-add="header.cta_buttons">Добавить CTA</button>
                        </div>
                        <div class="chrome-list-items" data-chrome-list="header.cta_buttons"></div>
                    </div>
                </section>

                <section class="chrome-panel" data-chrome-panel="footer">
                    <div class="grid cols-2">
                        <div class="field">
                            <label for="chrome-footer-variant">Макет подвала</label>
                            <select id="chrome-footer-variant" data-chrome-input="footer.variant">
                                <option value="inline">В строку</option>
                                <option value="two_column">Две колонки</option>
                                <option value="three_column">Три колонки</option>
                            </select>
                        </div>
                        <div class="field">
                            <label>Параметры подвала</label>
                            <div class="chrome-check-grid">
                                <label class="checkbox"><input type="checkbox" data-chrome-input="footer.enabled"> Включить подвал</label>
                                <label class="checkbox"><input type="checkbox" data-chrome-input="footer.show_brand"> Показывать бренд</label>
                                <label class="checkbox"><input type="checkbox" data-chrome-input="footer.show_tagline"> Показывать слоган</label>
                            </div>
                        </div>
                    </div>

                    <div class="grid cols-2">
                        @foreach($supportedLocales as $localeCode)
                            <div class="field">
                                <label for="chrome-footer-tagline-{{ $localeCode }}">Слоган подвала ({{ strtoupper($localeCode) }})</label>
                                <input type="text" id="chrome-footer-tagline-{{ $localeCode }}" data-chrome-input="footer.tagline_translations.{{ strtolower($localeCode) }}">
                            </div>
                        @endforeach
                    </div>

                    <div class="chrome-list-shell">
                        <div class="chrome-list-header">
                            <h3>Ссылки подвала</h3>
                            <button type="button" class="btn btn-small" data-chrome-add="footer.links">Добавить ссылку</button>
                        </div>
                        <div class="chrome-list-items" data-chrome-list="footer.links"></div>
                    </div>

                    <div class="chrome-list-shell" style="margin-top:12px;">
                        <div class="chrome-list-header">
                            <h3>Соцсети подвала</h3>
                            <button type="button" class="btn btn-small" data-chrome-add="footer.social_links">Добавить соцсеть</button>
                        </div>
                        <div class="chrome-list-items" data-chrome-list="footer.social_links"></div>
                    </div>

                    <div class="chrome-list-shell" style="margin-top:12px;">
                        <div class="chrome-list-header">
                            <h3>Юридические ссылки</h3>
                            <button type="button" class="btn btn-small" data-chrome-add="footer.legal_links">Добавить ссылку</button>
                        </div>
                        <div class="chrome-list-items" data-chrome-list="footer.legal_links"></div>
                    </div>
                </section>

                <section class="chrome-panel" data-chrome-panel="search">
                    <div class="chrome-check-grid" style="margin-bottom:12px;">
                        <label class="checkbox"><input type="checkbox" data-chrome-input="search.enabled"> Включить SSR-поиск</label>
                    </div>
                    <div class="grid cols-2">
                        <div class="field">
                            <label for="chrome-search-path">Slug страницы поиска</label>
                            <input type="text" id="chrome-search-path" data-chrome-input="search.path_slug" placeholder="search">
                            <small>Маршрут будет доступен как <span class="mono">/{locale}/search</span> (и через catch-all для кастомного slug).</small>
                        </div>
                        <div class="field">
                            <label for="chrome-search-scope">Область поиска по умолчанию</label>
                            <select id="chrome-search-scope" data-chrome-input="search.scope_default">
                                <option value="all">Всё</option>
                                <option value="posts">Посты</option>
                                <option value="pages">Страницы</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid cols-2">
                        <div class="field">
                            <label for="chrome-search-results">Результатов на страницу</label>
                            <input type="number" id="chrome-search-results" min="1" max="50" step="1" data-chrome-input="search.results_per_page">
                        </div>
                        <div class="field">
                            <label for="chrome-search-min-length">Минимальная длина запроса</label>
                            <input type="number" id="chrome-search-min-length" min="1" max="20" step="1" data-chrome-input="search.min_query_length">
                        </div>
                    </div>
                    <div class="grid cols-2">
                        @foreach($supportedLocales as $localeCode)
                            <div class="field">
                                <label for="chrome-search-placeholder-{{ $localeCode }}">Плейсхолдер поиска ({{ strtoupper($localeCode) }})</label>
                                <input type="text" id="chrome-search-placeholder-{{ $localeCode }}" data-chrome-input="search.placeholder_translations.{{ strtolower($localeCode) }}">
                            </div>
                        @endforeach
                    </div>
                </section>

                <div class="actions" style="margin-top:14px;">
                    <button type="submit" class="btn btn-primary">Сохранить шапку/подвал/поиск</button>
                    <button type="button" class="btn" id="chrome-builder-reset">Сбросить форму к сохранённой</button>
                </div>
            </div>

            <aside>
                <div class="chrome-preview-shell" id="chrome-builder-preview">
                    <div class="chrome-preview-head">Живое превью (RU)</div>
                    <div class="chrome-preview-body">
                        <div class="chrome-preview-topbar">
                            <div class="chrome-preview-brand">
                                <strong>{{ config('app.name') }}</strong>
                                <span data-chrome-preview-tagline>SEO-first CMS на Laravel</span>
                            </div>
                            <div class="chrome-preview-nav" data-chrome-preview-header-nav></div>
                        </div>
                        <div class="chrome-preview-search" data-chrome-preview-search>Поиск по сайту</div>
                        <div class="chrome-footer-preview">
                            <div class="chrome-preview-brand">
                                <strong data-chrome-preview-footer-brand>{{ config('app.name') }}</strong>
                                <span data-chrome-preview-footer-tagline>SEO-first CMS на Laravel</span>
                            </div>
                            <div class="chrome-preview-footer-links" data-chrome-preview-footer-links></div>
                        </div>
                    </div>
                </div>

                <details class="panel" style="margin-top:12px;">
                    <summary>Подсказки по конструктору</summary>
                    <ul style="margin:10px 0 0 18px;">
                        <li>Тексты кнопок/ссылок локализуются по RU/EN, URL — общий.</li>
                        <li>Поддерживаются шаблоны URL с <span class="mono">{locale}</span>.</li>
                        <li>Макет поиска учитывает и общий переключатель поиска, и размещение в шапке/подвале.</li>
                        <li>После сохранения full-page cache публичных страниц сбрасывается.</li>
                    </ul>
                </details>
            </aside>
        </div>
    </form>
</section>
