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
    | `affinity` is the internal mechanism described in SignCalculator. It is
    | never named to a member, never serialised, and never the subject of a
    | generated sentence.
    |
    */

    'weights' => [
        'affinity' => 25,
        'preferences' => 22,
        'values' => 17,
        'lifestyle' => 12,
        'personality' => 12,
        'physical' => 12,
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

    'threshold' => [
        'default' => 65,
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
