<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        return response(implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /owner/',
            'Disallow: /platform/',
            'Disallow: /player/',
            'Disallow: /booking/',
            'Disallow: /login',
            'Disallow: /register',
            'Sitemap: '.route('marketplace.sitemap'),
            '',
        ]), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
