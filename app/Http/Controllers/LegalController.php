<?php

namespace App\Http\Controllers;

use App\Support\Billing;
use Illuminate\View\View;

/**
 * ⚠️ THE LEGAL PAGES AND THE RECEIPT MUST NAME THE SAME COMPANY.
 *
 * The Terms state who a member is actually contracting with, and the card
 * statement states who took their money. If those disagree it is a mismatch
 * an acquirer's underwriting will flag, and it is also simply wrong, because
 * "RealVows" is a trading name here, not a legal person. The money settles to
 * a different company entirely.
 *
 * So both read `billing_legal_entity` from the same settings group. One
 * source of truth for who the company is, and LegalEntityTest fails the build
 * if either surface hardcodes a name.
 *
 * ── ⚠️ THESE ARE DRAFTED POLICIES, NOT LEGAL ADVICE ───────────────────────
 *
 * The client asked for them to be written rather than waiting on him: "the AI
 * can give you a policy, ask Grok or somebody, they can give you the legal
 * word for the policy." They are written carefully, they cover what actually
 * applies to this product, and they are honest about what it does. They have
 * NOT been reviewed by a lawyer in Jamaica, and that is worth the client
 * knowing before launch. Having considered policies now beats having none
 * while waiting for a review that may not come.
 *
 * ── ⚠️ TWO THINGS THESE PAGES MUST NEVER SAY ──────────────────────────────
 *
 *  1. HOW VERIFICATION IS PERFORMED. The client circled that paragraph and
 *     wrote "this is private information". Publishing the anti-fraud method
 *     publishes what somebody needs in order to prepare for it. Outcome only.
 *
 *  2. WHAT AN EMAIL OR WHATSAPP MESSAGE CONTAINS. Same instruction from him.
 *     That introductions arrive by email and WhatsApp is a fact and may be
 *     stated. What those messages say may not.
 *
 * ── ⚠️ EVERY CLAUSE MUST BE TRUE IN BOTH BILLING STATES ───────────────────
 *
 * Payments switch on later. A policy written as though everything is free
 * would need rewriting on the day charging begins, and a legal page that gets
 * rewritten quietly is worse than one that was right the first time. Anything
 * touching money is written to hold now and afterwards.
 */
class LegalController extends Controller
{
    public function terms(): View
    {
        return view('public.legal', [
            'billing' => app(Billing::class)->forDisplay(),
            'heading' => 'Terms of Service',
            'intro' => 'The agreement between you and RealVows.',
            'sections' => $this->termsSections(),
        ]);
    }

    public function privacy(): View
    {
        return view('public.legal', [
            'billing' => app(Billing::class)->forDisplay(),
            'heading' => 'Privacy Policy',
            'intro' => 'What we collect, why we collect it, and what we never do with it.',
            'sections' => $this->privacySections(),
        ]);
    }

    /**
     * ⚠️ THE PAGE THAT WAS ENTIRELY ABSENT, AND IS NOT OPTIONAL.
     *
     * A payment acquirer's underwriting looks for a refund and cancellation
     * policy specifically. Its absence is a real gap regardless of when
     * payments switch on, and it is worse during the free period rather than
     * better, because members joining now need to know what happens to
     * introductions they were given free when charging begins.
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

    public function guidelines(): View
    {
        return view('public.legal', [
            'billing' => app(Billing::class)->forDisplay(),
            'heading' => 'Community Guidelines',
            'intro' => 'How members are expected to treat one another here.',
            'sections' => $this->guidelineSections(),
        ]);
    }

    /**
     * @return list<array{heading: string, body: list<string>}>
     */
    private function termsSections(): array
    {
        return [
            [
                'heading' => 'Who may use RealVows',
                'body' => [
                    'You must be 18 or over to hold an account. You confirm this when you '
                        .'register, and we will close any account we find belongs to someone '
                        .'younger.',
                    'You may hold one account. It is personal to you, and you may not share it, '
                        .'sell it, or let another person use it.',
                    'You must give us accurate information about yourself. A profile that '
                        .'misrepresents who you are undermines the one thing this platform '
                        .'promises, and it is grounds for removal.',
                ],
            ],
            [
                'heading' => 'What RealVows does',
                'body' => [
                    'We introduce you to other members we believe you are compatible with. We '
                        .'choose who you are introduced to. There is no directory, no search, and '
                        .'no way to browse other members.',
                    'Introductions reach you by email and by WhatsApp, if you have given us a '
                        .'number and agreed to receive them there.',
                    'We do not promise that you will meet anyone, that anyone will reply, or that '
                        .'any introduction will lead anywhere. We stand behind the care taken in '
                        .'choosing, and nothing beyond that.',
                ],
            ],
            [
                'heading' => 'Verification',
                'body' => [
                    'Every member is reviewed by a real person before they can be matched. Nobody '
                        .'enters the matching pool unverified.',
                    'We may decline to verify an account, and we may withdraw verification later '
                        .'if something comes to light. We are not obliged to explain a decision '
                        .'in detail, because explaining exactly how we check would make the check '
                        .'easier to defeat.',
                ],
            ],
            [
                'heading' => 'Your content',
                'body' => [
                    'Your photographs and the words you write remain yours. You give us '
                        .'permission to store them and to show them to members you are introduced '
                        .'to, and to nobody else.',
                    'We do not use your photographs in advertising. We do not sell them, license '
                        .'them, or publish them outside the platform.',
                    'You must have the right to upload what you upload. Photographs of other '
                        .'people, images taken from elsewhere, and anything you do not own will be '
                        .'removed.',
                ],
            ],
            [
                'heading' => 'Paying for introductions',
                'body' => [
                    'RealVows sells introduction credits. One credit opens one introduction. What '
                        .'a credit costs, and whether anything is being charged at all, is shown '
                        .'to you before you pay.',
                    'Introductions are free while we are getting started. Anything you receive '
                        .'during that period stays open permanently and will never be charged for '
                        .'afterwards.',
                    'Our refund and cancellation policy forms part of these terms.',
                ],
            ],
            [
                'heading' => 'Suspending or closing an account',
                'body' => [
                    'You may close your account at any time from your account settings.',
                    'We may suspend or close an account that breaks these terms or the community '
                        .'guidelines, that we believe is fraudulent, or that puts other members at '
                        .'risk. Where it is safe and fair to do so, we will tell you why.',
                    'If we close your account for a reason that is not your fault, we refund any '
                        .'credits you have not spent.',
                ],
            ],
            [
                'heading' => 'What we are responsible for',
                'body' => [
                    'RealVows is an introduction service. We are not responsible for how another '
                        .'member behaves, for anything they tell you, or for what happens when you '
                        .'meet somebody in person. Use the same judgement you would use meeting '
                        .'anybody new.',
                    'We take reasonable care to keep the platform running and your information '
                        .'safe. We cannot promise the service will never be interrupted.',
                    'Nothing in these terms limits any right you have under the law that cannot '
                        .'be limited by agreement.',
                ],
            ],
            [
                'heading' => 'Changes to these terms',
                'body' => [
                    'We may change these terms. If a change materially affects you, we will tell '
                        .'you by email before it takes effect.',
                    'A change to what things cost never applies retroactively to an introduction '
                        .'you already hold.',
                ],
            ],
            [
                'heading' => 'Getting in touch',
                'body' => [
                    'Write to us at the address on this page with any question about these terms, '
                        .'about your account, or about a decision we have made. We answer within '
                        .'three working days.',
                ],
            ],
        ];
    }

    /**
     * @return list<array{heading: string, body: list<string>}>
     */
    private function privacySections(): array
    {
        return [
            [
                'heading' => 'What we collect',
                'body' => [
                    'The account details you give us: your email address, your mobile number, and '
                        .'your password, which we store only as a cryptographic hash and can '
                        .'never read.',
                    'Your profile: your age, where you live, your photographs, and the answers you '
                        .'give about your values, your faith, your lifestyle and what you are '
                        .'looking for in somebody else.',
                    'The material you send us for verification.',
                    'Technical records created by using the site, such as the times you signed in '
                        .'and whether a message we sent you was delivered.',
                ],
            ],
            [
                'heading' => 'Why we collect it',
                'body' => [
                    'To decide who to introduce you to. Your answers are the entire basis of that '
                        .'decision, which is why we ask for so many of them.',
                    'To confirm you are a real person, which is what the whole platform rests on.',
                    'To send you your introductions and the messages that go with them.',
                    'To take payment when payment applies, and to keep the records the law '
                        .'requires us to keep.',
                ],
            ],
            [
                'heading' => 'What we never do',
                'body' => [
                    'We do not sell your information. We do not rent it, trade it, or hand it to '
                        .'advertisers.',
                    'We do not show your profile to anyone who has not been introduced to you.',
                    'We do not publish your last name. It stays private until you and another '
                        .'member have both expressed interest in each other.',
                    'We do not put your photographs anywhere they can be found by a search engine '
                        .'or opened by somebody with a link.',
                ],
            ],
            [
                'heading' => 'Who else sees anything',
                'body' => [
                    'Members you are introduced to see your profile and your photographs. They see '
                        .'your last name and how to contact you only after you have both said yes.',
                    'A small number of trusted suppliers process data on our behalf: our hosting '
                        .'provider, our email provider, our payment provider, and WhatsApp for '
                        .'message delivery. They act on our instructions and may not use your '
                        .'information for anything else.',
                    'We disclose information to the authorities only where the law requires it, or '
                        .'where somebody is at risk of serious harm.',
                ],
            ],
            [
                'heading' => 'How long we keep it',
                'body' => [
                    'While your account is open, and for a short period afterwards so that an '
                        .'account closed by mistake can be restored.',
                    'When you delete your account we remove your profile, your photographs and '
                        .'your introductions.',
                    'We keep payment records for as long as tax and accounting law requires, even '
                        .'after an account is closed. Those records name the transaction rather '
                        .'than your profile.',
                ],
            ],
            [
                'heading' => 'Your rights',
                'body' => [
                    'You can ask for a copy of everything we hold about you.',
                    'You can correct anything that is wrong, and most of it you can edit yourself '
                        .'from your account.',
                    'You can ask us to delete your account and your data.',
                    'You can stop receiving messages at any time. Every email carries a link to '
                        .'unsubscribe, and you can reply STOP to any WhatsApp message.',
                ],
            ],
            [
                'heading' => 'Keeping it safe',
                'body' => [
                    'Traffic to and from the site is encrypted. Photographs are held in private '
                        .'storage and are served only through short-lived links to people entitled '
                        .'to see them.',
                    'Access to member data inside RealVows is limited to the people who need it to '
                        .'review verifications and answer support requests, and what they do is '
                        .'recorded.',
                    'No system is perfect. If something happens that affects you, we will tell you.',
                ],
            ],
            [
                'heading' => 'Asking us about your data',
                'body' => [
                    'Write to the address on this page. We answer within three working days, and '
                        .'we complete requests to export or delete data within thirty days.',
                ],
            ],
        ];
    }

    /**
     * @return list<array{heading: string, body: list<string>}>
     */
    private function guidelineSections(): array
    {
        return [
            [
                'heading' => 'Be who you say you are',
                'body' => [
                    'Use your own photographs, taken recently enough to be recognisable. Give your '
                        .'real age and your real situation.',
                    'Everybody here has been checked by a person before being matched. That is '
                        .'worth something only if it is true of you as well.',
                ],
            ],
            [
                'heading' => 'Treat an introduction as a real person',
                'body' => [
                    'Somebody chose to open an introduction to you. Whether or not you are '
                        .'interested, they are owed the courtesy you would want yourself.',
                    'It is always acceptable to say no, or to say nothing. It is never acceptable '
                        .'to be cruel about it.',
                ],
            ],
            [
                'heading' => 'What is not allowed',
                'body' => [
                    'Harassment of any kind, including messages that continue after somebody has '
                        .'asked you to stop.',
                    'Sexual content, nudity, or photographs of anyone under 18.',
                    'Asking members for money, promoting a business, or recruiting for anything.',
                    'Abuse based on race, religion, nationality, disability or gender.',
                    'Sharing another member\'s photographs, contact details or private messages '
                        .'with anyone.',
                ],
            ],
            [
                'heading' => 'Meeting in person',
                'body' => [
                    'Meet somewhere public the first time. Tell somebody you trust where you are '
                        .'going and when you expect to be back.',
                    'Nobody here has been background-checked. Verification confirms that a person '
                        .'is who they say they are. It is not a character reference.',
                    'Trust your judgement. If something feels wrong, leave.',
                ],
            ],
            [
                'heading' => 'Reporting somebody',
                'body' => [
                    'Every introduction carries a way to report the person in it. Tell us what '
                        .'happened and we read every report.',
                    'The person you report is never told that you reported them, and never told '
                        .'that a report exists.',
                    'We may warn, suspend or remove a member. Where somebody is in danger we '
                        .'involve the authorities.',
                ],
            ],
            [
                'heading' => 'If we get it wrong',
                'body' => [
                    'If we remove or suspend your account and you believe we are mistaken, write '
                        .'to us. A person will look at it again.',
                ],
            ],
        ];
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
                    'RealVows sells introduction credits. One credit opens one introduction, which '
                        .'means you see that member\'s full profile and photographs, and, if you '
                        .'both express interest, how to contact them.',
                    'You are not buying a match, an outcome, or a guarantee that anyone will '
                        .'reply. We choose who you are introduced to and we stand behind those '
                        .'choices. Nobody can promise you a marriage.',
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
                    'There are exceptions and we apply them without argument. If the profile '
                        .'turned out to be fake, if the person had left the platform, if the '
                        .'introduction was broken or unreadable, or if we made a mistake, write to '
                        .'us and we will return the credit.',
                ],
            ],
            [
                'heading' => 'Introductions given free',
                'body' => [
                    'RealVows is free while we are getting started. Introductions you receive '
                        .'during this period are yours to keep permanently, and they stay open '
                        .'when charging begins.',
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
}
