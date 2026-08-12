<?php

namespace App\Http\Controllers;

use App\Services\Ai\ProfileWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Suggest a tidier version of a member's own words.
 *
 * ⚠️ IT SUGGESTS. IT DOES NOT SAVE.
 *
 * This endpoint returns both versions and writes nothing. The member reads them
 * side by side and chooses, and the choice goes through the ordinary profile
 * save. A rewrite that saved itself would mean somebody's profile changed
 * because they pressed a button labelled "improve", which is not consent.
 */
class ProfileWriterController extends Controller
{
    public function __invoke(Request $request, ProfileWriter $writer): JsonResponse
    {
        /*
         * ⚠️ 404 WHEN THE FEATURE IS OFF, not a message explaining a feature
         * nobody configured. With no API key the button is hidden, so a request
         * arriving here at all is somebody probing.
         */
        abort_unless($writer->isEnabled(), 404);

        $validated = $request->validate([
            'bio' => ['required', 'string', 'min:40', 'max:4000'],
        ]);

        /*
         * ⚠️ FIVE A DAY, PER MEMBER. A rewrite costs real money on somebody
         * else's account and a member cycling for a version they like better is
         * using a slot machine, not a writing aid. Keyed on the user rather
         * than the IP: two members on one household connection are two people.
         */
        $key = 'profile-writer:'.$request->user()->id;

        if (RateLimiter::tooManyAttempts($key, ProfileWriter::DAILY_LIMIT)) {
            return response()->json([
                'message' => 'You have used your rewrites for today. Your own words are saved '
                    .'and nothing has been lost.',
            ], 429);
        }

        /*
         * ⚠️ AN EXPLICIT NUMBER OF SECONDS, NOT A CARBON DIFF.
         *
         * This was `now()->addDay()->diffInSeconds(now())`, which reads as "one
         * day" and evaluates to MINUS 86,400 — Carbon returns a signed
         * difference and the later instant is on the left. A negative decay
         * expires the counter immediately, so the limit never limited anything
         * and the sixth, sixtieth and six-hundredth rewrite all succeeded.
         *
         * It failed open, on the one control standing between a member and an
         * unbounded bill on somebody else's API account.
         */
        RateLimiter::hit($key, ProfileWriter::LIMIT_WINDOW_SECONDS);

        $result = $writer->rewrite($validated['bio']);

        /*
         * ⚠️ NULL MEANS KEEP THEIR OWN WORDS, and the member is told nothing
         * beyond that. "The model invented a place so we threw it away" is
         * true, unhelpful, and slightly alarming.
         */
        if ($result === null) {
            return response()->json([
                'rewrite' => null,
                'message' => 'We could not improve on what you wrote. Your version is good as it is.',
            ]);
        }

        return response()->json([
            'original' => $validated['bio'],
            'rewrite' => $result['rewrite'],
            'model' => $result['model'],
        ]);
    }
}
