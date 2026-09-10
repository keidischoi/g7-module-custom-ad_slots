<?php

namespace Modules\Custom\AdSlots\Http\Controllers\Public;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * Serve module static assets (JS) from resources/assets.
 */
class AssetController extends Controller
{
    public function show(): Response
    {
        // Controller is under Http/Controllers/Public → 4 levels up = module root
        $path = dirname(__DIR__, 4).'/resources/assets/hero-carousel.js';
        if (! is_file($path)) {
            return response('/* missing */', 200, [
                'Content-Type' => 'application/javascript; charset=UTF-8',
                'Cache-Control' => 'no-store',
            ]);
        }

        $js = (string) file_get_contents($path);

        return response($js, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'public, max-age=60',
        ]);
    }
}
