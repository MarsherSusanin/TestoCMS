@php
    $__layout = 'admin.partials.layout-styles';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('SEO & Crawlers') }} — TestoCMS</title>
    @include('admin.partials.layout-head')
    @include('admin.partials.layout-styles')
</head>
<body class="bg-gray-50 text-gray-900 font-sans antialiased min-h-screen flex">

    @include('admin.partials.sidebar')

    <div class="flex-1 flex flex-col min-w-0">
        <header class="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between sticky top-0 z-10">
            <div>
                <h1 class="text-xl font-bold font-display text-gray-900">{{ __('SEO & Crawlers') }}</h1>
                <p class="text-sm text-gray-500 mt-1">
                    {{ __('Настройки robots.txt и llms.txt') }}
                </p>
            </div>
        </header>

        <main class="flex-1 p-6 overflow-y-auto">
            <div class="max-w-3xl">
                @include('admin.partials.flash')

                <form action="{{ route('admin.settings.seo.update') }}" method="POST" class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    @csrf
                    @method('PUT')

                    <div class="p-6 space-y-8">
                        
                        <!-- Robots.txt -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label for="robots_txt_custom" class="block text-sm font-semibold text-gray-900">
                                    robots.txt
                                </label>
                                <a href="/robots.txt" target="_blank" class="text-xs text-indigo-600 hover:text-indigo-900 font-medium">Смотреть текущий →</a>
                            </div>
                            <p class="text-xs text-gray-500 mb-3">Оставьте поле пустым, чтобы CMS генерировала стандартный robots.txt (разрешает всё + указывает Sitemap). Если ввести текст здесь, он полностью переопределит дефолтный файл.</p>
                            
                            <textarea name="robots_txt_custom" id="robots_txt_custom" rows="6" 
                                      class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm font-mono text-xs p-3"
                                      placeholder="User-agent: *&#10;Allow: /&#10;Sitemap: {{ url('/sitemap-index.xml') }}">{{ old('robots_txt_custom', $settings->robots_txt_custom) }}</textarea>
                            @error('robots_txt_custom')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- LLMS.txt -->
                        <div class="pt-6 border-t border-gray-100">
                            <div class="flex items-center justify-between mb-2">
                                <label for="llms_txt_intro" class="block text-sm font-semibold text-gray-900">
                                    llms.txt (Intro)
                                </label>
                                <a href="/llms.txt" target="_blank" class="text-xs text-indigo-600 hover:text-indigo-900 font-medium">Смотреть текущий →</a>
                            </div>
                            <p class="text-xs text-gray-500 mb-3">Вводный текст для краулеров искусственного интеллекта. Выводится в самом начале файла <code>/llms.txt</code>. Поддерживается Markdown.</p>
                            
                            <textarea name="llms_txt_intro" id="llms_txt_intro" rows="6" 
                                      class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm font-mono text-xs p-3"
                                      placeholder="# Welcome to {{ config('app.name') }}&#10;&#10;Optional notes for AI LLM tools..."
                                      >{{ old('llms_txt_intro', $settings->llms_txt_intro) }}</textarea>
                            @error('llms_txt_intro')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                    </div>

                    <div class="px-6 py-4 bg-gray-50 flex items-center justify-end border-t border-gray-200">
                        <button type="submit" class="inline-flex justify-center rounded-lg border border-transparent bg-indigo-600 py-2 px-4 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors">
                            {{ __('Save Settings') }}
                        </button>
                    </div>
                </form>

            </div>
        </main>
    </div>
    
    @include('admin.partials.shell-scripts')
</body>
</html>
