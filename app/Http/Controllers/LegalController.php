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

    /**
     * ⚠️ THE PAGE THAT WAS ENTIRELY ABSENT, AND IS NOT OPTIONAL.
     *
     * A payment acquirer's underwriting looks for a refund and cancellation
     * policy specifically. Its absence is a real gap regardless of when
     * payments switch on — and it is worse during the free period, not better,
     * because members joining now need to know what happens to introductions
     * they were given free when charging begins.
     *
     * ⚠️ DRAFT WORDING. The client approves it; nothing here is legal advice.
     * The draft banner in the view stays until he does.
     */
    public function refunds(): View
    {
        return view('public.legal', [
            'billing' => app(Billing::class)->forDisplay(),
            'heading' => 'Refunds and Cancellation',
            'intro' => 'What happens if you change your mind, and what an introduction credit is.',
            'sections' => $this->refundSections(),
        ]);
    }

    /**
     * @return list<array{heading: string, body: list<string>}>
     */
    private function refundSections(): array
    {
        return [
            [
                'heading' => 'What you are buying',
                'body' => [
                    'RealVows sells introduction credits. One credit opens one introduction, '
                        .'which means you see that person\'s full profile, their photographs and, '
                        .'if you both express interest, how to contact them.',
                    'You are not buying a match, an outcome, or a guarantee that anyone will '
                        .'reply. We choose who you are introduced to and we stand behind those '
                        .'choices, but nobody can promise you a marriage.',
                ],
            ],
            [
                'heading' => 'Unused credits',
                'body' => [
                    'A credit you have not spent can be refunded in full within 14 days of '
                        .'purchase. Write to us and we will return it to the card you paid with.',
                    'Credits do not expire. If you step away and come back, they are still there.',
                ],
            ],
            [
                'heading' => 'Credits you have already spent',
                /*
                 * ⚠️ THE HONEST PART. An opened introduction cannot be
                 * un-seen, so a blanket refund on spent credits would be a
                 * promise we cannot keep. Saying that plainly is better than a
                 * vague policy that becomes an argument later.
                 */
                'body' => [
                    'Once an introduction is open you have seen the profile, so that credit has '
                        .'been used and is not normally refundable.',
                    'There are exceptions and we apply them without argument: if the profile '
                        .'turned out to be fake, if the person had left the platform, if the '
                        .'introduction was broken or unreadable, or if we made a mistake. In any '
                        .'of those cases write to us and we will return the credit.',
                ],
            ],
            [
                'heading' => 'Introductions given free',
                'body' => [
                    'RealVows is free while we are getting started. Introductions you receive '
                        .'during this period are yours to keep permanently, and stay open when '
                        .'charging begins.',
                    'Nothing you were given free will ever be taken back or locked, and you will '
                        .'never be charged for it afterwards.',
                ],
            ],
            [
                'heading' => 'Closing your account',
                'body' => [
                    'You can close your account at any time from your account settings. We delete '
                        .'your profile, your photographs and your introductions.',
                    'Any credits you have not spent are refunded if you closed within 14 days of '
                        .'buying them. After that they are not refundable, but they also cost you '
                        .'nothing to leave behind.',
                ],
            ],
            [
                'heading' => 'How to ask',
                'body' => [
                    'Write to the billing address on your receipt and tell us what happened. We '
                        .'do not require a reason for an unused credit and we do not make it '
                        .'difficult.',
                    'We answer within three working days. Refunds reach your card within five to '
                        .'ten working days after that, depending on your bank.',
                ],
            ],
        ];
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
