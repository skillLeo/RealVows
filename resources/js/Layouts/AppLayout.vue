<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import RvLogo from '@/Components/RvLogo.vue';
import BottomTabBar from '@/Components/BottomTabBar.vue';
import AccountMenu from '@/Components/AccountMenu.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useAdminShell } from '@/shell';

/**
 * Chrome for every authenticated screen.
 *
 * Desktop: a top bar carries navigation. Mobile: the top bar keeps only the
 * wordmark and the member, and the bottom tab bar carries navigation — so the
 * product reads as an installed app rather than a website.
 */
const props = defineProps({
    title: { type: String, default: null },
    // Screens with their own sticky action bar (the profile builder) manage
    // their own bottom spacing and opt out of the tab-bar padding.
    bare: { type: Boolean, default: false },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const current = computed(() => page.url.split('?')[0]);

/**
 * Navigation comes from the server, already filtered to what this person can
 * actually open. The browser does not decide who sees what — it cannot, since
 * permissions live in the database and a role name never told the whole
 * story. See App\Support\Navigation.
 */
const links = computed(() => page.props.nav ?? []);

/*
 * ⚠️ STAFF GET THE SIDEBAR ON EVERY SCREEN, INCLUDING THIS ONE.
 *
 * The admin pages were moved to AdminLayout, but /dashboard and /account are
 * shared — a member's screens that staff also open. So a reviewer working
 * through the queue would click Matches, land here, and the sidebar would
 * vanish and a top navigation bar take its place. Two different products in
 * one session.
 *
 * Delegating here rather than editing each shared page means a screen added
 * later cannot forget: it inherits the right chrome by using this layout at
 * all. Member screens are untouched — for anybody with no staff areas this
 * branch is never taken.
 */
const adminShell = useAdminShell();
</script>

<template>
    <!-- ⚠️ ONE LAYOUT DELEGATING, NOT TWO LAYOUTS MERGED. AdminLayout stays
         the only place staff chrome is defined, so a change to it can still
         never reach a member. -->
    <AdminLayout v-if="adminShell" :title="title">
        <template #bar><slot name="bar" /></template>
        <template #actionbar><slot name="actionbar" /></template>
        <slot />
    </AdminLayout>

    <template v-else>
    <Head :title="title" />

    <a href="#rv-main" class="rv-skip">Skip to content</a>

    <div v-if="$page.props.isDemo" class="rv-demobar" role="status">
        <strong>Review build</strong>
        <span>{{ $t('demo.banner') }}</span>
    </div>

    <!--
        ⚠️ WHICH GATEWAY IS BOUND, STATED RATHER THAN INFERRED.

        Real-versus-fake is decided by whether an API key exists, which made
        it invisible: the settings screen could read "PowerTranz" while every
        checkout went to a stand-in. Unanswerable state on a screen that
        handles money is the same failure class that has caught this project
        four times.

        ⚠️ LIVE IS ROSE, NOT BRASS, AND THAT IS THE POINT. The stand-in and
        the sandbox are both brass because both are safe. Live is the one
        state where a mistake costs somebody real money, so it must not
        resemble the two that cannot.
    -->
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

    <div class="rv-app">
        <header class="rv-app__bar">
            <div class="rv-shell rv-app__bar-inner">
                <RvLogo href="/dashboard" />

                <nav class="rv-app__nav" aria-label="Sections">
                    <Link
                        v-for="link in links"
                        :key="link.key"
                        :href="link.href"
                        class="rv-app__nav-link"
                        :class="{ 'is-active': current === link.href || current.startsWith(`${link.href}/`) }"
                        :aria-current="current === link.href ? 'page' : undefined"
                    >
                        {{ link.label }}
                    </Link>
                </nav>

                <AccountMenu v-if="user" :user="user" />
            </div>

            <slot name="bar" />
        </header>

        <main id="rv-main" class="rv-app__main" :class="{ 'rv-has-tabbar': !bare }">
            <slot />
        </main>

        <slot name="actionbar" />

        <BottomTabBar />
    </div>
    </template>
</template>

<style scoped>
.rv-app {
    min-height: 100vh;
    min-height: 100dvh;
    display: flex;
    flex-direction: column;
    background: var(--cream);
}

.rv-app__bar {
    position: sticky;
    top: 0;
    z-index: 20;
    background: var(--oxblood-deep);
    color: var(--cream);
}

.rv-app__bar-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px 24px;
    padding-block: 20px;
}

.rv-app__nav {
    display: none;
    flex: 1 1 auto;
    justify-content: center;
    gap: 8px;
}

@media (min-width: 768px) {
    .rv-app__nav {
        display: flex;
    }
}

.rv-app__nav-link {
    min-height: var(--tap-min);
    display: inline-flex;
    align-items: center;
    padding: 0 16px;
    border-radius: var(--r);
    color: var(--on-dark);
    font-size: var(--fs-body-2);
    font-weight: 500;
    text-decoration: none;
    transition:
        background var(--dur-tap) var(--ease),
        color var(--dur-tap) var(--ease);
}

.rv-app__nav-link:hover {
    background: rgba(197, 160, 89, 0.14);
    color: var(--cream);
}

.rv-app__nav-link.is-active {
    color: var(--brass-bright);
}

.rv-app__member {
    display: flex;
    align-items: center;
    gap: 12px;
}

.rv-app__name {
    display: none;
    font-size: var(--fs-body-2);
    font-weight: 500;
    color: rgba(242, 239, 233, 0.86);
}

@media (min-width: 480px) {
    .rv-app__name {
        display: inline;
    }
}

.rv-app__avatar {
    width: var(--tap-min);
    height: var(--tap-min);
    flex: 0 0 auto;
    border: 1px solid var(--brass);
    border-radius: var(--r);
    background: var(--oxblood);
    color: var(--brass-bright);
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: var(--display);
    font-size: var(--fs-body-1);
    line-height: 1;
}

.rv-app__main {
    flex: 1 1 auto;
}
</style>
