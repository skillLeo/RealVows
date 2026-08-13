<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import RvLogo from '@/Components/RvLogo.vue';
import RvIcon from '@/Components/RvIcon.vue';
import AccountMenu from '@/Components/AccountMenu.vue';

/**
 * Chrome for the staff screens.
 *
 * ⚠️ A SEPARATE LAYOUT, NOT A RESTYLED AppLayout, AND THAT IS DELIBERATE.
 *
 * AppLayout is what a member sees: a four-item bar and a bottom tab bar, built
 * so the product reads as an installed app on a phone. Staff work is the
 * opposite shape — twelve areas, on a desk, in a browser, for hours. Bending
 * one layout to serve both would mean every future change to the admin
 * furniture could reach a member's screen, and the member's screens are the
 * ones with photographs on them.
 *
 * ⚠️ THE SIDEBAR IS NOT A NICER TAB STRIP. IT IS THE ONLY WAY IN.
 *
 * The top bar carried four items while the admin routes grew to twelve, so
 * trusted contacts, reports, support, payments, emails and credits worked
 * perfectly and could be reached only by typing the URL. A reviewer could not
 * navigate to the trusted-contact queue that is their entire job. Vertical
 * space is what makes room for twelve things; that is the reason this is a
 * sidebar rather than a tidier row of tabs.
 *
 * The tree comes from App\Support\Navigation::admin(), already filtered to what
 * this person may open. The browser never decides who sees what.
 */
defineProps({
    title: { type: String, default: null },
    // The small line above the page heading: "Review", "Configuration".
    eyebrow: { type: String, default: null },
    /*
     * ⚠️ OPT OUT OF THE READING-WIDTH CAP.
     *
     * Pages here are capped at 880px because a settings form or a queue is
     * prose and controls, and a label whose input runs 1500px across is
     * unreadable and unpleasant to fill in.
     *
     * A dashboard is the opposite: it is columns and charts, and the cap left
     * 700px of empty page to the right of it on a large screen — worse the
     * further you zoomed out, because the content stopped growing while the
     * window did not. That is not a breakpoint problem and no amount of
     * container-query tuning would have found it; the content simply refused
     * the space it had.
     */
    wide: { type: Boolean, default: false },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const current = computed(() => page.url.split('?')[0]);
const allSections = computed(() => page.props.adminNav ?? []);

/*
 * ⚠️ ONE PINNED CONTROL, AND IT IS THE ACTION RATHER THAN THE DESTINATION.
 *
 * I pinned both "Your account" and "Log out" and the client was right that it
 * is one too many: two stuck buttons stop reading as a footer and start
 * reading as a second navigation competing with the first.
 *
 * The distinction that settles which one stays is what they are, not which
 * feels more important. Your account is a DESTINATION — a page, like the
 * fifteen above it — so it belongs in the list with them, under its own
 * heading. Log out is an ACTION: it changes state, it is available from
 * everywhere, and it is the one thing somebody must never have to scroll to
 * find. That is what a pinned footer is for.
 */
const sections = computed(() => allSections.value);

function logout() {
    router.post('/logout');
}

/*
 * ⚠️ THE BAR IS MEASURED, NOT GUESSED.
 *
 * The sidebar was `top: 62px` with `max-height: calc(100dvh - 62px)` — a
 * hardcoded guess at the header's height. The review banner and the gateway
 * banner sit above it, so the real offset is larger and varies by build; the
 * column was therefore taller than the space it had and its last item fell off
 * the bottom of the screen. Exactly the fault as the mobile drawer, in a
 * second place, from the same number.
 *
 * A ResizeObserver writes the true height to a custom property. It costs one
 * observer on staff screens and cannot be wrong.
 */
const bar = ref(null);
const scroller = ref(null);

let observer = null;

/*
 * ⚠️ THE SIDEBAR'S OWN SCROLL POSITION SURVIVES A NAVIGATION.
 *
 * Inertia swaps the page component and this layout is remounted with it, so
 * the scrolling element is a brand new node every time — and a new node starts
 * at the top. Click WhatsApp near the bottom of Configuration and the column
 * snapped back, hiding the item you had just chosen and every one around it.
 *
 * The client's description is exact: the sidebar should not move when the page
 * does. It is furniture. Furniture that jumps on every click reads as the page
 * reloading, which is the one thing a single-page app exists to avoid.
 *
 * ⚠️ sessionStorage, NOT A MODULE VARIABLE. A hard refresh — which the client
 * also does constantly — throws module state away, and landing at the top
 * after F5 is the same complaint again. Session-scoped so it does not outlive
 * the tab, and keyed so a member's screens can never read it.
 *
 * The proper fix is Inertia's persistent layouts, which would stop the
 * remount entirely. That means changing how every admin page declares its
 * layout, and this is the same result in fifteen lines that cannot half-apply
 * to some pages and not others.
 */
const SCROLL_KEY = 'rv.admin.nav.scroll';

function rememberScroll() {
    if (! scroller.value) return;

    try {
        sessionStorage.setItem(SCROLL_KEY, String(scroller.value.scrollTop));
    } catch {
        // Private browsing refuses sessionStorage. Losing the position is a
        // nuisance; throwing here would break the sidebar.
    }
}

function restoreScroll() {
    if (! scroller.value) return;

    try {
        const saved = Number(sessionStorage.getItem(SCROLL_KEY) ?? 0);

        if (saved > 0) scroller.value.scrollTop = saved;
    } catch {
        // As above.
    }
}

onMounted(() => {
    restoreScroll();
    scroller.value?.addEventListener('scroll', rememberScroll, { passive: true });

    if (! bar.value || typeof ResizeObserver === 'undefined') return;

    observer = new ResizeObserver(() => {
        document.documentElement.style.setProperty(
            '--rv-admin-bar',
            `${Math.round(bar.value.getBoundingClientRect().bottom)}px`
        );
    });

    observer.observe(bar.value);
});

onBeforeUnmount(() => {
    // Written on the way out too: a click can land before the last scroll
    // event has been handled.
    rememberScroll();
    scroller.value?.removeEventListener('scroll', rememberScroll);
    observer?.disconnect();
});

/*
 * ⚠️ THE LOGO GOES WHEREVER THIS PERSON CAN ACTUALLY GO.
 *
 * The wordmark used to point at the verification queue, written in by hand —
 * a permission-gated route living in furniture that renders for everybody. A
 * reviewer without verifications.view, or a member reaching this shell from
 * their account page, would click it and get a 404 from the one control every
 * screen has.
 *
 * (Written out in prose rather than quoted as markup on purpose: the
 * navigation crawler reads link targets out of this file's source and cannot
 * tell a comment from a template, so quoting the old value here reintroduced
 * the very dead link it describes. Fourth time a guard has caught my own
 * explanation of what it forbids.)
 *
 * Writing a gated route into a layout is the same defect App\Support\Navigation
 * was built to stamp out, arriving through a side door. The first item of the
 * first section is by definition something the server already decided this
 * person may open.
 */
const home = computed(() => sections.value[0]?.items[0]?.href ?? '/account');

/*
 * ⚠️ EXACT MATCH FIRST, THEN PREFIX, AND THE ORDER MATTERS.
 *
 * /admin/settings and /admin/settings/mail both begin with /admin/settings, so
 * a pure prefix test lights up two items at once and the sidebar claims you are
 * in two places. The longest matching href wins, which is the only reading that
 * is right for a nested tree.
 */
const activeHref = computed(() => {
    const all = sections.value.flatMap((section) => section.items.map((item) => item.href));

    return all
        .filter((href) => current.value === href || current.value.startsWith(`${href}/`))
        .sort((a, b) => b.length - a.length)[0] ?? null;
});

/*
 * On a narrow screen the sidebar is a drawer. It starts closed, and it closes
 * itself on navigation — a drawer still covering the page you just asked for is
 * the commonest way this pattern is got wrong.
 */
const open = ref(false);

watch(current, () => {
    open.value = false;
});

/*
 * ⚠️ ESCAPE CLOSES IT, because the drawer now covers the whole screen
 * including the control that opened it. A full-screen overlay with no keyboard
 * way out is a trap, and the scrim is not reachable by keyboard at all.
 */
function onKeydown(event) {
    if (event.key === 'Escape' && open.value) {
        open.value = false;
    }
}

onMounted(() => document.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Head :title="title" />

    <a href="#rv-main" class="rv-skip">Skip to content</a>

    <div v-if="$page.props.isDemo" class="rv-demobar" role="status">
        <strong>Review build</strong>
        <span>{{ $t('demo.banner') }}</span>
    </div>

    <!-- Carried over from AppLayout unchanged: which gateway is bound is
         stated rather than inferred, and live is rose because it is the one
         state where a mistake costs somebody real money. -->
    <div
        v-if="$page.props.gateway"
        class="rv-gatewaybar"
        :class="{ 'rv-gatewaybar--live': $page.props.gateway.isLive }"
        role="status"
    >
        <strong>{{ $page.props.gateway.label }}</strong>
        <span class="rv-gatewaybar__class">{{ $page.props.gateway.className }}</span>
        <span v-if="$page.props.gateway.host">{{ $page.props.gateway.host }}</span>
    </div>

    <div class="rv-admin">
        <header ref="bar" class="rv-admin__bar">
            <button
                type="button"
                class="rv-admin__drawer-toggle"
                :aria-expanded="open"
                aria-controls="rv-admin-nav"
                @click="open = !open"
            >
                <span class="rv-admin__lines" aria-hidden="true">
                    <span></span><span></span><span></span>
                </span>
                <span class="rv-sr-only">Sections</span>
            </button>

            <RvLogo :href="home" />

            <span class="rv-admin__badge">Administration</span>

            <AccountMenu v-if="user" :user="user" />
        </header>

        <!-- Forwarded from AppLayout so a screen that supplies a sticky bar
             does not silently lose it on the day staff can reach it. -->
        <slot name="bar" />

        <div class="rv-admin__body">
            <!-- ⚠️ A real <nav> with a real heading per group, so the tree is
                 navigable by landmark and by heading on a screen reader rather
                 than being a flat list of forty links. -->
            <nav
                id="rv-admin-nav"
                class="rv-admin__nav"
                :class="{ 'is-open': open }"
                aria-label="Administration"
            >
                <!-- ⚠️ ONLY EXISTS WHILE THE DRAWER DOES. On a desktop the
                     sidebar is permanent and has nothing to close, so a close
                     button there would be a control that does nothing. -->
                <div class="rv-admin__close">
                    <span class="rv-admin__close-label">Sections</span>
                    <button type="button" class="rv-admin__close-btn" @click="open = false">
                        <span aria-hidden="true">&times;</span>
                        <span class="rv-sr-only">Close</span>
                    </button>
                </div>

                <div ref="scroller" class="rv-admin__scroll">
                <div v-for="section in sections" :key="section.key" class="rv-admin__group">
                    <h2 class="rv-admin__group-label">{{ section.label }}</h2>

                    <ul class="rv-admin__list">
                        <li v-for="item in section.items" :key="item.key">
                            <Link
                                :href="item.href"
                                class="rv-admin__link"
                                :class="{ 'is-active': activeHref === item.href }"
                                :aria-current="activeHref === item.href ? 'page' : undefined"
                            >
                                <RvIcon
                                    v-if="item.icon"
                                    :name="item.icon"
                                    :size="17"
                                    class="rv-admin__icon"
                                />
                                <span class="rv-admin__link-text">{{ item.label }}</span>

                                <!-- ⚠️ Only when there is something to say. A
                                     tick beside every finished group turns the
                                     sidebar into a checklist and buries the
                                     two entries that still need attention. -->
                                <span
                                    v-if="item.badge > 0"
                                    class="rv-admin__badge-count"
                                    :title="`${item.badge} still to fill in`"
                                >{{ item.badge }}</span>
                            </Link>
                        </li>
                    </ul>
                </div>
                </div>

                <!-- ⚠️ PINNED BELOW THE SCROLL, NOT INSIDE IT. margin-block-start
                     auto pushes this to the bottom of the flex column, so it
                     stays put however long the section list grows. -->
                <div class="rv-admin__foot">
                    <button type="button" class="rv-admin__action rv-admin__action--out" @click="logout">
                        <RvIcon name="signout" :size="17" />
                        <span>Log out</span>
                    </button>
                </div>
            </nav>

            <!-- The scrim only exists while the drawer does. It is not a
                 button: Escape and the toggle both close, and a screen reader
                 has no use for a nameless clickable overlay. -->
            <div v-if="open" class="rv-admin__scrim" @click="open = false" />

            <main id="rv-main" class="rv-admin__main">
                <div class="rv-admin__page" :class="{ 'is-wide': wide }">
                    <p v-if="eyebrow" class="rv-eyebrow">{{ eyebrow }}</p>
                    <slot />
                </div>
            </main>
        </div>

        <slot name="actionbar" />
    </div>
</template>

<style scoped>
.rv-admin {
    min-height: 100vh;
    min-height: 100dvh;
    display: flex;
    flex-direction: column;
    background: var(--cream);
}

/* ── The bar ─────────────────────────────────────────────────────────────
   Deliberately shorter than the member bar. Staff see this for hours and a
   generous header is space taken from the queue they are working through. */
.rv-admin__bar {
    position: sticky;
    top: 0;
    z-index: 30;
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 14px 24px;
    background: var(--oxblood-deep);
    color: var(--cream);
}

.rv-admin__badge {
    margin-inline-start: auto;
    font-size: 11px;
    letter-spacing: .14em;
    text-transform: uppercase;
    color: var(--brass);
    /* A hairline rather than a filled chip: it is a label, not a status, and
       a filled pill here would compete with the gateway bar above it. */
    border: 1px solid color-mix(in oklab, var(--brass) 40%, transparent);
    border-radius: 999px;
    padding: 4px 10px;
}

@media (max-width: 860px) {
    .rv-admin__badge { display: none; }
}

.rv-admin__drawer-toggle {
    display: none;
    background: none;
    border: 0;
    padding: 8px;
    /* 44px including padding — the touch-target floor this project measures
       at a real 375px viewport rather than assuming. */
    min-width: 44px;
    min-height: 44px;
    color: inherit;
    cursor: pointer;
}

.rv-admin__lines {
    display: grid;
    gap: 4px;
}

.rv-admin__lines span {
    display: block;
    width: 20px;
    height: 1.5px;
    background: currentColor;
}

.rv-admin__body {
    flex: 1;
    display: grid;
    grid-template-columns: 264px minmax(0, 1fr);
    align-items: start;
}

/* ── The sidebar ─────────────────────────────────────────────────────────
   Sticky under the bar and scrolling on its own, so a long configuration page
   never leaves somebody scrolled away from the navigation. */
/* ⚠️ A FLEX COLUMN, so the sections scroll and the footer pins. As a grid with
   `align-content: start` the account and log-out controls were simply the last
   rows of a scrolling list, which is how they ended up cut off. */
.rv-admin__nav {
    position: sticky;
    /* Measured — see the ResizeObserver. The fallback is only for the first
       paint before it runs, and being slightly wrong for one frame is fine
       where being permanently wrong was not. */
    top: var(--rv-admin-bar, 62px);
    height: calc(100dvh - var(--rv-admin-bar, 62px));
    overflow: hidden;
    padding: 24px 14px 16px;
    display: flex;
    flex-direction: column;
    gap: 18px;
    /* Tone, not a shadow. Shadows are banned by the design system, so the
       separation between navigation and work is a single hairline and a
       slightly cooler ground. */
    background: color-mix(in oklab, var(--cream) 78%, var(--oxblood-deep) 4%);
    border-inline-end: 1px solid color-mix(in oklab, var(--ink) 8%, transparent);
}

/*
 * ⚠️ THE SECTIONS WERE FOUR LISTS SEPARATED BY WHITESPACE ALONE.
 *
 * At a glance that reads as one long list with odd gaps in it, and the labels
 * — set small, grey and uppercase — were quieter than the links beneath them,
 * so the thing naming the group was the least visible thing in it.
 *
 * A hairline above each heading does the separating, so the gap can come down
 * and the column tightens up. Brass on the label, because it is the only
 * colour in the system that reads as a heading without competing with the
 * active item's oxblood.
 */
/* Hidden on a desktop; the mobile block below turns it on. */
.rv-admin__close {
    display: none;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 0 8px 14px 12px;
    border-block-end: 1px solid color-mix(in oklab, var(--ink) 9%, transparent);
}

.rv-admin__close-label {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .16em;
    text-transform: uppercase;
    color: var(--ink-soft);
}

.rv-admin__close-btn {
    /* 44px, the touch-target floor this project measures rather than assumes. */
    min-width: 44px;
    min-height: 44px;
    display: grid;
    place-items: center;
    background: none;
    border: 0;
    font-size: 22px;
    line-height: 1;
    color: var(--ink);
    cursor: pointer;
}

/* Only the sections scroll. The footer below them never moves. */
.rv-admin__scroll {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    overscroll-behavior: contain;
    display: grid;
    gap: 18px;
    align-content: start;
    /* Room for the scrollbar so it does not sit on top of the badges. */
    padding-inline-end: 2px;
}

/* ── The two controls that must always be reachable ──────────────────────── */
.rv-admin__foot {
    margin-block-start: auto;
    padding-block-start: 14px;
    border-block-start: 1px solid color-mix(in oklab, var(--ink) 12%, transparent);
    display: grid;
    gap: 8px;
}

/*
 * ⚠️ SECONDARY, NOT PRIMARY, AND THAT IS A SAFETY DECISION AS MUCH AS A VISUAL
 * ONE. A filled oxblood button is what this system uses for the action a screen
 * WANTS you to take. Log out is not that — it is available, deliberate, and
 * should never be the brightest thing in the column or somebody working a queue
 * will eventually hit it by muscle memory. An outline reads as present without
 * inviting the press.
 */
.rv-admin__action {
    display: flex;
    align-items: center;
    gap: 10px;
    /* 44px including padding, the touch-target floor measured elsewhere in
       this project rather than assumed. */
    min-height: 44px;
    /* 12px matches the links and the group labels, so icons, text and
       headings all sit on one vertical line down the column. */
    padding: 10px 12px;
    border-radius: var(--r-sm, 8px);
    border: 1px solid color-mix(in oklab, var(--oxblood) 26%, transparent);
    background: var(--card);
    color: var(--oxblood);
    font: inherit;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: background-color 140ms var(--ease), border-color 140ms var(--ease);
}

.rv-admin__action:hover {
    background: color-mix(in oklab, var(--oxblood) 8%, var(--card));
    border-color: var(--oxblood);
}

.rv-admin__action.is-active {
    background: color-mix(in oklab, var(--oxblood) 12%, var(--card));
    border-color: var(--oxblood);
}

/* Quieter still than its neighbour: the way out should not out-rank the way in. */
.rv-admin__action--out {
    color: var(--ink-soft);
    border-color: color-mix(in oklab, var(--ink) 16%, transparent);
}

.rv-admin__action--out:hover {
    color: var(--rose);
    background: color-mix(in oklab, var(--rose) 7%, var(--card));
    border-color: color-mix(in oklab, var(--rose) 45%, transparent);
}

/* The icon must never shrink when a label wraps. */
.rv-admin__icon { flex: none; }
.rv-admin__link-text {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.rv-admin__group + .rv-admin__group {
    padding-block-start: 18px;
    border-block-start: 1px solid color-mix(in oklab, var(--ink) 9%, transparent);
}

.rv-admin__group-label {
    font-family: inherit;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .16em;
    text-transform: uppercase;
    color: var(--brass-ink);
    padding-inline: 12px;
    margin-block-end: 10px;
}

.rv-admin__list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 2px;
}

/*
 * ⚠️ NOT space-between. THAT IS WHAT MADE IT READ BACKWARDS.
 *
 * With three children — icon, label, badge — space-between spreads all three
 * across the full width, so the icon pinned left and the label drifted to the
 * middle with a gap between them. The eye then reads the label as starting in
 * the centre of the row and the icon as belonging to nothing, which is exactly
 * the impression of a right-to-left layout.
 *
 * An icon labels the thing immediately after it. They sit together at the
 * start with one gap, and only the badge is pushed to the far end.
 */
.rv-admin__link {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 12px;
    border-radius: var(--r-sm, 8px);
    color: var(--ink);
    text-decoration: none;
    font-size: 14px;
    line-height: 1.35;
    /* Colour only. Nothing here moves, because a sidebar that shifts under
       the cursor on a screen somebody uses all day is a nuisance rather than
       a delight. */
    transition: background-color 140ms var(--ease), color 140ms var(--ease);
}

.rv-admin__link:hover {
    background: color-mix(in oklab, var(--oxblood) 7%, transparent);
}

/*
 * ⚠️ THE ACTIVE STATE IS NOT COLOUR ALONE. A brass rule down the leading edge
 * carries it as well, so the current section is legible to somebody who cannot
 * separate oxblood from ink — and remains legible in high-contrast mode, where
 * background colours are commonly discarded.
 */
.rv-admin__link.is-active {
    background: color-mix(in oklab, var(--oxblood) 10%, transparent);
    color: var(--oxblood);
    font-weight: 600;
    box-shadow: inset 2px 0 0 var(--brass);
}

/* Brass, not rose. This says "unfinished", not "wrong" — an empty setting on a
   review build is the expected state, and a red count beside eight of them
   would read as eight faults. */
.rv-admin__badge-count {
    /* The only child that goes to the far edge. */
    margin-inline-start: auto;
    flex: none;
    min-width: 20px;
    padding: 1px 6px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    text-align: center;
    color: var(--brass-ink);
    background: color-mix(in oklab, var(--brass) 26%, transparent);
}

/* ⚠️ A CONTAINER, so pages inside can size themselves against the space they
 * actually have rather than against the viewport.
 *
 * The sidebar is 264px wide and the padding another 64. A page using
 * `@media (max-width: 1100px)` is therefore reasoning about 1100 while being
 * handed 772 — every breakpoint out by the width of the furniture, and out by
 * a DIFFERENT amount once the sidebar collapses to a drawer. That is not a
 * number anybody can keep in their head, and it is why the dashboard read as
 * cramped at widths where the arithmetic said it was fine. */
.rv-admin__main {
    min-width: 0;
    padding: 32px 32px 72px;
    container-type: inline-size;
    container-name: rv-admin-main;
}

.rv-admin__page {
    max-width: 880px;
    display: grid;
    gap: 8px;
}

/* Still capped, but at a width a two-column dashboard can actually use. Beyond
   about 1600px a grid stops reading as a layout and starts reading as things
   drifting apart, so this is a limit rather than an absence of one. */
.rv-admin__page.is-wide {
    max-width: 1600px;
}

/* ── Narrow screens ──────────────────────────────────────────────────────
   The sidebar becomes a drawer. It is moved with transform rather than being
   removed from the layout, so opening it cannot cause a reflow of the page
   behind it. */
@media (max-width: 900px) {
    .rv-admin__drawer-toggle { display: grid; place-items: center; }

    .rv-admin__body { grid-template-columns: minmax(0, 1fr); }

    /*
     * ⚠️ inset: 0 AND ABOVE THE BAR — NOT OFFSET BELOW IT.
     *
     * This was pinned with `inset-block: 62px 0`, a hardcoded guess at the
     * bar's height, at a z-index BELOW it. Two things were wrong and they
     * compounded: the review banner and the gateway banner sit above the bar,
     * so the bar is not at 62px and often not near it — and being underneath
     * meant the drawer slid out behind the header rather than over it, which
     * is what read as "buggy".
     *
     * A drawer that covers everything cannot be measured wrong. It carries its
     * own close control, because the toggle that opened it is now underneath.
     */
    .rv-admin__nav {
        position: fixed;
        inset: 0 auto 0 0;
        height: 100dvh;
        width: min(320px, 86vw);
        z-index: 60;
        transform: translateX(-102%);
        transition: transform 220ms var(--ease-entrance, ease);
        max-height: none;
        padding-block-start: 16px;
        overscroll-behavior: contain;
    }

    .rv-admin__nav.is-open { transform: translateX(0); }

    .rv-admin__scrim {
        position: fixed;
        inset: 0;
        z-index: 59;
        background: color-mix(in oklab, var(--ink) 44%, transparent);
    }

    .rv-admin__close { display: flex; }

    .rv-admin__main { padding: 24px 20px 64px; }
}

@media (prefers-reduced-motion: reduce) {
    .rv-admin__nav { transition: none; }
}
</style>
