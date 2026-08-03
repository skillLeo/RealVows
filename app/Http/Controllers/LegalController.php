<?php

namespace App\Http\Controllers;

use App\Support\Billing;
use Illuminate\View\View;

/**
 * ⚠️ THE LEGAL PAGES AND THE RECEIPT MUST NAME THE SAME COMPANY.
 *
 * The Terms state who a member is actually contracting with, and the card
 * statement states who took their money. If those disagree it is a mismatch
 * an acquirer's underwriting will flag — and it is also simply wrong, because
 * "RealVows" is a trading name here, not a legal person. The money settles to
 * a different company entirely.
 *
 * So both read `billing_legal_entity` from the same settings group. One
 * source of truth for who the company is, and LegalEntityTest fails the build
 * if either surface hardcodes a name.
 */
class LegalController extends Controller
{
    public function terms(): View
    {
        return view('public.legal', [
            'billing' => app(Billing::class)->forDisplay(),
            'heading' => 'Terms of Service',
            'intro' => 'The agreement between you and RealVows.',
        ]);
    }

    public function privacy(): View
    {
        return view('public.legal', [
            'billing' => app(Billing::class)->forDisplay(),
            'heading' => 'Privacy Policy',
            'intro' => 'What we collect, why we collect it, and what we never do with it.',
        ]);
    }

    public function guidelines(): View
    {
        return view('public.legal', [
            'billing' => app(Billing::class)->forDisplay(),
            'heading' => 'Community Guidelines',
            'intro' => 'How members are expected to treat one another here.',
        ]);
    }
}
