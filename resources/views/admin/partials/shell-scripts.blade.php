<script type="application/json" id="testocms-admin-shell-boot">{!! json_encode(($adminShell['boot_payload'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<script src="{{ route('admin.runtime.show', ['runtime' => 'admin-shell.js']) }}"></script>
<script src="{{ route('admin.runtime.show', ['runtime' => 'admin-ui.js']) }}"></script>
<script src="{{ route('admin.runtime.show', ['runtime' => 'admin-i18n.js']) }}"></script>
