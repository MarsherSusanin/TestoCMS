<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Post;
use App\Models\PublishSchedule;
use App\Modules\Content\Services\PublicationTransitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ScheduleCancellationController extends Controller
{
    public function __construct(private readonly PublicationTransitionService $transitions) {}

    public function page(Request $request, Page $page, PublishSchedule $schedule): RedirectResponse
    {
        $this->authorize('publish', $page);
        $this->transitions->cancel($page, $schedule->id, $request);

        return back()->with('success', 'Расписание отменено.');
    }

    public function post(Request $request, Post $post, PublishSchedule $schedule): RedirectResponse
    {
        $this->authorize('publish', $post);
        $this->transitions->cancel($post, $schedule->id, $request);

        return back()->with('success', 'Расписание отменено.');
    }

    public function apiPage(Request $request, Page $page, PublishSchedule $schedule): JsonResponse
    {
        return response()->json(['data' => $this->transitions->cancel($page, $schedule->id, $request)]);
    }

    public function apiPost(Request $request, Post $post, PublishSchedule $schedule): JsonResponse
    {
        return response()->json(['data' => $this->transitions->cancel($post, $schedule->id, $request)]);
    }
}
