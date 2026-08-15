<?php

namespace Tests\Feature;

use App\Support\WhatsAppDelivery;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ⚠️ A PUBLIC PAGE MUST NEVER PROMISE A CHANNEL THE PLATFORM CANNOT USE.
 *
 * The home page, the questions page and the terms have all been saying
 * introductions arrive "by email and WhatsApp". Every WhatsApp send is accepted
 * by Meta and then fails on a billing setting, so not one has ever been
 * delivered.
 *
 * Nobody has signed up yet, so nobody has been misled. That is luck rather than
 * design: the sentence is already a promise the platform cannot keep, and it
 * becomes a false statement to a real person on the day the first member reads
 * it — which is the same day they start waiting for a message that will not come.
 *
 * ⚠️ THE SETTING GOVERNS COPY, NOT SENDING. The sender keeps its own
 * credentials. This answers one question: what do we tell people we will do?
 */
class PublicPagesPromiseOnlyWhatWorksTest extends TestCase
{
    use RefreshDatabase;

    /** Every page that names how an introduction reaches somebody. */
    private const PAGES = ['/', '/questions', '/terms'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
    }

    private function setDelivery(bool $on): void
    {
        \App\Models\Setting::where('group', WhatsAppDelivery::GROUP)
            ->where('key', WhatsAppDelivery::KEY)
            ->update(['value' => $on ? '1' : '0']);

        app(\App\Services\Settings\SettingsService::class)->flush();
    }

    public function test_with_delivery_off_no_public_page_mentions_whatsapp(): void
    {
        $this->setDelivery(false);

        foreach (self::PAGES as $page) {
            $body = $this->get($page)->assertOk()->getContent();

            $this->assertStringNotContainsStringIgnoringCase(
                'whatsapp',
                $body,
                "{$page} promises WhatsApp while WhatsApp cannot deliver a message. A visitor "
                    .'reading that will sign up expecting something that never arrives.',
            );
        }
    }

    /**
     * ⚠️ POSITIVE CONTROL, AND WITHOUT IT THE ASSERTION ABOVE IS SATISFIED BY A
     * BROKEN PAGE.
     *
     * "WhatsApp does not appear" is equally true of a page that 500s, a page
     * that renders empty, and a template where somebody deleted the sentence
     * permanently. This proves the wording comes back when the setting is on,
     * so the absence above is the flag working rather than the copy being gone.
     */
    public function test_with_delivery_on_the_pages_say_it_again(): void
    {
        $this->setDelivery(true);

        foreach (self::PAGES as $page) {
            $this->get($page)->assertOk()->assertSee('WhatsApp', false);
        }
    }

    /**
     * ⚠️ THE PAGES STILL SAY SOMETHING TRUE WHEN THE CHANNEL IS OFF.
     *
     * Stripping the promise must not leave a member with no idea how an
     * introduction reaches them. Email is the channel that works today, and it
     * has to be named.
     */
    public function test_email_is_still_promised_when_whatsapp_is_off(): void
    {
        $this->setDelivery(false);

        foreach (self::PAGES as $page) {
            $this->get($page)->assertOk()->assertSee('email', false);
        }
    }

    /**
     * ⚠️ FAILS OFF WHEN THE SETTING CANNOT BE READ.
     *
     * Under-promising costs a member nothing. Over-promising tells somebody a
     * message is coming and leaves them waiting for it.
     */
    public function test_an_unreadable_setting_does_not_promise_the_channel(): void
    {
        \App\Models\Setting::where('group', WhatsAppDelivery::GROUP)
            ->where('key', WhatsAppDelivery::KEY)
            ->delete();

        app(\App\Services\Settings\SettingsService::class)->flush();

        $this->assertFalse(
            app(WhatsAppDelivery::class)->isOn(),
            'With the setting missing entirely the pages would claim WhatsApp. The safe '
                .'direction is silence about a channel we cannot confirm.',
        );
    }
}
