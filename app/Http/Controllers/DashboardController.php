<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController
{
    public function __invoke(Request $request): \Inertia\Response
    {
        // Inertia shared props already include auth and permissions (auth.can)
        return Inertia::render('dashboard');
    }
}
