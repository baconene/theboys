<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\PrintServiceSetting;
use Inertia\Inertia;
use Inertia\Response;

class PrintServiceSettingsController extends Controller
{
    public function edit(): Response
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);

        return Inertia::render('settings/PrintSettings', [
            'settings'           => PrintServiceSetting::getSetting(),
            'beams_instance_id'  => config('broadcasting.beams.instance_id') ?: null,
            'beams_configured'   => ! empty(config('broadcasting.beams.instance_id')) && ! empty(config('broadcasting.beams.secret_key')),
        ]);
    }
}
