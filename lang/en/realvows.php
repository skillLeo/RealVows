<?php

/*
|--------------------------------------------------------------------------
| The copy deck
|--------------------------------------------------------------------------
|
| Every member-facing string in one file. Three reasons:
|
|  1. The CI content check has one file to read rather than forty.
|  2. Tone stays consistent, because it is all visible at once.
|  3. The client can be shown the wording without being shown the code.
|
| ── TONE RULES ────────────────────────────────────────────────────────────
|
|  · Sentence case throughout.
|  · NO EXCLAMATION MARKS, anywhere in the product.
|  · Errors do not apologise and are never vague about what happened.
|  · Empty states are invitations, not dead ends.
|  · An action keeps the same word through a whole flow — the button that
|    says "Open" produces a confirmation that says "Opened".
|  · They are PEOPLE, never "users", "profiles", "results" or "matches
|    found". The product INTRODUCES; it does not "return".
|
| ⚠️ Nothing here may name the compatibility mechanism. See
| ForbiddenLanguageTest, which reads this file.
|
*/

return [

    'dashboard' => [
        /*
         * ⚠️ THE ONE CLICK BETWEEN A FINISHED PROFILE AND BEING MATCHED.
         *
         * Said plainly and once. No duration, no step count, no "it only
         * takes" — the client's objection was to language that makes a small
         * thing sound like work before it has started.
         */
        'confirm_heading' => 'One last thing',
        'confirm_body' => 'We have sent a link to :email. Introductions begin once you have '
            .'opened it.',
        'confirm_resend' => 'Send it again',
        'confirm_resent' => 'Sent. It should arrive shortly.',

        /*
         * ⚠️ SHOWN INSTEAD OF A PURCHASE BUTTON, NOT BESIDE ONE. `/packages`
         * answers 404 while introductions are free, so the button that used to
         * sit here sent members to a dead page.
         */
        'nothing_to_buy' => 'Introductions are free while we are getting started, so there is nothing to buy.',

        'update_your_photos' => 'Update your photos',
        'waiting_one' => 'One person is waiting to meet you.',
        'waiting_many' => ':count people are waiting to meet you.',
        'yours' => 'Your introductions.',
        'chosen' => 'Chosen for you by our matching rather than by browsing. Open one whenever you are ready.',
        'no_browsing' => 'Chosen for you. RealVows has no profile browsing.',

        // The empty state is doing real work. A marriage platform that says
        // "no results" has failed the person reading it.
        'searching_heading' => 'We have not yet found someone we would stand behind.',
        'searching_body' => 'We would rather send you nobody than send you the wrong person. As soon as someone meets the bar, they will be here.',

        'all_open_heading' => 'You have opened all your introductions.',
        'all_open_body' => 'When you would like more, they are here. There is no rush, and nothing expires.',

        'unsealed' => 'They are yours to meet.',
        'credits_label' => 'Introductions to open',
        'ready' => 'Your profile is ready, and it stays ready.',
    ],

    'verification' => [
        // Never "instantly". Shared hosting batches work by cron.
        'pending_heading' => 'Your profile is with our team.',
        'pending_body' => 'A real person is reviewing it now. We will email you the moment it is done, and your introductions will be waiting.',
        'rejected_heading' => 'We need one more look.',
        'incomplete_heading' => 'Finish your profile.',
        'incomplete_body' => 'You are :percent% of the way there. Once it is complete and verified, we start looking.',
    ],

    'match' => [
        /*
         * ⚠️ STATED, NOT LEFT TO BE NOTICED. A card with no picture and no
         * comment on it reads as a card that failed to load one. Said plainly,
         * it reads as the product working the way it promised.
         *
         * It says who DOES see them rather than only who does not — the rule
         * is that somebody responsible looks properly, not that nobody looks.
         */
        'photos_with_contact' => 'Their photographs are with your trusted contact, who will look and advise you.',
        'locked' => 'Open this introduction',
        'open' => 'See their profile',
        'why' => 'Why we chose them for you',
        'in_their_words' => 'In their words',
        'introduced_on' => 'Introduced on :date',
        'read_more' => 'Read more',
        'read_less' => 'Show less',
    ],

    'packages' => [
        'heading' => 'Open the ones you want.',
        'reassurance_label' => 'No rush',
        'reassurance' => 'Your profile is ready and it stays ready. Introductions do not expire, there is nothing recurring, and nobody is removed while you think about it.',
        'choose' => 'Choose',
        'one_payment' => 'One payment. Nothing recurring and nothing expires. Open them whenever you are ready.',
        'card_safety' => 'Your card details go straight to the payment provider. They never touch RealVows.',
    ],

    'checkout' => [
        'choose_a_different_package' => 'Choose a different package',
        'taking_you_to_them' => 'Taking you to them now.',
        'confirming' => 'Confirming with your bank.',
        'do_not_close' => 'This can take a moment. Please do not close this window or press back.',
        'thanks' => 'Thank you.',
        'failed_heading' => 'That did not go through.',

        // The most important sentence on the failure screen.
        'not_charged' => 'You have not been charged. There is nothing to cancel and nothing to claim back.',
        'try_again' => 'Try again',
        'untouched' => 'Your profile is untouched and your introductions are still waiting.',

        // Gateway codes mean nothing to a member. What they need is whether
        // they were charged and what to try next.
        'decline_generic' => 'The payment did not go through and nothing was charged. It is worth trying again. If it happens twice, your bank will be able to tell you why.',
        'decline_bank' => 'Your bank declined the payment. They did not say why, and nothing was charged. A different card usually works.',
        'decline_expired' => 'That card has expired. Nothing was charged.',
        'decline_unknown_card' => 'That card number was not recognised. Nothing was charged.',
        'decline_timeout' => 'We did not hear back from your bank in time, so we stopped and charged nothing.',
    ],

    'credits' => [
        'none_left' => 'You have no introductions left to open.',
        // Reassures rather than scolds. Nobody was charged twice.
        'already_open' => 'That introduction is already open. You were not charged twice.',
    ],

    /*
     * ⚠️ THE WORDS FOR A DECISION THAT CANNOT BE UNDONE.
     *
     * Both answers are final, so the copy has to carry that weight without
     * threatening anybody. "Yes, I'm interested" rather than "Like"; "Not for
     * me" rather than "Reject" — a member is declining an introduction, not
     * grading a person, and the difference shows in how it reads when it is
     * you being declined.
     *
     * Note what is absent: no "match percentage", no "swipe", no streak, no
     * count of how many people are waiting. The vocabulary of a dating app is
     * kept out deliberately.
     */
    'respond' => [
        'heading' => 'What would you like to do?',
        'interested' => 'Yes, I am interested',
        'pass' => 'Not for me',
        'final' => 'Both answers are final, and we only tell someone when the interest is mutual.',
        'passed_state' => 'You passed on this introduction.',
        'interested_state' => 'You said you are interested. We will tell you if they say the same.',
        'mutual_state' => 'You are both interested. Their details are below.',
        'contact_heading' => 'How to get in touch',
        'block' => 'Never show me this person',
        'block_confirm' => 'We will not show you this person again, and they are never told.',
    ],

    'report' => [
        'anything_you_want_to' => 'Anything you want to add',
        'action' => 'Report a concern',
        'heading' => 'Report a concern',
        'privacy' => 'This goes to a person on our team. :name is never told.',
        'thanks' => 'Thank you. Someone will look at this.',
        'reasons' => [
            'misrepresented_identity' => 'They are not who they say they are',
            'inappropriate_content' => 'Something in their profile is inappropriate',
            'appears_to_be_scam' => 'This looks like a scam',
            'other' => 'Something else',
        ],
    ],

    'account' => [
        /*
         * ⚠️ Structurally unreachable today: MemberHub always returns at least
         * the introductions and help groups. It exists because that guarantee
         * lives in another file, and a future policy that hid every row would
         * otherwise shrink this page with nothing to explain why.
         */
        'hub_empty' => 'There is nothing else on your account yet.',
        'change_your_password' => 'Change your password',
        'signed_in_on_this' => 'Signed in on this device',
        'this_is_where_your' => 'This is where your matches are sent by WhatsApp.',
        'your_email_is_how' => 'Your email is how you sign in, so it cannot be changed here. Write to support and we will move it for you.',
        'your_profile_stays_exactly' => 'Your profile stays exactly as it is.',
        'notifications' => 'When we have news',
        'email_matches' => 'Email me when there is someone new',
        'whatsapp_matches' => 'Message me on WhatsApp',
        'whatsapp_note' => 'We will use this once WhatsApp delivery is switched on.',
        'receipts' => 'Receipts',
        'receipts_note' => 'Always sent. A receipt for money you have paid is a record you are entitled to, so there is nothing to switch off here.',
    ],

    'purchases' => [
        'heading' => 'Purchase history',
        'empty_heading' => 'Nothing here yet.',
        'empty_body' => 'Your receipts will appear here once you open your first introduction.',
        'receipt' => 'Receipt',
    ],

    /*
     * ⚠️ Added when the deck was finally wired up. Every one of these was
     * sitting inline in a component, which is exactly the drift the deck
     * exists to prevent — "Not verified yet" appeared in three separate files
     * and could have been reworded in one of them without anybody noticing.
     */
    'auth' => [
        'one_click_signs_you' => 'One click signs you straight in. Password for every account is',
        'register_heading' => 'Join people looking for their true soul mate.',
        'register_badge' => 'Soul mate first',
        'register_free' => 'Sign up is completely free. Build your entire profile without paying anything.',
        'register_submit' => 'Create your account',
        'age_confirm' => 'I confirm I am 18 years of age or older',
        'agree_to' => 'I agree to the',
        'terms' => 'Terms of Service',
        'have_account' => 'Already have an account?',
        'create_account' => 'Create a free account',
        'secure' => 'Secure login · 18+',

        'forgot_heading' => 'Reset your password',
        'forgot_body' => 'Enter the email you signed up with and we will send you a link.',
        'forgot_sent_heading' => 'Check your email',
        'forgot_sent_body' => 'We have sent a reset link to your address. It expires in one hour.',
        'forgot_not_arrived' => 'Did not arrive?',
        'send_again' => 'Send it again',

        'reset_heading' => 'Choose a new password',
        'reset_body' => 'Choose something you have not used here before.',

        'verify_heading' => 'One last thing',
        'verify_sent' => 'We have sent a link to',
        'verify_restart' => 'Log out and start again',
    ],

    'profile' => [
        // ⚠️ Shown on the step that is short, when Continue is pressed.
        'before_you_continue' => 'Before you continue:',

        'a_photo_of_you' => 'A photo of you holding a handwritten note with the code below. A real person checks it. This is how we keep every profile genuine.',
        'these_are_absolute_anyone' => 'These are absolute. Anyone they apply to is removed before any scoring happens, so choose only what you genuinely could not live with.',
        'not_verified' => 'Not verified yet',
        'not_confirmed' => 'Not confirmed yet',
        'whatsapp_destination' => 'We send your matches here by WhatsApp.',
        'change_in_account' => 'Need to change either of these? You can do it from your account settings.',

        'values_prompt' => 'Your five core values, most important first',
        'looking_for' => 'What are you looking for?',
        'on_disagreement' => 'When you disagree with someone close to you',
        'on_affection' => 'How you show affection',
        'about_you' => 'A few words about you',
        'prompts_are_hints' => 'Those prompts are only there to help you start. Write it however you like.',
        'no_better_end' => 'There is no better end of any of these. Put the marker where it honestly falls.',

        'body_types' => 'Body types you are open to',
        'leave_unticked' => 'Leave them all unticked if you would rather not narrow this.',

        'verification_note' => 'Never shown on your profile',
    ],

    /*
     * The inbox. ⚠️ Note what is NOT here: no "search", no "filter by", no
     * "refine". The vocabulary of a directory is absent on purpose, because
     * copy is where a product's promises quietly change.
     */
    'inbox' => [
        'heading' => 'Your introductions',
        'chosen_for_you' => 'Everyone here was chosen for you. There is nothing to browse.',
        'empty_all' => 'Your introductions will appear here as we find them. There is nothing to search. We do the looking.',
        'empty_waiting' => 'Nothing waiting just now. New introductions arrive as we find them.',
        'empty_open' => 'Nothing open at the moment.',
        'empty_interested' => 'You have not said yes to anyone yet.',
        'empty_passed' => 'You have not passed on anyone yet.',
        'empty_closed' => 'Nothing has closed yet.',
        'tab_all' => 'All',
        'tab_waiting' => 'Waiting',
        'tab_open' => 'Open',
        'tab_interested' => 'Interested',
        'tab_passed' => 'Passed',
        'tab_closed' => 'Closed',
        'filter_label' => 'Filter introductions by state',
    ],

    /*
     * ⚠️ THE FREE-PERIOD PROMISE, WHERE A MEMBER ACTUALLY SEES IT.
     *
     * The same sentence is in the refund policy, which is correct and which
     * nobody reads. A promise has to reach somebody at the moment they act on
     * it — signing up, and opening their first introduction — or it is a
     * defence we can point at later rather than a commitment they relied on.
     *
     * ⚠️ The wording matches the refund policy deliberately. If the two ever
     * drift, the page is the contract and this is marketing, which is exactly
     * the gap a complaint lives in.
     */
    'free_period' => [
        'badge' => 'Free right now',
        'signup' => 'Introductions are free while we are getting started. No card needed.',
        'kept' => 'Anything you receive during this period is yours to keep permanently, and stays open when charging begins.',
        'dashboard' => 'Your introductions are free at the moment. Open as many as you like.',
    ],

    /*
     * ⚠️ "TRUSTED CONTACT" ON EVERY SURFACE. The internal name is `guardian`
     * and not one string here says so. The client asked for it never to read
     * as religious machinery to a member.
     */
    'trusted_contact' => [
        /*
         * ⚠️ THE TWO STRINGS A STUCK MEMBER NEEDED.
         *
         * The gate has always told somebody whose contact was rejected or has
         * gone quiet that they may name another person. The form to do it was
         * a v-else on "has a contact", so it was never on the page for them —
         * the product asked them to act and gave them nothing to act with.
         */
        'name_someone_else' => 'Name somebody else',
        'rejected_because' => 'We could not confirm the person you named. :reason You can name somebody else below.',
        'heading' => 'Your trusted contact',
        'lead' => 'Someone who knows you, who can confirm you are who you say you are, and who looks at an introduction before you do.',
        /*
         * ⚠️ WRITTEN FOR SOMEBODY MEETING THE IDEA FOR THE FIRST TIME.
         *
         * A member now arrives here straight after finishing their profile
         * rather than hunting for it in a menu, so this page has to introduce
         * the whole concept in a few seconds — who this person is, why they
         * exist, and that saying no is allowed.
         *
         * Plainly, in the second person, with no jargon. "Wali" and "mahram"
         * are the right words and they are not the words a nervous
         * twenty-four-year-old needs at this moment.
         */
        'who_they_are' => 'Somebody in your family, usually your father or your brother.',
        'why_them' => 'They see an introduction before you do, and they tell you what they think. '
            .'It means you are never weighing up a stranger on your own, and it is how families '
            .'have always done this.',
        'what_they_see' => 'They see who you have been introduced to. They cannot write to '
            .'anybody, and they cannot see anything else about the platform.',
        'if_nobody' => 'If there is nobody you can name, tell us and we will arrange somebody. '
            .'You are never left out of this because of it.',
        'skip_for_now' => 'I will do this later',
        'skip_note' => 'You can add them whenever you like. Introductions start once they are '
            .'confirmed.',
        'where_this_stands' => 'Where this stands',
        'name_somebody' => 'Name somebody',
        'their_name' => 'Their full name',
        'how_you_know_them' => 'How you know them',
        'their_email' => 'Their email address',
        'their_email_help' => 'We send their invitation here.',
        'their_phone' => 'Their mobile number',
        'optional' => 'Optional.',
        'their_dob' => 'Their date of birth',
        'their_dob_help' => 'Optional. It helps us check the details add up.',
        'send_invitation' => 'Send their invitation',
        'sending' => 'Sending',
        'invited_body' => 'We have invited them. They will create their own account and confirm they know you.',
        'arranging_heading' => 'We are arranging somebody',
        'arranging_body' => 'You told us you do not have anybody to name. We will arrange somebody and let you know. There is nothing for you to do.',
        'none_heading' => 'If you do not have anybody',
        'none_body' => 'That is common and it is not a problem. We will arrange somebody, and your photographs are never shared with them.',
        'none_action' => 'I do not have a trusted contact',

        'accept_heading_named' => ':name has asked you to be her trusted contact',
        'accept_heading_generic' => 'You have been named as a trusted contact',
        'accept_what_we_do' => 'RealVows introduces people who are looking for their soul mate. Before she can be introduced to anyone, we ask somebody who knows her to confirm she is who she says she is.',
        'accept_what_is_asked' => 'You will create your own account with your own password. Then we will show you a short code and ask for one photograph of the two of you together, holding it. Nothing else is required of you.',
        'accept_your_name' => 'Your full name',
        'accept_your_email' => 'Your email address',
        'accept_your_email_help' => 'This is how you will sign in.',
        'accept_submit' => 'Create my account',
        'accept_submitting' => 'Creating your account',
    ],

    /*
     * ⚠️ THE TRUSTED CONTACT'S OWN SCREENS. They are not a member and not
     * staff. Nothing here offers browsing, search, or any sight of anybody
     * other than their ward and the person she was introduced to.
     */
    'guardian_area' => [
        /*
         * ⚠️ THE INTRODUCTION IS HIS TO MAKE NOW. The scholar ruled that
         * approving an exchange is not the same as being present for it, so
         * these details come to him rather than to his ward, and the wording
         * has to say plainly that acting on them is the expectation.
         */
        'contact_heading' => 'How to reach them',
        'contact_help' => 'These have come to you rather than to her, so that you can make the '
            .'introduction yourself. Speak with her about how she would like to go from here.',

        'dashboard_heading' => 'Introductions for :name',
        'dashboard_lead' => 'You are asked to look at each introduction before she does. Take your time.',
        'nothing_yet' => 'There is nothing waiting for you.',
        'nothing_yet_body' => 'When she is introduced to somebody, it will appear here and we will email you.',
        'withheld' => 'Photographs are not shared with an appointed trusted contact. They are reviewed by our team and shown to her directly.',
        'approve' => 'This one is fine',
        'decline' => 'Not this one',
        'reason' => 'Why, if you would like to say',
        'reason_help' => 'She will not be shown this. It helps us if you are ever replaced.',
        'decided' => 'Thank you. Your decision has been recorded.',

        /*
         * ⚠️ IT SAYS WHAT DID NOT HAPPEN, AND WHAT TO DO.
         *
         * The card leaves the list the instant a trusted contact decides, so
         * when the request fails the card comes back — and a card reappearing
         * with no explanation reads as the site undoing a decision on purpose.
         * The safe reading of a vague failure is to press again, which is the
         * one thing that must not become a habit here.
         */
        'decision_failed' => 'That did not save, so nothing has been recorded. Your answer is still waiting below.',

        'confirm_heading' => 'One photograph, and you are done',
        'confirm_lead' => 'We need one photograph of you and :name together, holding this code. A person on our team checks it.',
        'confirm_code_label' => 'Write this down or show it on a screen',
        'confirm_no_id' => 'We do not ask for any identity document, and we never will.',
        'confirm_upload' => 'Upload the photograph',
        'confirm_uploading' => 'Uploading',
        'confirm_done_heading' => 'Thank you, that is with us',
        'confirm_done_body' => 'Someone will check it and we will email you. There is nothing else for you to do.',
    ],

    'support' => [
        'heading' => 'Ask us something',
        'lead' => 'A person reads every message and replies here. There is nobody else in the room.',
        'unfair_prompt' => 'Your trusted contact has declined several introductions without saying why. If that does not feel right, tell us and we will look.',
        'what_about' => 'What is this about?',
        'tell_us' => 'Tell us what happened',
        'send' => 'Send',
        'sending' => 'Sending',
        'open_heading' => 'Open with us',

        /*
         * ⚠️ SHOWN INSTEAD OF HIDING THE WHOLE SECTION.
         *
         * The card used to disappear when there was nothing open, so somebody
         * who had just written to us saw the form, sent it, and found no trace
         * of it anywhere. On a page reached by a member who already feels
         * unheard, silence is the worst available answer.
         */
        'nothing_open' => 'You have nothing open with us at the moment.',
    ],

    'writer' => [
        'offer' => 'Tidy up my English',
        'working' => 'Reading what you wrote',
        'heading' => 'Two versions',
        'yours' => 'What you wrote',
        'ours' => 'A tidier version',
        'keep_mine' => 'Keep mine',
        'use_theirs' => 'Use this one',
        'nothing' => 'We could not improve on what you wrote. Your version is good as it is.',
        'promise' => 'It only tidies the English. It never adds anything you did not say.',
    ],

    'demo' => [
        'banner' => 'This is not the live platform. Every member shown here is invented, and the sign-in buttons are open to anyone.',
    ],

    'offline' => [
        'heading' => 'You are offline.',
        'body' => 'Your introductions are safe. We will bring them back when you reconnect.',
    ],
];
