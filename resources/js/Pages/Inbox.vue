<script setup>
/**
 * ⚠️ FILTERING BY STATE IS NAVIGATION. SEARCHING BY ATTRIBUTE IS BROWSING.
 *
 * This screen is where browsing could creep back in, because it looks like a
 * list and lists invite search boxes. There is deliberately no text input here
 * and no client-side filtering of any kind — the state tabs are links that
 * re-query the server, and the server accepts nothing but a state from a
 * closed list.
 *
 * If a search field is ever added here, the product has stopped being what it
 * promises to be. InboxTest fails the build if one appears.
 */
import { getCurrentInstance } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseCard from '@/Components/BaseCard.vue';
import MatchCard from '@/Components/MatchCard.vue';

const props = defineProps({
    state: { type: String, default: 'all' },
    states: { type: Object, default: () => ({}) },
    matches: { type: Array, default: () => [] },
});

const $t = getCurrentInstance().appContext.config.globalProperties.$t;

/*
 * The member's words, not the schema's. "Waiting" rather than "teaser",
 * "Closed" rather than "expired" — a member has never heard of a teaser, and
 * "expired" reads as a failure they caused.
 */
const tabs = ['all', 'waiting', 'open', 'interested', 'passed', 'closed'];

const emptyLine = () => $t(`inbox.empty_${props.state}`);
</script>

<template>
    <AppLayout :title="$t('inbox.heading')">
        <div class="rv-stack">
            <header>
                <h1 class="rv-display">{{ $t('inbox.heading') }}</h1>
                <p class="rv-quiet">{{ $t('inbox.chosen_for_you') }}</p>
            </header>

            <!--
                Links rather than buttons, so each state is a real URL a member
                can return to, and so this works with JavaScript disabled.
            -->
            <nav class="rv-inbox__tabs" :aria-label="$t('inbox.filter_label')">
                <Link
                    v-for="tab in tabs"
                    :key="tab"
                    :href="`/introductions?state=${tab}`"
                    class="rv-inbox__tab"
                    :class="{ 'is-current': state === tab }"
                    :aria-current="state === tab ? 'page' : undefined"
                >
                    {{ $t(`inbox.tab_${tab}`) }}
                    <span v-if="states[tab]" class="rv-inbox__count">{{ states[tab] }}</span>
                </Link>
            </nav>

            <div v-if="matches.length" class="rv-inbox__list">
                <MatchCard v-for="match in matches" :key="match.id" :match="match" />
            </div>

            <BaseCard v-else>
                <p class="rv-quiet">{{ emptyLine() }}</p>
            </BaseCard>
        </div>
    </AppLayout>
</template>
