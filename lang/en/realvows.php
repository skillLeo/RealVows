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
        'waiting_one' => 'One person is waiting to meet you.',
        'waiting_many' => ':count people are waiting to meet you.',
        'yours' => 'Your introductions.',
        'chosen' => 'Chosen for you by our matching, not by browsing. Open one whenever you\'re ready.',
        'no_browsing' => 'Chosen for you — RealVows has no profile browsing.',

        // The empty state is doing real work. A marriage platform that says
        // "no results" has failed the person reading it.
        'searching_heading' => 'We haven\'t found someone we\'d stand behind yet.',
        'searching_body' => 'We would rather send you nobody than send you the wrong person. As soon as someone meets the bar, they will be here.',

        'all_open_heading' => 'You\'ve opened all your introductions.',
        'all_open_body' => 'When you would like more, they are here. There is no rush, and nothing expires.',

        'unsealed' => 'They\'re yours to meet.',
        'credits_label' => 'Introductions to open',
        'ready' => 'Your profile is ready, and it stays ready.',
    ],

    'verification' => [
        // Never "instantly". Shared hosting batches work by cron.
        'pending_heading' => 'Your profile is with our team.',
        'pending_body' => 'A real person is reviewing it — usually within a day. We will email you when it is done, and your introductions will be waiting.',
        'rejected_heading' => 'We need one more look.',
        'incomplete_heading' => 'Finish your profile.',
        'incomplete_body' => 'You are :percent% of the way there. Once it is complete and verified, we start looking.',
    ],

    'match' => [
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
        'one_payment' => 'One payment. Nothing recurring, and nothing expires — open them whenever you are ready.',
        'card_safety' => 'Your card details go straight to the payment provider. They never touch RealVows.',
    ],

    'checkout' => [
        'confirming' => 'Confirming with your bank.',
        'do_not_close' => 'This can take a moment. Please do not close this window or press back.',
        'thanks' => 'Thank you.',
        'failed_heading' => 'That didn\'t go through.',

        // The most important sentence on the failure screen.
        'not_charged' => 'You have not been charged. There is nothing to cancel and nothing to claim back.',
        'try_again' => 'Try again',
        'untouched' => 'Your profile is untouched and your introductions are still waiting.',

        // Gateway codes mean nothing to a member. What they need is whether
        // they were charged and what to try next.
        'decline_generic' => 'The payment did not go through, and nothing was charged. It is worth trying again — if it happens twice, your bank will know why.',
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

    'report' => [
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
        'notifications' => 'When we have news',
        'email_matches' => 'Email me when there is someone new',
        'whatsapp_matches' => 'Message me on WhatsApp',
        'whatsapp_note' => 'We\'ll use this once WhatsApp delivery is switched on.',
        'receipts' => 'Receipts',
        'receipts_note' => 'Always sent. A receipt for money you have paid is a record you are entitled to, so there is nothing to switch off here.',
    ],

    'purchases' => [
        'heading' => 'Purchase history',
        'empty_heading' => 'Nothing here yet.',
        'empty_body' => 'Your receipts will appear here once you open your first introduction.',
        'receipt' => 'Receipt',
    ],

    'offline' => [
        'heading' => 'You\'re offline.',
        'body' => 'Your matches are safe — we\'ll bring them back when you reconnect.',
    ],
];
