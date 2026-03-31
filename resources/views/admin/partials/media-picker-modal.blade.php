<div class="media-picker-overlay" id="testocms-media-picker" aria-hidden="true">
    <div class="media-picker-modal" role="dialog" aria-modal="true" aria-labelledby="media-picker-title">
        <div class="media-picker-header">
            <div>
                <h3 id="media-picker-title">Медиатека</h3>
                <p id="media-picker-subtitle">Выберите файл из Assets</p>
            </div>
            <button type="button" class="close-btn" data-media-picker-close aria-label="Закрыть">✕</button>
        </div>

        <div class="media-picker-toolbar">
            <input type="text" placeholder="Поиск по названию, alt, URL, mime…" data-media-picker-search>
            <select data-media-picker-kind>
                <option value="all">Все файлы</option>
                <option value="image">Изображения</option>
                <option value="video">Видео</option>
                <option value="document">Документы</option>
            </select>
            <select data-media-picker-sort>
                <option value="newest">Сначала новые</option>
                <option value="oldest">Сначала старые</option>
                <option value="title">По названию</option>
            </select>
        </div>

        <div class="media-picker-upload" data-media-picker-upload hidden>
            <div class="media-picker-upload-dropzone" data-media-picker-upload-dropzone tabindex="0">
                <strong>Перетащите файл сюда</strong>
                <span>или выберите его вручную, не покидая текущую форму</span>
                <button type="button" class="btn btn-small" data-media-picker-upload-choose>Выбрать файл</button>
                <input type="file" data-media-picker-upload-input hidden>
            </div>
            <div class="media-picker-upload-fields">
                <div class="field" style="margin:0;">
                    <label for="media-picker-upload-title">Название</label>
                    <input id="media-picker-upload-title" type="text" maxlength="255" data-media-picker-upload-title placeholder="Например, Hero image">
                </div>
                <div class="field" style="margin:0;">
                    <label for="media-picker-upload-alt">Alt-текст</label>
                    <input id="media-picker-upload-alt" type="text" maxlength="255" data-media-picker-upload-alt placeholder="Краткое описание изображения">
                </div>
            </div>
            <div class="media-picker-upload-footer">
                <div class="status" data-media-picker-upload-status>Файл ещё не выбран.</div>
                <div class="actions">
                    <button type="button" class="btn" data-media-picker-upload-reset>Очистить</button>
                    <button type="button" class="btn btn-primary" data-media-picker-upload-submit>Загрузить и выбрать</button>
                </div>
            </div>
        </div>

        <div class="media-picker-grid-wrap">
            <div class="media-picker-grid" data-media-picker-grid></div>
            <aside class="media-picker-side" data-media-picker-side></aside>
        </div>

        <div class="media-picker-footer">
            <div class="status" data-media-picker-status>Выбрано: 0</div>
            <div class="actions">
                <button type="button" class="btn" data-media-picker-clear>Снять выбор</button>
                <button type="button" class="btn" data-media-picker-cancel>Отмена</button>
                <button type="button" class="btn btn-primary" data-media-picker-confirm>Выбрать</button>
            </div>
        </div>
    </div>
</div>
