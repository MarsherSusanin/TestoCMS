<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\I18n\Services\LocaleResolver;

class HomeRedirectController extends Controller
{
    public function __invoke(LocaleResolver $localeResolver)
    {
        return redirect('/'.$localeResolver->effectiveDefault());
    }
}
