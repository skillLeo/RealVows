<?php

namespace App\Console\Commands;

use App\Models\Guardian;
use Illuminate\Console\Command;

/**
 * Reminders at 3 and 7 days, and an offer to replace at 14 (B8).
 *
 * ⚠️ NEVER LET A FLOW DEADLOCK WITH NO VISIBLE REASON.
 *
 * A member whose introductions have stopped because somebody has not opened an
 * email is waiting on a person who may not know they were asked. Two reminders,
 * then we stop chasing and tell the member she can name somebody else. Three
 * touches is the limit: a fourth is nagging a stranger who did somebody a
 * favour by being named at all.
 *
 * ⚠️ IT DOES NOT CHASE FOREVER. At 14 days the record is left alone and the
 * MEMBER is offered the replacement. Continuing to email somebody who has
 * ignored two messages is how a domain earns spam complaints, and this is a
 * cold recipient who never asked to hear from us.
 */
class RemindTrustedContacts extends Command
{
    protected $signature = 'realvows:remind-trusted-contacts {--dry : Report without sending}';

    protected $description = 'Remind trusted contacts who have not responded, and flag stale ones';

    /** Days after invitation at which each action happens. */
    public const FIRST_REMINDER = 3;

    public const SECOND_REMINDER = 7;

    public const OFFER_REPLACEMENT = 14;

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');

        $pending = Guardian::query()
            ->whereIn('status', [Guardian::STATUS_INVITED, Guardian::STATUS_REGISTERED])
            ->where('is_appointed', false)

            /*
             * ⚠️ A ROW MARKED `invited` HAS NOT NECESSARILY BEEN INVITED, AND
             * THIS COMMAND USED TO BE ENTITLED TO ASSUME IT HAD.
             *
             * GuardianService writes `status => invited` when the member names
             * somebody, and until R5 that was always true the instant it was
             * written, because naming a contact required a confirmed account and
             * the message went out in the same request.
             *
             * R5 holds that message until the member confirms their own address.
             * The row still says `invited`. So without this line, the daily
             * reminder would have chased a father the platform had deliberately
             * decided not to write to yet — defeating the hold entirely, one
             * morning later, from a file that has nothing to do with it.
             *
             * ⚠️ THIS IS THE SAME SHAPE AS THE POOL. A guarantee held by
             * something that never claimed to hold it: "a row marked invited was
             * invited" was true by side effect, and nothing recorded that anyone
             * depended on it. See LOAD-BEARING-GUARANTEES.md.
             */
            ->whereHas('member', fn ($q) => $q->whereNotNull('email_verified_at'))
            ->whereNotNull('email')
            ->get();

        $reminded = 0;
        $stale = 0;

        foreach ($pending as $guardian) {
            $age = $guardian->created_at->diffInDays(now());

            /*
             * ⚠️ AT 14 DAYS WE STOP. The record is not deleted and the guardian
             * is not marked rejected — they may still act, and the member is
             * simply told she can name somebody else. Marking them rejected
             * would be the platform passing judgement on a person who has done
             * nothing wrong.
             */
            if ($age >= self::OFFER_REPLACEMENT) {
                $stale++;

                continue;
            }

            $due = match (true) {
                $age >= self::SECOND_REMINDER => self::SECOND_REMINDER,
                $age >= self::FIRST_REMINDER => self::FIRST_REMINDER,
                default => null,
            };

            if ($due === null) {
                continue;
            }

            /*
             * ⚠️ ONE REMINDER PER STAGE, NOT ONE PER RUN.
             *
             * This runs from cron every day. Without the stamp, a guardian on
             * day 8 receives a reminder every single day until they act — which
             * is the shape that gets a sending domain blocked, and it would
             * have been invisible in testing because a test runs once.
             */
            if ($guardian->last_reminded_at && $guardian->last_reminded_at->diffInDays(now()) < 3) {
                continue;
            }

            if (! $dry) {
                /*
                 * ⚠️ THE LINK IS RE-ISSUED, NOT RECOVERED.
                 *
                 * Invitation tokens are stored hashed, so the plaintext from
                 * the original email cannot be read back — that is the whole
                 * point of hashing them. A reminder therefore carries a FRESH
                 * token, and issuing one invalidates the previous.
                 *
                 * The consequence is real and acceptable: somebody who kept the
                 * first email and clicks it after a reminder has been sent will
                 * find a dead link, and the working one is in the newer message
                 * sitting above it in their inbox. The alternative is storing
                 * live credentials in plaintext so that an old email keeps
                 * working, which is a worse trade.
                 */
                ['token' => $token] = \App\Models\GuardianInvitation::issue($guardian);

                \Illuminate\Support\Facades\Mail::to($guardian->email)->send(
                    new \App\Mail\TrustedContactReminder(
                        $guardian,
                        $token,
                        isFinal: $due === self::SECOND_REMINDER,
                    )
                );

                // Email only. There is no approved WhatsApp template for a
                // trusted-contact reminder — see GuardianService.
                $guardian->forceFill(['last_reminded_at' => now()])->save();
            }

            $reminded++;
        }

        $nudged = $this->remindMembersWhoAcceptedAndNamedNobody($dry);

        if ($nudged > 0) {
            $this->info(sprintf('%s %d member%s who accepted and has named nobody yet.',
                $dry ? 'Would remind' : 'Reminded', $nudged, $nudged === 1 ? '' : 's'));
        }

        $this->info(sprintf(
            '%s %d reminder%s. %d trusted contact%s past %d days and left alone.',
            $dry ? 'Would send' : 'Sent',
            $reminded,
            $reminded === 1 ? '' : 's',
            $stale,
            $stale === 1 ? '' : 's',
            self::OFFER_REPLACEMENT,
        ));

        return self::SUCCESS;
    }

    /**
     * ⚠️ SHE SAID YES AND THEN WAS ASKED A QUESTION SHE HAS NOT ANSWERED.
     *
     * The trusted contact is requested at acceptance now, so a woman can accept
     * an introduction and stop there. The introduction stays accepted and
     * waiting — she acted, she is not ignoring it — but nothing can move, and
     * the other person is never told anything at any point.
     *
     * ⚠️ TWICE, THEN NEVER AGAIN. Day three and day seven, and after that the
     * platform stops. A prompt that keeps arriving is a platform arguing with a
     * decision she is entitled to make by doing nothing, and this one concerns
     * her family.
     *
     * ⚠️ AND IT IS DRIVEN BY responded_at, NOT BY A COUNTER. A counter is a
     * second fact that can disagree with the first. The day she accepted is
     * already recorded and cannot drift.
     */
    private function remindMembersWhoAcceptedAndNamedNobody(bool $dry): int
    {
        $gate = app(\App\Services\Guardian\GuardianGate::class);
        $sent = 0;

        $accepted = \App\Models\MatchRecord::query()
            ->where('response', \App\Models\MatchRecord::RESPONSE_INTERESTED)
            ->whereNotNull('responded_at')
            ->with('seeker.user')
            ->get()
            ->unique(fn ($match) => $match->seeker?->user_id);

        foreach ($accepted as $match) {
            $member = $match->seeker?->user;

            if (! $member || ! $gate->awaitingTrustedContact($member)) {
                continue;
            }

            /*
             * ⚠️ SOMEBODY WHO HAS NAMED ANYBODY AT ALL IS LEFT ALONE. She has
             * answered the question, and what she is waiting on now is that
             * person rather than herself. The block above chases them.
             */
            if (\App\Models\Guardian::where('member_id', $member->id)->exists()) {
                continue;
            }

            $days = (int) $match->responded_at->diffInDays(now());

            if (! in_array($days, [self::FIRST_REMINDER, self::SECOND_REMINDER], true)) {
                continue;
            }

            if (! $dry) {
                \Illuminate\Support\Facades\Mail::to($member->email)
                    ->send(new \App\Mail\NameYourTrustedContact($member, $days));
            }

            $sent++;
        }

        return $sent;
    }
}
