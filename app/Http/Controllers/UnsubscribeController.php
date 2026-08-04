<?php

namespace App\Http\Controllers;

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Unsubscribing without signing in.
 *
 * ⚠️ IT MUST WORK WITH NO SESSION, AND THAT IS NOT A CONVENIENCE.
 *
 * A member who wants to stop receiving email is frequently a member who cannot
 * or will not sign in — they have forgotten the password, they never finished
 * the profile, or they are annoyed enough that "log in first" reads as an
 * obstacle deliberately placed in front of them. An unsubscribe link behind
 * authentication is the commonest reason a legitimate sender ends up marked as
 * spam, and a spam complaint costs the whole domain's deliverability.
 *
 * So: a signed URL, no session, no password.
 *
 * ⚠️ SIGNED, NOT GUESSABLE, AND SCOPED TO ONE CHANNEL.
 *
 * The signature is what proves the request concerns this member, since there
 * is no session to ask. Without it the route would be an enumeration tool: an
 * id in a URL that switches off somebody else's notifications.
 *
 * ⚠️ PER CHANNEL. Unsubscribing from email must not switch off WhatsApp, and
 * the reverse — which is the same rule the inbound STOP handler follows. A
 * member who says "stop emailing me" has not said "stop telling me about the
 * introductions I paid for".
 */
class UnsubscribeController extends Controller
{
    /** The channels a member may switch off from a link. */
    private const CHANNELS = [
        'email' => 'email_matches',
        'whatsapp' => 'whatsapp_matches',
    ];

    /**
     * ⚠️ GET SHOWS A PAGE, IT DOES NOT UNSUBSCRIBE.
     *
     * Mail clients and corporate security scanners follow every link in an
     * email before a human ever sees it. A GET that acts would unsubscribe
     * members who never clicked anything — silently, and in a way nobody would
     * connect to the scanner that caused it.
     *
     * The act is the POST from the button on this page.
     */
    public function show(Request $request, User $user, string $channel): View
    {
        abort_unless(array_key_exists($channel, self::CHANNELS), 404);

        return view('unsubscribe', [
            'user' => $user,
            'channel' => $channel,
            // The signature is carried forward so the POST is provable too.
            'confirmUrl' => $request->fullUrl(),
            'already' => ! $this->enabled($user, $channel),
        ]);
    }

    public function update(Request $request, User $user, string $channel): View
    {
        abort_unless(array_key_exists($channel, self::CHANNELS), 404);

        NotificationPreference::updateOrCreate(
            ['user_id' => $user->id],
            [self::CHANNELS[$channel] => false],
        );

        return view('unsubscribe', [
            'user' => $user,
            'channel' => $channel,
            'confirmUrl' => null,
            'done' => true,
            'already' => true,
        ]);
    }

    private function enabled(User $user, string $channel): bool
    {
        return (bool) ($user->notificationPreference?->{self::CHANNELS[$channel]} ?? true);
    }
}
