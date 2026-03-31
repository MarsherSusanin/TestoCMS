<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;

class DashboardController extends Controller
{
    public function __invoke()
    {
        return view('admin.dashboard', [
            'stats' => [
                'posts' => Post::query()->count(),
                'pages' => Page::query()->count(),
                'categories' => Category::query()->count(),
                'assets' => Asset::query()->count(),
            ],
        ]);
    }
}
