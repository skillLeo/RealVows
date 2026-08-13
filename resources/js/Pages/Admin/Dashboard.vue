<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import BaseCard from '@/Components/BaseCard.vue';
import RvBarChart from '@/Components/RvBarChart.vue';
import RvDonut from '@/Components/RvDonut.vue';

const props = defineProps({
    scope: { type: Object, required: true },
    members: { type: Object, required: true },
    queues: { type: Object, required: true },
    introductions: { type: Object, required: true },
    signups: { type: Array, required: true },
    genderSplit: { type: Array, default: null },
    completeness: { type: Array, required: true },
    freePeriod: { type: Boolean, default: false },
    insights: { type: Array, default: () => [] },
    you: { type: Object, default: null },
});

/*
 * ⚠️ THE QUEUE TILES LEAD, AND THE ORDER IS THE ARGUMENT.
 *
 * A dashboard's job on this product is not to describe the platform, it is to
 * answer "is anybody waiting on me?" — because on the other side of every one
 * of these numbers is a person who submitted a photograph and is waiting to
 * hear. Totals are context and sit underneath.
 */
const waiting = computed(() => [
    { label: 'Verifications', value: props.queues.verifications, href: '/admin/verifications' },
    { label: 'Trusted contacts', value: props.queues.trustedContacts, href: '/admin/trusted-contacts' },
    { label: 'Reports', value: props.queues.reports, href: '/admin/reports' },
    { label: 'Support', value: props.queues.support, href: '/admin/support' },
]);

const nothingWaiting = computed(() => waiting.value.every((tile) => tile.value === 0));

const signupBars = computed(() =>
    props.signups.map((row) => ({
        // "13 Aug" is readable; the ISO date is not, at 14 across.
        label: new Date(`${row.day}T00:00:00`).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }),
        count: row.count,
    }))
);

const verificationSplit = computed(() =>
    [
        { label: 'Verified', count: props.members.verified },
        { label: 'Waiting', count: props.members.awaiting },
        { label: 'Not submitted', count: props.members.unsubmitted },
    ].filter((row) => row.count > 0)
);
</script>

<template>
    <AdminLayout title="Dashboard" eyebrow="Overview">
        <div class="rv-stack" style="gap: 32px">
            <header class="rv-stack" style="gap: 8px">
                <h1 class="rv-d2">Dashboard</h1>
                <!-- ⚠️ THE SCOPE IS STATED ON THE PAGE, ALWAYS. A gender-scoped
                     reviewer seeing "18 members" must not read it as the size
                     of the platform. A number without its scope is a number
                     somebody will quote in a meeting. -->
                <p class="rv-help">{{ scope.label }}</p>
            </header>

            <!-- ⚠️ THE RAIL COMES FIRST IN THE MARKUP AND SECOND ON SCREEN.
                 Grid places it in the right-hand column on a wide viewport; in
                 source order it stays first, so on a phone — and to a screen
                 reader, and in the tab order — the things needing attention
                 are read before the charts describing them. Ordering by CSS
                 alone would put the actions last for exactly the people who
                 cannot see the layout. -->
            <div class="rv-dash">
                <aside class="rv-dash__rail">

            <!-- ── What this means ────────────────────────────────────────
                 ⚠️ FIRST ON THE PAGE, ABOVE EVERY NUMBER.

                 The client is not technical. Charts tell him what is true;
                 these tell him what to do about it, in the order it matters.
                 Written in PHP from the figures below rather than by a model —
                 see App\Support\DashboardInsights for why that is a
                 correctness decision and not a saving. -->
            <section class="rv-stack" style="gap: 12px">
                <span class="rv-label">What needs you</span>

                <!-- ⚠️ DashboardInsights always returns at least one line —
                     it falls back to "nothing needs you" precisely so this
                     panel is never blank. This branch is here because that is a
                     promise made in another file, and a heading with nothing
                     under it is what the reader gets on the day it stops being
                     kept. Caught by EmptyStatesTest on this very page. -->
                <p v-if="!insights.length" class="rv-help">
                    No summary could be produced for this view.
                </p>

                <ul class="rv-insights">
                    <li
                        v-for="(insight, i) in insights"
                        :key="i"
                        class="rv-insight"
                        :class="`rv-insight--${insight.tone}`"
                    >
                        <span class="rv-insight__text">{{ insight.text }}</span>
                        <Link v-if="insight.href" :href="insight.href" class="rv-insight__go">
                            Open
                        </Link>
                    </li>
                </ul>
            </section>

            <!-- ── Your own account ───────────────────────────────────────
                 Carried over from the member dashboard so /admin is somewhere
                 you can stay. Absent entirely for staff with no member profile
                 of their own — see ownState() for why two permanent zeroes are
                 worse than nothing. -->
            <section v-if="you" class="rv-stack" style="gap: 12px">
                <span class="rv-label">Your own account</span>

                <div class="rv-tiles">
                    <Link href="/dashboard" class="rv-tile">
                        <span class="rv-tile__value" :class="{ 'is-zero': you.waiting === 0 }">
                            {{ you.waiting }}
                        </span>
                        <span class="rv-tile__label">Introductions to open</span>
                    </Link>

                    <Link href="/profile" class="rv-tile">
                        <span class="rv-tile__value" :class="{ 'is-zero': you.completeness === 0 }">
                            {{ you.completeness }}%
                        </span>
                        <span class="rv-tile__label">Your profile</span>
                    </Link>

                    <!-- ⚠️ Hidden while introductions are free. A credit
                         balance shown during the free period invites the
                         question "why do I have none?" about a currency that
                         is not being spent. -->
                    <Link v-if="!freePeriod" href="/purchases" class="rv-tile">
                        <span class="rv-tile__value" :class="{ 'is-zero': you.credits === 0 }">
                            {{ you.credits }}
                        </span>
                        <span class="rv-tile__label">Credits</span>
                    </Link>
                </div>
            </section>

                </aside>

                <div class="rv-dash__main">

            <!-- ── Who is waiting ─────────────────────────────────────────── -->
            <section class="rv-stack" style="gap: 12px">
                <span class="rv-label">Waiting on you</span>

                <p v-if="nothingWaiting" class="rv-help">
                    Nothing is waiting. Every queue is clear.
                </p>

                <div class="rv-tiles">
                    <Link v-for="tile in waiting" :key="tile.label" :href="tile.href" class="rv-tile">
                        <span class="rv-tile__value" :class="{ 'is-zero': tile.value === 0 }">
                            {{ tile.value }}
                        </span>
                        <span class="rv-tile__label">{{ tile.label }}</span>
                    </Link>
                </div>

                <p v-if="queues.oldestWaitingDays !== null" class="rv-help">
                    The longest anybody has been waiting for verification is
                    <strong>{{ queues.oldestWaitingDays }}</strong>
                    day<template v-if="queues.oldestWaitingDays !== 1">s</template>.
                </p>
            </section>

            <!-- ── Members ────────────────────────────────────────────────── -->
            <div class="rv-cols">
                <BaseCard>
                    <div class="rv-stack" style="gap: 16px">
                        <h2 class="rv-d3">Members</h2>

                        <div class="rv-figures">
                            <div>
                                <span class="rv-figure">{{ members.total }}</span>
                                <span class="rv-figure__label">Total</span>
                            </div>
                            <div>
                                <span class="rv-figure">{{ members.verified }}</span>
                                <span class="rv-figure__label">Verified</span>
                            </div>
                            <div>
                                <span class="rv-figure">{{ members.active }}</span>
                                <!-- The definition travels with the number.
                                     "Active" otherwise means whatever the
                                     reader assumes it means. -->
                                <span class="rv-figure__label">Signed in, 30 days</span>
                            </div>
                        </div>

                        <RvDonut :data="verificationSplit" empty-message="No members yet." />
                    </div>
                </BaseCard>

                <BaseCard v-if="genderSplit">
                    <div class="rv-stack" style="gap: 16px">
                        <h2 class="rv-d3">Who has joined</h2>
                        <RvDonut :data="genderSplit" empty-message="No members yet." />
                    </div>
                </BaseCard>

                <!-- ⚠️ ABSENT, NOT EMPTY, for a scoped reviewer. They see one
                     gender by design, so a split of it is a pie with one slice
                     — a chart whose only message is that the reader is not
                     allowed to see the rest. -->
                <BaseCard v-else>
                    <div class="rv-stack" style="gap: 8px">
                        <h2 class="rv-d3">Who has joined</h2>
                        <p class="rv-help">
                            The breakdown across the whole platform is not part of your review
                            scope.
                        </p>
                    </div>
                </BaseCard>
            </div>

            <!-- ── Signups ────────────────────────────────────────────────── -->
            <BaseCard>
                <div class="rv-stack" style="gap: 16px">
                    <div class="rv-stack" style="gap: 4px">
                        <h2 class="rv-d3">Joining, last 14 days</h2>
                        <p class="rv-help">Quiet days are drawn, not skipped.</p>
                    </div>

                    <RvBarChart
                        :data="signupBars"
                        :height="150"
                        empty-message="Nobody has joined in the last fortnight."
                    />
                </div>
            </BaseCard>

            <!-- ── Profiles and introductions ─────────────────────────────── -->
            <div class="rv-cols">
                <BaseCard>
                    <div class="rv-stack" style="gap: 16px">
                        <h2 class="rv-d3">How complete profiles are</h2>
                        <RvBarChart
                            :data="completeness"
                            :height="130"
                            empty-message="No profiles yet."
                        />
                    </div>
                </BaseCard>

                <BaseCard>
                    <div class="rv-stack" style="gap: 16px">
                        <h2 class="rv-d3">Introductions</h2>

                        <div class="rv-figures">
                            <div>
                                <span class="rv-figure">{{ introductions.total }}</span>
                                <span class="rv-figure__label">Made</span>
                            </div>
                            <div>
                                <span class="rv-figure">{{ introductions.delivered }}</span>
                                <span class="rv-figure__label">Delivered</span>
                            </div>
                            <div>
                                <span class="rv-figure">{{ introductions.opened }}</span>
                                <span class="rv-figure__label">Opened</span>
                            </div>
                        </div>

                        <!-- ⚠️ SAID, NOT DRAWN. There is no revenue during the
                             free period by design, and an empty money chart
                             reads as a broken one. -->
                        <p v-if="freePeriod" class="rv-help">
                            The free period is on, so nothing has been charged and
                            {{ introductions.unlocked }} introduction<template
                                v-if="introductions.unlocked !== 1"
                            >s</template>
                            were opened at no cost.
                        </p>
                        <p v-else class="rv-help">
                            {{ introductions.unlocked }} introduction<template
                                v-if="introductions.unlocked !== 1"
                            >s</template>
                            have been unlocked.
                        </p>
                    </div>
                </BaseCard>
            </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
/* ── Two columns ─────────────────────────────────────────────────────────
   The rail is a third of the width, not the full span. A one-sentence row
   stretched across 900px leaves a canyon between the words and the action at
   the end of it, and the eye has to travel the whole way to find out what it
   can do. Narrow, the sentence and its link are one object. */
.rv-dash {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(280px, 340px);
    gap: 28px;
    align-items: start;
}

/* Explicit placement, because the rail is FIRST in the markup so that a phone
   and a screen reader meet the actions before the charts. */
.rv-dash__main { grid-column: 1; grid-row: 1; }

.rv-dash__rail {
    grid-column: 2;
    grid-row: 1;
    display: grid;
    gap: 28px;
    align-content: start;
    /* Follows down a long page. The queues are the reason this screen exists;
       scrolling to the completeness chart should not lose them. */
    position: sticky;
    top: 90px;
}

/* One column below the breakpoint, and the rail keeps its source position —
   first — which is the right order when there is no room for two. */
@media (max-width: 1100px) {
    .rv-dash {
        grid-template-columns: minmax(0, 1fr);
    }

    .rv-dash__main { grid-column: 1; grid-row: 2; }

    .rv-dash__rail {
        grid-column: 1;
        grid-row: 1;
        position: static;
    }
}

/* ── The summary ─────────────────────────────────────────────────────────
   A rule down the leading edge carries the severity, not a coloured
   background. Three tinted panels stacked read as an alert dialogue; a
   hairline reads as a margin note, which is what these are. It also keeps
   the text on the page's own ground, so contrast never depends on the tint. */
.rv-insights {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 8px;
}

.rv-insight {
    display: grid;
    justify-items: start;
    gap: 8px;
    padding: 12px 16px;
    border-radius: var(--r, 12px);
    background: var(--card);
    border: 1px solid color-mix(in oklab, var(--ink) 8%, transparent);
    box-shadow: inset 3px 0 0 var(--brass);
}

/* ⚠️ Rose is reserved for the one state where somebody is being kept waiting
   or a member has been reported. If everything were rose, nothing would be. */
.rv-insight--urgent { box-shadow: inset 3px 0 0 var(--rose); }
.rv-insight--good { box-shadow: inset 3px 0 0 color-mix(in oklab, var(--brass) 45%, transparent); }

.rv-insight__text {
    color: var(--ink);
    line-height: 1.5;
}

.rv-insight__go {
    flex: none;
    font-size: 13px;
    color: var(--oxblood);
    text-decoration: none;
    border-bottom: 1px solid color-mix(in oklab, var(--oxblood) 35%, transparent);
}

.rv-insight__go:hover { border-bottom-color: var(--oxblood); }

.rv-tiles {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
}

/* ⚠️ auto-fit would give the rail a single 300px-wide tile per row, which
   turns four short numbers into a column half a page long. Two up reads as a
   set. */
.rv-dash__rail .rv-tiles {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

/* Tone and a hairline. Shadows are banned, so a tile lifts by being slightly
   lighter than the page rather than by floating above it. */
.rv-tile {
    display: grid;
    gap: 4px;
    padding: 18px 20px;
    border-radius: var(--r, 12px);
    background: var(--card);
    border: 1px solid color-mix(in oklab, var(--ink) 8%, transparent);
    text-decoration: none;
    color: inherit;
    transition: border-color 140ms var(--ease), background-color 140ms var(--ease);
}

.rv-tile:hover {
    background: color-mix(in oklab, var(--oxblood) 4%, var(--card));
    border-color: color-mix(in oklab, var(--brass) 44%, transparent);
}

.rv-tile__value {
    font-family: var(--font-display, inherit);
    font-size: 30px;
    line-height: 1;
    color: var(--oxblood);
    font-variant-numeric: tabular-nums;
}

/* ⚠️ Zero is quiet, not absent. A clear queue should not shout the same way a
   queue of nine does, but it must still be readable — greying it out entirely
   would look like the number failed to load. */
.rv-tile__value.is-zero {
    color: var(--ink-soft);
}

.rv-tile__label {
    font-size: 12px;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--ink-soft);
}

.rv-cols {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 20px;
    align-items: start;
}

.rv-figures {
    display: grid;
    grid-auto-flow: column;
    grid-auto-columns: 1fr;
    gap: 16px;
}

.rv-figures > div {
    display: grid;
    gap: 2px;
}

.rv-figure {
    font-family: var(--font-display, inherit);
    font-size: 26px;
    line-height: 1;
    color: var(--oxblood);
    font-variant-numeric: tabular-nums;
}

.rv-figure__label {
    font-size: 11px;
    letter-spacing: .07em;
    text-transform: uppercase;
    color: var(--ink-soft);
}
</style>
