<?php

namespace App\Http\Controllers;

use App\Models\Advertisement;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class WelcomeController extends Controller
{
    public function index(): Response
    {
        $active = Advertisement::active()->get();

        return Inertia::render('Welcome', [
            'canRegister' => Features::enabled(Features::registration()),
            'banners'     => $active->where('type', 'banner')->values(),
            'promos'      => $active->where('type', 'promo')->values(),
        ]);
    }
}
