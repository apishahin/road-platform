<?php

namespace App\Http\Controllers;

use App\Models\Page;

class PageController extends Controller
{
    public function show(string $slug)
    {
        $page = Page::where('slug', $slug)->firstOrFail();

        // খসড়া পেজ শুধু অ্যাডমিন দেখবে; অন্যদের জন্য ৪০৪
        if (! $page->is_published) {
            abort_unless(auth()->user()?->role === 'admin', 404);
        }

        return view('pages.show', ['page' => $page]);
    }
}