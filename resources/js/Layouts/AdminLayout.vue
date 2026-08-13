<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import RvLogo from '@/Components/RvLogo.vue';
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
const sections = computed(() => page.props.adminNav ?? []);

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
        <header class="rv-admin__bar">
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
                                <span>{{ item.label }}</span>

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
.rv-admin__nav {
    position: sticky;
    top: 62px;
    max-height: calc(100dvh - 62px);
    overflow-y: auto;
    padding: 28px 16px 40px;
    display: grid;
    gap: 28px;
    align-content: start;
    /* Tone, not a shadow. Shadows are banned by the design system, so the
       separation between navigation and work is a single hairline and a
       slightly cooler ground. */
    background: color-mix(in oklab, var(--cream) 78%, var(--oxblood-deep) 4%);
    border-inline-end: 1px solid color-mix(in oklab, var(--ink) 8%, transparent);
}

.rv-admin__group-label {
    font-family: inherit;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .13em;
    text-transform: uppercase;
    color: var(--ink-soft);
    padding-inline: 12px;
    margin-block-end: 8px;
}

.rv-admin__list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 2px;
}

.rv-admin__link {
    display: flex;
    align-items: center;
    justify-content: space-between;
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

    .rv-admin__nav {
        position: fixed;
        inset-block: 62px 0;
        inset-inline-start: 0;
        width: min(300px, 84vw);
        z-index: 25;
        transform: translateX(-102%);
        transition: transform 220ms var(--ease-entrance, ease);
        max-height: none;
    }

    .rv-admin__nav.is-open { transform: translateX(0); }

    .rv-admin__scrim {
        position: fixed;
        inset-block: 62px 0;
        inset-inline: 0;
        z-index: 24;
        background: color-mix(in oklab, var(--ink) 38%, transparent);
    }

    .rv-admin__main { padding: 24px 20px 64px; }
}

@media (prefers-reduced-motion: reduce) {
    .rv-admin__nav { transition: none; }
}
</style>
