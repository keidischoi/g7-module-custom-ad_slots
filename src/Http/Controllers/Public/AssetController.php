<?php

namespace Modules\Custom\AdSlots\Http\Controllers\Public;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * Serve module static assets (JS) from resources/assets.
 */
class AssetController extends Controller
{
    /** @var list<string> */
    private const ALLOWED = [
        'hero-carousel.js',
        'ad-slot-image-upload.js',
    ];

    public function show(string $file = 'hero-carousel.js'): Response
    {
        $file = basename($file);
        if (! in_array($file, self::ALLOWED, true)) {
            return response('/* not allowed */', 404, [
                'Content-Type' => 'application/javascript; charset=UTF-8',
                'Cache-Control' => 'no-store',
            ]);
        }

        // Controller is under Http/Controllers/Public → 4 levels up = module root
        $path = dirname(__DIR__, 4).'/resources/assets/'.$file;
        if (! is_file($path)) {
            return response('/* missing */', 200, [
                'Content-Type' => 'application/javascript; charset=UTF-8',
                'Cache-Control' => 'no-store',
            ]);
        }

        $js = (string) file_get_contents($path);

        return response($js, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'no-store, must-revalidate',
        ]);
    }
}
