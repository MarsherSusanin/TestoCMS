<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Setup\Services\PublicMediaService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class PublicMediaController extends Controller
{
    public function __invoke(Request $request, string $path, PublicMediaService $media): Response
    {
        $file = $media->resolve($path);
        abort_if($file === null, 404);
        $response = new BinaryFileResponse($file);
        $response->setAutoEtag();
        $response->setAutoLastModified();
        $response->setPublic();
        $response->setMaxAge(300);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Security-Policy', "default-src 'none'; sandbox");
        if ($response->isNotModified($request)) {
            return $response;
        }
        // Normalize a single range before Symfony streams the file. An oversized
        // suffix still describes the entire file; an unsatisfiable range is 416.
        $range = (string) $request->headers->get('Range', '');
        $ifRange = $request->headers->get('If-Range');
        $matchesValidator = $ifRange === null || $ifRange === $response->getEtag()
            || $ifRange === $response->headers->get('Last-Modified');
        if ($request->isMethod('GET') && $matchesValidator
            && preg_match('/^bytes=(\d*)-(\d*)$/D', $range, $matches)) {
            $size = (int) filesize($file);
            $start = $matches[1] === '' ? max(0, $size - (int) $matches[2]) : (int) $matches[1];
            $end = $matches[2] === '' || $matches[1] === '' ? $size - 1 : (int) $matches[2];
            if ($start >= $size || $end < $start || ($matches[1] === '' && (int) $matches[2] === 0)) {
                return new Response('', 416, [
                    'Content-Range' => 'bytes */'.$size,
                    'Content-Length' => '0',
                    'Accept-Ranges' => 'bytes',
                    'X-Content-Type-Options' => 'nosniff',
                ]);
            }
            $request->headers->set('Range', 'bytes='.$start.'-'.min($end, $size - 1));
        } elseif ($request->isMethod('GET') && $range !== '' && $matchesValidator) {
            // Multipart and malformed ranges may legally be ignored.
            $request->headers->remove('Range');
        }

        $response->prepare($request);

        return $response;
    }
}
