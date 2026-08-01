<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import RvIcon from './RvIcon.vue';

/**
 * The account dropdown behind the avatar.
 *
 * Closes on outside click, on Escape, and on navigation. Log out is a POST
 * through the router rather than a link, so it carries the CSRF token and
 * cannot be triggered by someone else's page linking at it.
 */
const props = defineProps({
    user: { type: Object, required: true },
});

const open = ref(false);
const root = ref(null);

const initial = computed(() =>
    (props.user.first_name || props.user.name || '·').charAt(0).toUpperCase()
);

function onDocumentClick(event) {
    if (open.value && root.value && !root.value.contains(event.target)) {
        open.value = false;
    }
}

function onKeydown(event) {
    if (event.key === 'Escape') open.value = false;
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onKeydown);
});

function go(href) {
    open.value = false;
    router.visit(href);
}

function logout() {
    open.value = false;
    router.post('/logout');
}
</script>

<template>
    <div ref="root" class="rv-menu">
        <button
            type="button"
            class="rv-menu__trigger"
            :aria-expanded="open"
            aria-haspopup="menu"
            :aria-label="`Account menu for ${user.first_name || user.name}`"
            @click="open = !open"
        >
            <span class="rv-menu__name">{{ user.first_name || user.name }}</span>
            <span class="rv-menu__avatar" aria-hidden="true">{{ initial }}</span>
            <span class="rv-menu__chevron" :class="{ 'is-open': open }" aria-hidden="true">
                <RvIcon name="arrow-right" :size="14" />
            </span>
        </button>

        <Transition name="rv-menu-pop">
            <div v-if="open" class="rv-menu__panel" role="menu">
                <span class="rv-menu__label">{{ user.email }}</span>

                <span class="rv-menu__rule" />

                <button type="button" class="rv-menu__item" role="menuitem" @click="go('/dashboard')">
                    Your matches
                </button>
                <button type="button" class="rv-menu__item" role="menuitem" @click="go('/profile')">
                    Your profile
                </button>
                <button type="button" class="rv-menu__item" role="menuitem" @click="go('/packages')">
                    Match packages
                </button>
                <button type="button" class="rv-menu__item" role="menuitem" @click="go('/account')">
                    Account settings
                </button>

                <template v-if="user.is_admin">
                    <span class="rv-menu__rule" />
                    <span class="rv-menu__label">Administration</span>
                    <button type="button" class="rv-menu__item" role="menuitem" @click="go('/admin/verifications')">
                        Verification queue
                    </button>
                    <button type="button" class="rv-menu__item" role="menuitem" @click="go('/admin/settings')">
                        Settings
                    </button>
                </template>

                <span class="rv-menu__rule" />

                <button type="button" class="rv-menu__item rv-menu__item--quiet" role="menuitem" @click="logout">
                    Log out
                </button>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.rv-menu {
    position: relative;
}

.rv-menu__trigger {
    min-height: var(--tap-min);
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0 6px 0 12px;
    border: 1px solid transparent;
    border-radius: var(--r);
    background: transparent;
    color: var(--cream);
    cursor: pointer;
    transition:
        background var(--dur-tap) var(--ease),
        border-color var(--dur-tap) var(--ease);
}

.rv-menu__trigger:hover,
.rv-menu__trigger[aria-expanded='true'] {
    border-color: var(--hairline);
    background: rgba(185, 145, 84, 0.12);
}

.rv-menu__name {
    display: none;
    font-size: var(--fs-body-2);
    font-weight: 500;
    color: rgba(242, 239, 233, 0.86);
}

@media (min-width: 480px) {
    .rv-menu__name {
        display: inline;
    }
}

.rv-menu__avatar {
    width: 36px;
    height: 36px;
    flex: 0 0 auto;
    border: 1px solid var(--brass);
    border-radius: var(--r);
    background: var(--oxblood);
    color: var(--brass-bright);
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: var(--display);
    font-size: var(--fs-body-2);
    line-height: 1;
}

.rv-menu__chevron {
    display: flex;
    color: var(--brass);
    transform: rotate(90deg);
    transition: transform var(--dur-tap) var(--ease);
}

.rv-menu__chevron.is-open {
    transform: rotate(-90deg);
}

.rv-menu__panel {
    position: absolute;
    right: 0;
    top: calc(100% + 10px);
    z-index: 50;
    min-width: 240px;
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 12px;
    border: 1px solid var(--brass);
    border-radius: var(--r);
    background: var(--card);
    transform-origin: top right;
}

.rv-menu__label {
    padding: 8px 12px 4px;
    font-size: var(--fs-body-3);
    font-weight: 500;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--ink-soft);
    overflow: hidden;
    text-overflow: ellipsis;
}

.rv-menu__rule {
    height: 1px;
    margin: 6px 0;
    background: var(--hairline-soft);
}

.rv-menu__item {
    min-height: var(--tap-min);
    display: flex;
    align-items: center;
    padding: 0 12px;
    border: 0;
    border-radius: var(--r);
    background: transparent;
    color: var(--ink);
    font-size: var(--fs-body-2);
    text-align: left;
    cursor: pointer;
    transition:
        background var(--dur-tap) var(--ease),
        color var(--dur-tap) var(--ease);
}

.rv-menu__item:hover {
    background: color-mix(in oklab, var(--brass) 12%, var(--card));
    color: var(--oxblood);
}

.rv-menu__item--quiet {
    color: var(--rose);
}

.rv-menu-pop-enter-active,
.rv-menu-pop-leave-active {
    transition:
        opacity 160ms var(--ease),
        transform 160ms var(--ease);
}

.rv-menu-pop-enter-from,
.rv-menu-pop-leave-to {
    opacity: 0;
    transform: translateY(-6px) scale(0.98);
}
</style>
