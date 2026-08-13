<?php

namespace App\Http\Controllers;

use App\Support\PhotoVisibilityPolicy;
use Illuminate\View\View;

/**
 * The Blade marketing pages.
 *
 * ⚠️ NOTHING HERE TOUCHES THE DATABASE, AND THAT IS A REQUIREMENT RATHER THAN
 * A COINCIDENCE.
 *
 * These are the pages a stranger sees first, on a mid-range phone, over 4G. A
 * settings lookup on this path has already turned the home page into a 500
 * once, when the settings table was unmigrated — a database fault taking down
 * the one page that does not need a database.
 *
 * The one fact the navigation needs (is the free period on?) is read from
 * App\Support\PublicState, which is a generated file. Read that class before
 * adding anything here that queries.
 */
class PublicPageController extends Controller
{
    public function home(): View
    {
        return view('public.home');
    }

    public function howItWorks(): View
    {
        return view('public.how-it-works');
    }

    public function about(): View
    {
        return view('public.about');
    }

    public function faq(): View
    {
        /*
         * ⚠️ THE PHOTOGRAPH ANSWER IS BUILT, NOT TYPED. See the note in the
         * view. Three separate public documents carried a sentence about who
         * sees a photograph, all three went stale together, and none of them
         * was ever asked by a test. This one asks the object that decides.
         */
        return view('public.faq', [
            'photographAnswer' => $this->photographAnswer(),
        ]);
    }

    /**
     * ⚠️ THE STRICTEST SENTENCE IS THE DEFAULT ARM.
     *
     * PhotoVisibilityPolicy::level() already returns `never` if the settings
     * table cannot be read, so a database fault produces the most cautious
     * answer rather than an over-promise on a page anybody can open. That is
     * the same direction PublicState takes with the founding offer, and for the
     * same reason: a promise must never be made on a guess.
     */
    private function photographAnswer(): string
    {
        return match (app(PhotoVisibilityPolicy::class)->level()) {
            PhotoVisibilityPolicy::AFTER_CONTACT_APPROVAL => 'Your trusted contact sees them, and so does somebody you have been introduced '
                .'to once your trusted contact has approved that introduction. They are never '
                .'public and never findable by a search engine.',

            PhotoVisibilityPolicy::AT_MUTUAL_INTEREST => 'Your trusted contact sees them, and so does somebody you have been introduced '
                .'to once you have both said yes to each other. They are never public and never '
                .'findable by a search engine.',

            default => 'Your trusted contact sees them, and nobody you are introduced to ever does. '
                .'Nobody here is on display. They are never public and never findable by a '
                .'search engine.',
        };
    }
}
