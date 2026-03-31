<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Установка TestoCMS' }}</title>
    <meta name="robots" content="noindex, nofollow">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: ui-sans-serif, system-ui, -apple-system, sans-serif; background: #f0f2f5; color: #1a1a2e; line-height: 1.6; min-height: 100vh; }
        .wizard-container { max-width: 640px; margin: 40px auto; padding: 0 16px; }
        .wizard-header { text-align: center; margin-bottom: 32px; }
        .wizard-header h1 { font-size: 28px; font-weight: 700; color: #111827; }
        .wizard-header p { color: #6b7280; margin-top: 4px; }
        .steps-nav { display: flex; gap: 4px; margin-bottom: 24px; }
        .steps-nav .step { flex: 1; text-align: center; padding: 10px 4px; font-size: 13px; font-weight: 500; border-radius: 8px; background: #e5e7eb; color: #6b7280; transition: all .2s; }
        .steps-nav .step.active { background: #111827; color: #fff; }
        .steps-nav .step.done { background: #d1fae5; color: #065f46; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
        .card h2 { font-size: 20px; margin-bottom: 16px; font-weight: 600; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 14px; font-weight: 500; margin-bottom: 4px; color: #374151; }
        .form-group input, .form-group select { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; transition: border-color .2s; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #111827; box-shadow: 0 0 0 3px rgba(17,24,39,.08); }
        .form-group .hint { font-size: 12px; color: #9ca3af; margin-top: 2px; }
        .choice-grid { display: grid; gap: 12px; }
        .choice-card { position: relative; border: 1px solid #cbd5e1; border-radius: 10px; background: #fff; transition: border-color .2s, box-shadow .2s; }
        .choice-card:has(input:checked) { border-color: #111827; box-shadow: 0 0 0 3px rgba(17,24,39,.08); }
        .choice-card label { display: flex; gap: 10px; align-items: flex-start; padding: 14px; cursor: pointer; margin: 0; }
        .choice-card input[type="radio"] { width: 16px; height: 16px; margin-top: 3px; accent-color: #111827; flex-shrink: 0; }
        .choice-card-title { font-size: 14px; font-weight: 600; color: #111827; }
        .choice-card-text { font-size: 13px; color: #6b7280; margin-top: 2px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px; border: 0; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; transition: all .15s; text-decoration: none; }
        .btn-primary { background: #111827; color: #fff; }
        .btn-primary:hover { background: #1f2937; }
        .btn-primary:disabled { opacity: .5; cursor: not-allowed; }
        .btn-secondary { background: #e5e7eb; color: #374151; }
        .btn-secondary:hover { background: #d1d5db; }
        .btn-success { background: #059669; color: #fff; }
        .btn-success:hover { background: #047857; }
        .actions { display: flex; justify-content: space-between; margin-top: 24px; }
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
        .alert-info { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; }
        .check-list { list-style: none; }
        .check-list li { display: flex; align-items: center; gap: 8px; padding: 8px 0; border-bottom: 1px solid #f3f4f6; font-size: 14px; }
        .check-list li:last-child { border-bottom: 0; }
        .check-icon { width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; flex-shrink: 0; }
        .check-icon.pass { background: #d1fae5; color: #065f46; }
        .check-icon.fail { background: #fef2f2; color: #991b1b; }
        .check-icon.opt { background: #fefce8; color: #854d0e; }
        .check-detail { margin-left: auto; font-size: 12px; color: #9ca3af; }
        .checkbox-group { display: flex; gap: 16px; align-items: center; }
        .checkbox-group label { display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 14px; }
        .checkbox-group input[type="checkbox"] { width: 16px; height: 16px; accent-color: #111827; }
        .spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid #e5e7eb; border-top-color: #111827; border-radius: 50%; animation: spin .6s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        @media (max-width: 640px) {
            .form-row { grid-template-columns: 1fr; }
            .choice-card label { padding: 12px; }
        }
    </style>
</head>
<body>
    <div class="wizard-container">
        <div class="wizard-header">
            <h1>TestoCMS</h1>
            <p>Мастер настройки</p>
        </div>

        <div class="steps-nav">
            <div class="step {{ ($currentStep ?? 1) == 1 ? 'active' : (($currentStep ?? 1) > 1 ? 'done' : '') }}">1. Система</div>
            <div class="step {{ ($currentStep ?? 1) == 2 ? 'active' : (($currentStep ?? 1) > 2 ? 'done' : '') }}">2. БД</div>
            <div class="step {{ ($currentStep ?? 1) == 3 ? 'active' : (($currentStep ?? 1) > 3 ? 'done' : '') }}">3. Сайт</div>
            <div class="step {{ ($currentStep ?? 1) == 4 ? 'active' : (($currentStep ?? 1) > 4 ? 'done' : '') }}">4. Админ</div>
            <div class="step {{ ($currentStep ?? 1) == 5 ? 'active' : '' }}">5. Готово</div>
        </div>

        <div class="card">
            @yield('content')
        </div>
    </div>
</body>
</html>
