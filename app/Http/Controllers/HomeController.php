<?php

namespace App\Http\Controllers;

use App\Services\HomeData;

class HomeController extends Controller
{
    public function index(HomeData $data)
    {
        return view('home', $data->build());
    }
}