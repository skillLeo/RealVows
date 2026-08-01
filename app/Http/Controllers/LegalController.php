<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class LegalController extends Controller
{
    public function terms(): View
    {
        return view('public.legal', [
            'heading' => 'Terms of Service',
            'intro' => 'The agreement between you and RealVows.',
        ]);
    }

    public function privacy(): View
    {
        return view('public.legal', [
            'heading' => 'Privacy Policy',
            'intro' => 'What we collect, why we collect it, and what we never do with it.',
        ]);
    }

    public function guidelines(): View
    {
        return view('public.legal', [
            'heading' => 'Community Guidelines',
            'intro' => 'How members are expected to treat one another here.',
        ]);
    }
}
