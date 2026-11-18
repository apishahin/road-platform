<?php

namespace App\Http\Controllers;

use App\Services\DashboardData;

class DashboardController extends Controller
{
    public function index(DashboardData $data)
    {
        return view('dashboard', $data->build());
    }
}
