<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Component weights — THE ONLY PLACE THESE NUMBERS EXIST
    |--------------------------------------------------------------------------
    |
    | These define the product. They live in code, not in the settings table,
    | so that no click in the admin panel can change what RealVows means by a
    | good match. The Matching settings group displays them read-only.
    |
    | They must sum to 100. MatchingConfigTest fails loudly if they stop, so
    | nobody can edit one number without editing another.
    |
    | ── ⚠️ THE AFFINITY COMPONENT IS GONE, AND IT IS NOT COMING BACK ─────────
    |
    | It was 25 points of affinity, computed from date of birth, and it was
    | the largest single weight in the product. It has been removed
    | entirely rather than switched off: the matrix, the scorer, the calculator
    | and the stored component are all deleted.
    |
    | That changes what this file is. The weights used to describe a mechanism
    | that could not be explained to the person it was applied to. They now
    | describe something a member could be told in full without the product
    | being any worse for it: RealVows scores what you told us about yourself.
    |
    | ── ⚠️ THE CLIENT SET THESE FIVE NUMBERS HIMSELF — R10 §4.1, 2026-08-29 ──
    |
    | He sent "40% 25% 20% 10% 5%" in that order and confirmed separately that
    | Preference Fulfilment is the primary at forty. They are assigned below in
    | the reference page's own display order, which is the order he sent them
    | in.
    |
    | What changed and why it matters: preferences rises 28 → 40 and becomes the
    | largest weight in the product. The reasoning he gave for it is in the
    | rewritten compatibility article — if the basics do not align, it throws
    | most other things off. Personality falls 17 → 10 and physical 13 → 5,
    | which is the product saying plainly that what you say you want counts for
    | more than what you happen to like.
    |
    | ⚠️ THE PUBLIC PAGE READS THESE. It does not restate them.
    |
    | The Science of Harmony section renders its five percentages and its bar
    | widths from this array, and MatchingConfigTest asserts the rendered page
    | equals this file. A number typed into a Blade template would be a claim
    | about the product that the product itself could contradict, silently, for
    | as long as nobody checked.
    |
    */

    'weights' => [
        'preferences' => 40,
        'values' => 25,
        'lifestyle' => 20,
        'personality' => 10,
        'physical' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Business dials — defaults and bounds only
    |--------------------------------------------------------------------------
    |
    | Unlike the weights, these two are the owner's to tune, and the SETTINGS
    | TABLE is their source of truth (matching.minimum_score_threshold and
    | matching.matches_per_batch). What lives here is the fallback used before
    | anything is saved, plus the bounds the save is clamped to.
    |
    | The bounds are not decoration. A threshold of 5 would introduce members
    | to strangers and call it matching; a threshold of 99 would show nobody
    | anything. Both would look like the product was broken.
    |
    */

    /*
    | ⚠️ 70, NOT 65 — R10 §4.2. The client's words were "seventy percent is
    | good". The public page states this number, and MatchingConfigTest asserts
    | the page and this file agree, so it cannot drift into being a lie.
    */
    'threshold' => [
        'default' => 70,
        'min' => 50,
        'max' => 85,
    ],

    'teaser_count' => [
        'default' => 8,
        'min' => 3,
        'max' => 12,
    ],

    /*
    |--------------------------------------------------------------------------
    | Batch sizes
    |--------------------------------------------------------------------------
    |
    | Shared hosting runs the queue from a one-minute cron with
    | --stop-when-empty. Nothing may assume it will be allowed to finish, so
    | the nightly sweep works in chunks small enough that being killed
    | mid-run costs at most one chunk.
    |
    */

    'nightly_chunk' => 50,

    // How long a profile edit waits before its re-run fires, so somebody
    // editing six fields queues one job rather than six.
    'regenerate_debounce_minutes' => 10,

    // A teaser set older than this is refreshed by the nightly sweep.
    'stale_after_days' => 7,

];
