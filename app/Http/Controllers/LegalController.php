<?php

namespace App\Http\Controllers;

use App\Support\Billing;
use App\Support\PhotoVisibilityPolicy;
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
    /**
     * ⚠️ ONE VERSION FOR ALL FOUR PAGES, AND IT IS A DATE RATHER THAN A NUMBER.
     *
     * A member who is told the terms have changed has to be able to work out
     * WHICH terms they agreed to. An undated policy makes that impossible, and
     * "we may change these terms, we will tell you by email" is an empty
     * promise if the thing being pointed at has no name.
     *
     * ⚠️ SHARED DELIBERATELY. The four documents cross-reference each other:
     * the Terms incorporate the refund policy, the Terms point at the privacy
     * policy for who sees a photograph, and the guidelines are grounds for
     * removal under the Terms. Versioning them separately would let a member
     * hold a consistent-looking set of four that never existed together.
     *
     * ⚠️ MOVE THIS WHEN THE WORDING CHANGES, NOT WHEN THE FILE DOES. A
     * reformat, a typo fix or a comment is not a new version. A changed
     * obligation is.
     */
    public const VERSION = '2026-08-14';

    /**
     * ⚠️ PRIOR VERSIONS ARE RETRIEVABLE, AND THIS IS WHERE THEY ARE.
     *
     * The honest answer today is git: every earlier wording of these pages is
     * in the repository history, tagged by the commit that changed it. That is
     * a real archive and it is not reachable by a member, so this says where to
     * ask rather than pretending to a version picker that does not exist.
     *
     * If a member ever needs to see the terms they agreed to, that request is
     * answerable in full. Saying so is the point.
     */
    public const PRIOR_VERSIONS =
        'Earlier versions of this document are kept. Write to us and we will send you the '
        .'wording that was in force on any date you name.';

    public function terms(): View
    {
        return view('public.legal', [
            'billing' => app(Billing::class)->forDisplay(),
            'version' => self::VERSION,
            'priorVersions' => self::PRIOR_VERSIONS,
            'heading' => 'Terms of Service',
            'intro' => 'The agreement between you and RealVows.',
            'sections' => $this->termsSections(),
        ]);
    }

    public function privacy(): View
    {
        return view('public.legal', [
            'billing' => app(Billing::class)->forDisplay(),
            'version' => self::VERSION,
            'priorVersions' => self::PRIOR_VERSIONS,
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
            'version' => self::VERSION,
            'priorVersions' => self::PRIOR_VERSIONS,
            'heading' => 'Refunds and Cancellation',
            'intro' => 'What happens if you change your mind, and what an introduction credit is.',
            'sections' => $this->refundSections(),
        ]);
    }

    public function guidelines(): View
    {
        return view('public.legal', [
            'billing' => app(Billing::class)->forDisplay(),
            'version' => self::VERSION,
            'priorVersions' => self::PRIOR_VERSIONS,
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
                    /*
                     * ⚠️ THE TERMS DESCRIBE WHAT ACTUALLY HAPPENS, NOT WHAT IS
                     * BUILT.
                     *
                     * WhatsApp is finished and cannot send: every message is
                     * accepted by Meta and then fails on a billing setting. A
                     * term of service naming a delivery channel that has never
                     * delivered anything is a false statement in the one
                     * document a member could hold us to.
                     */
                    app(\App\Support\WhatsAppDelivery::class)->isOn()
                        ? 'Introductions reach you by email and by WhatsApp, if you have given us '
                            .'a number and agreed to receive them there.'
                        : 'Introductions reach you by email.',
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
                    /*
                     * ⚠️ THE SAME FALSE CLAIM THE PRIVACY PAGE CARRIED. This
                     * granted us permission "to show them to members you are
                     * introduced to", which describes a product that no longer
                     * exists and grants a permission we do not want and do not
                     * use. A licence clause wider than the behaviour is not a
                     * harmless leftover on a page a member agrees to.
                     */
                    'Your photographs and the words you write remain yours. You give us '
                        .'permission to store them and to show them only to the people set out '
                        .'in our privacy policy, and to nobody else.',
                    'We do not use your photographs in advertising. We do not sell them, license '
                        .'them, or publish them outside the platform.',
                    'You must have the right to upload what you upload. Photographs of other '
                        .'people, images taken from elsewhere, and anything you do not own will be '
                        .'removed.',
                ],
            ],
            [
                'heading' => 'Your trusted contact',
                'body' => [
                    'You may be asked to name a trusted contact before you can be introduced to '
                        .'anybody. This is a person who looks at your introductions and advises '
                        .'you, and on this platform they are a participant with their own account '
                        .'and their own obligations.',
                    'They agree to these terms when they accept your invitation. They must be who '
                        .'you say they are, they must act in your interest, and they may not use '
                        .'their position to reach or contact anybody on your behalf.',
                    'They can approve or decline an introduction for you. They cannot write to '
                        .'another member, and they cannot see anything about the platform beyond '
                        .'what concerns you.',
                    'If you believe your trusted contact is standing in your way without good '
                        .'reason, write to us. We keep the whole record of what they decided and '
                        .'when, and we will look at it. You may name somebody else at any time.',
                    'We may remove a trusted contact who breaks these terms, and we will tell you '
                        .'if we do.',
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
                    'RealVows is an introduction service. We introduce, and we do not guarantee. '
                        .'We are not responsible for how another member behaves, for anything '
                        .'they tell you, or for what happens when you meet somebody in person. '
                        .'Use the same judgement you would use meeting anybody new.',
                    /*
                     * ⚠️ SAID EXPLICITLY BECAUSE OF WHERE THE PRODUCT STOPS.
                     * Once two members hold each other\'s details they speak
                     * privately and nothing of it passes through here. A member
                     * who assumed otherwise would be assuming a protection that
                     * does not exist, and the honest thing is to say so on the
                     * page rather than let the assumption stand.
                     */
                    'Once you and another member have exchanged contact details, anything that '
                        .'follows happens between the two of you. It does not pass through this '
                        .'platform, we do not see it, and we cannot act on it.',
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
    /**
     * ⚠️ THIS PAGE DESCRIBES A PRODUCT THAT CHANGED UNDER IT.
     *
     * It used to say "members you are introduced to see your profile and your
     * photographs". That stopped being true and stayed on the page. A privacy
     * policy that overstates who can see a photograph is not a stale sentence,
     * it is a false statement about the most sensitive material held here, made
     * on the page a person reads specifically in order to decide whether to
     * hand it over.
     *
     * ── ⚠️ FOUR THINGS THAT WERE MISSING ENTIRELY ─────────────────────────
     *
     *  1. WHO SEES A PHOTOGRAPH. Stated by name now, and read from the live
     *     setting rather than written down, so it cannot go stale the same way
     *     twice. See App\Support\PhotoVisibilityPolicy.
     *
     *  2. THE TRUSTED CONTACT. A third person whose name, relationship, email
     *     address, password, joint photograph and every decision are held here,
     *     and who never signed up on their own initiative. They are a data
     *     subject with their own rights, not a field on somebody else's form.
     *
     *  3. AI PROCESSING. Both features are built and dormant. The disclosure is
     *     written NOW, before a key is pasted in, because the alternative is
     *     depending on somebody remembering to write it on the day the feature
     *     starts sending member text to a third party.
     *
     *  4. DEVICE AND TECHNICAL DATA. Risk scoring reads details of the device
     *     and connection. Undisclosed profiling is the kind of thing that is
     *     defensible right up until it is discovered.
     *
     * ⚠️ AND ONE THING WORTH SAYING OUT LOUD: no government identity document
     * is collected anywhere in this product. That is a genuine assurance, it
     * costs nothing to state, and a reader cannot infer it from silence.
     *
     * @return list<array{heading: string, body: list<string>}>
     */
    private function privacySections(): array
    {
        return [
            [
                'heading' => 'What we collect',
                'body' => [
                    'The account details you give us. Your email address, your mobile number, and '
                        .'your password, which we store only as a cryptographic hash and can never '
                        .'read.',
                    'Your profile. Your age, where you live, and the answers you give about your '
                        .'values, your faith, your lifestyle and what you are looking for in '
                        .'somebody else.',
                    'Your photographs, and the material you send us so that a person can confirm '
                        .'you are real.',
                    'The details of the trusted contact you name, if you name one. Their name, '
                        .'their relationship to you, and a way to reach them.',
                    'Technical records created by using the site. The times you signed in, the '
                        .'device and connection you used, and whether a message we sent you '
                        .'arrived.',
                ],
            ],
            [
                'heading' => 'We do not ask for identity documents',
                'body' => [
                    'We never collect a passport, a driving licence, a national identity card or '
                        .'any other government document. Not at registration, not for '
                        .'verification, and not at any later stage.',
                    'If anybody ever asks you for one in the name of RealVows, it did not come '
                        .'from us.',
                ],
            ],
            [
                'heading' => 'Your photographs',
                'body' => [
                    'Photographs are the most sensitive thing we hold, and they are treated '
                        .'differently from everything else on this list.',
                    'A member of our review team sees them, and that person is always of the same '
                        .'gender as you.',
                    $this->whoElseSeesPhotographs(),
                    'They are never published, never indexed by a search engine, and never '
                        .'reachable by a plain address. They are held in private storage and are '
                        .'served only through short-lived links issued to somebody entitled to '
                        .'open them.',
                    'They are never attached to an email or included in a message. No message we '
                        .'send contains an image of any kind.',
                    'We keep them while your account is open. When you delete your account we '
                        .'delete the files themselves, not only the record pointing at them.',
                ],
            ],
            [
                'heading' => 'If you name a trusted contact',
                'body' => [
                    'A trusted contact is a person you name to look at your introductions and '
                        .'advise you. They are told what we hold about them at the moment they '
                        .'are invited, before they agree to anything.',
                    'We hold their name, their relationship to you, a way to reach them, the '
                        .'password they choose for their own account, the photograph the two of '
                        .'you send us together, and a record of every decision they make about '
                        .'your introductions.',
                    'That record is kept in full and is never edited. If a trusted contact '
                        .'changes their mind, we add the new decision rather than replacing the '
                        .'old one, because the sequence is the only honest answer if you ever '
                        .'tell us somebody is standing in your way.',
                    'They have the same rights over their own information as you have over yours. '
                        .'They can ask for a copy of it, correct it, or ask us to delete it, and '
                        .'they can do that without going through you.',
                    'If you close your account, their record closes with it. If they ask to be '
                        .'removed while your account is open, we tell you that you need to name '
                        .'somebody else.',
                ],
            ],
            [
                'heading' => 'Artificial intelligence',
                'body' => [
                    'Two features on this platform can call an outside provider. Both are '
                        .'switched off until the platform owner supplies a key, and this section '
                        .'describes what happens when they are on.',
                    'The first helps you write about yourself. It takes the words you have '
                        .'already written and offers a tidier version of them. It may never add a '
                        .'fact you did not supply, and nothing it writes is saved until you have '
                        .'read it and accepted it.',
                    'The second writes a short summary to help our review team weigh an account. '
                        .'It contributes no part of any score and no part of any matching '
                        .'decision.',
                    'Your photographs are never sent to any such provider. Neither is the '
                        .'material you send us for verification, nor your contact details, nor '
                        .'anything belonging to your trusted contact.',
                    'The provider processes what we send on our instructions and may not use it '
                        .'for anything else.',
                ],
            ],
            [
                'heading' => 'Device and technical information',
                'body' => [
                    'When you register or sign in we record details of the device and connection '
                        .'you used. We compare those details across accounts to find the pattern '
                        .'where one person is quietly operating several.',
                    'That is the only purpose. We do not use it to build an advertising profile '
                        .'of you, and we do not share it with anybody who does.',
                ],
            ],
            [
                'heading' => 'Why we collect it',
                'body' => [
                    'To decide who to introduce you to. Your answers are the entire basis of that '
                        .'decision, which is why we ask for so many of them.',
                    'To confirm you are a real person, which is what the whole platform rests on.',
                    'To send you your introductions and to let you know when something has '
                        .'happened.',
                    'To take payment when payment applies, and to keep the records the law '
                        .'requires us to keep.',
                ],
            ],
            [
                'heading' => 'What we never do',
                'body' => [
                    'We do not sell your information. We do not rent it, trade it, or hand it to '
                        .'advertisers.',
                    'We do not show your profile to anybody who has not been introduced to you. '
                        .'There is no directory here, no search, and no way to browse members.',
                    'We do not publish your last name. It stays private until you and another '
                        .'member have both said yes to each other.',
                    'We do not carry messages between members. If you write to us we answer you, '
                        .'and we do not pass what you said to anybody else.',
                ],
            ],
            [
                'heading' => 'Who else sees anything',
                'body' => [
                    'Members you are introduced to see your first name, your age, where you live '
                        .'and the answers you gave about yourself. They see your last name and '
                        .'how to reach you only after you have both said yes.',
                    'A small number of suppliers process data on our behalf. Our hosting '
                        .'provider, our email provider, our payment provider, and WhatsApp for '
                        .'message delivery. They act on our instructions and may not use your '
                        .'information for anything else.',
                    'We disclose information to the authorities only where the law requires it, '
                        .'or where somebody is at risk of serious harm.',
                ],
            ],
            [
                'heading' => 'How long we keep it',
                'body' => [
                    /*
                     * ⚠️ WAS "A SHORT PERIOD AFTERWARDS", WHICH IS NOT A
                     * RETENTION PERIOD. A member cannot act on it and we cannot
                     * be held to it, which is the same thing as not having one.
                     */
                    'While your account is open, and for thirty days afterwards so that an '
                        .'account closed by mistake can be restored.',

                    /*
                     * ⚠️ THE CATEGORY EMAIL VERIFICATION CREATED WHEN IT MOVED.
                     *
                     * A person can now build a profile before confirming their
                     * address, which means an unconfirmed account can hold
                     * photographs of a face and a trusted contact's mobile
                     * number. The contact never signed up and cannot ask us for
                     * anything, so the only protection they have is that we do
                     * not keep it forever.
                     *
                     * ⚠️ TWELVE MONTHS AND A COMMAND THAT ENFORCES IT. See
                     * ForgetAbandonedRegistrations, scheduled weekly. A number
                     * written here with nothing carrying it out would be worse
                     * than the vague sentence it replaced.
                     */
                    'If you register and never confirm your email address, we delete everything '
                        .'you entered after twelve months. That includes your photographs and '
                        .'anything you told us about a trusted contact.',
                    'When you delete your account we remove your profile, your photographs, your '
                        .'introductions and the record of your trusted contact.',
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
                        .'unsubscribe, and you can reply STOP to any WhatsApp message. Stopping '
                        .'one does not stop the other.',
                ],
            ],
            [
                'heading' => 'Keeping it safe',
                'body' => [
                    'Traffic to and from the site is encrypted. Photographs are held in private '
                        .'storage and are served only through short-lived links.',
                    'Access to member data inside RealVows is limited to the people who need it '
                        .'to review verifications and answer support requests, and what they do '
                        .'is recorded.',
                    'No system is perfect. If something happens that affects you, we will tell '
                        .'you.',
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
     * ⚠️ READ FROM THE LIVE SETTING, NEVER WRITTEN DOWN HERE.
     *
     * Whether a member ever sees a photograph of the person they have been
     * introduced to is a setting, because it is a religious question the
     * client's scholar answers rather than one this codebase decides. A
     * sentence typed into this policy would be correct until the day he
     * answers, and then it would be a false statement on the privacy page,
     * which is exactly how the old wording came to overstate who could see a
     * photograph.
     *
     * ⚠️ AN UNREADABLE SETTING PRODUCES THE STRICTEST SENTENCE, because the
     * policy resolves to `never` in that case and this must describe what the
     * product will actually do, not what it would prefer to say.
     */
    private function whoElseSeesPhotographs(): string
    {
        return match (app(PhotoVisibilityPolicy::class)->level()) {
            PhotoVisibilityPolicy::AFTER_CONTACT_APPROVAL => 'The trusted contact of a member you are introduced to sees them, once that '
                .'member has been approved to meet you. Nobody else does.',

            PhotoVisibilityPolicy::AT_MUTUAL_INTEREST => 'A member you are introduced to sees them once you have both said yes to each '
                .'other, and their trusted contact sees them before that. Nobody else does.',

            /*
             * ⚠️ "REALVOWS DOES NOT DISPLAY" AND NOT "NO MEMBER EVER SEES".
             *
             * The two sound alike and only one of them is true. The platform
             * controls what it shows. It does not control what a father does
             * with his own phone, and the position taken on this expects
             * exactly that: the guardian may show his ward a photograph if he
             * judges it right, which is a person exercising judgement rather
             * than a system granting access.
             *
             * So "no other member ever sees them" became false the moment a
             * guardian turned his screen around, and a legal page that says
             * something untrue is worse than one that says nothing. The last
             * sentence puts the line where it actually falls.
             */
            default => 'Your own trusted contact sees them, if you have named one. RealVows does '
                .'not display them to any other member, at any stage, whether or not you have '
                .'both said yes. What your trusted contact chooses to show you himself is '
                .'between the two of you and is not something we do or record.',
        };
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
                'heading' => 'Respect the people around them',
                'body' => [
                    'Many members here are advised by a trusted contact, usually a father or a '
                        .'brother. That person is part of how this works and is owed the same '
                        .'courtesy as the member themselves.',
                    'Do not try to reach somebody around their trusted contact, and do not press '
                        .'them to answer without one. If a trusted contact declines an '
                        .'introduction, that is the answer.',
                    'Do not ask a member to hide anything about this from their family. A '
                        .'request to keep it quiet is the clearest warning sign there is, and we '
                        .'act on reports of it.',
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
