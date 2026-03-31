<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

class HomeRedirectController extends Controller
{
    public function __invoke()
    {
        $locale = config('cms.default_locale', 'en');

        return redirect('/'.$locale);
    }
}
