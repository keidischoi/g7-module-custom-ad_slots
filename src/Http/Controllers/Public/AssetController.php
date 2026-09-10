<?php

namespace Modules\Custom\AdSlots\Http\Controllers\Public;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * Serve whitelisted module static assets (JS) from resources/assets.
 */
class AssetController extends Controller
{
    /** @var list<string> */
    private const ALLOWED = [
        'hero-carousel.js',
    ];

    public function show(string $file): Response
    {
        $basename = basename($file);
        if (! in_array($basename, self::ALLOWED, true)) {
            abort(404);
        }

        $path = dirname(__DIR__, 3).'/resources/assets/'.$basename;
        if (! is_file($path)) {
            abort(404);
        }

        $js = (string) file_get_contents($path);

        return response($js, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'public, max-age=60',
        ]);
    }
}
