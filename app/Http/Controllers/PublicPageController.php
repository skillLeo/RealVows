<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * The Blade marketing pages.
 *
 * ⚠️ NOTHING HERE TOUCHES THE DATABASE, AND THAT IS A REQUIREMENT RATHER THAN
 * A COINCIDENCE.
 *
 * These are the pages a stranger sees first, on a mid-range phone, over 4G. A
 * settings lookup on this path has already turned the home page into a 500
 * once, when the settings table was unmigrated — a database fault taking down
 * the one page that does not need a database.
 *
 * The one fact the navigation needs (is the free period on?) is read from
 * App\Support\PublicState, which is a generated file. Read that class before
 * adding anything here that queries.
 */
class PublicPageController extends Controller
{
    public function home(): View
    {
        return view('public.home');
    }

    public function howItWorks(): View
    {
        return view('public.how-it-works');
    }

    public function about(): View
    {
        return view('public.about');
    }

    public function faq(): View
    {
        return view('public.faq');
    }
}
