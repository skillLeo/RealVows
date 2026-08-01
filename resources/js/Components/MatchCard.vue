<script setup>
import { computed } from 'vue';
import CompatibilityRing from './CompatibilityRing.vue';
import BaseButton from './BaseButton.vue';

/**
 * One introduction, locked or open.
 *
 * ⚠️ THE LOCKED STATE RENDERS NO <img> ELEMENT AT ALL.
 *
 * Not a blurred image, not a placeholder, not a hidden one. The server never
 * generates a signed URL for a locked match, so `match.photos` does not exist
 * in the payload — there is nothing here for devtools to recover, because
 * there is nothing to recover. A CSS blur over a real photograph would mean
 * anyone who opens the inspector has the full-resolution picture of a real
 * person on a marriage platform.
 *
 * Tone: an unopened envelope, never a broken or withheld thing. No
 * countdown, no scarcity, no "three people viewed you".
 */
const props = defineProps({
    match: { type: Object, required: true },
    unsealing: { type: Boolean, default: false },
    dimmed: { type: Boolean, default: false },
});

defineEmits(['unlock']);

const isOpen = computed(() => props.match.status === 'unlocked');

const name = computed(() =>
    isOpen.value && props.match.lastName
        ? `${props.match.firstName} ${props.match.lastName}`
        : props.match.firstName
);

const firstPhoto = computed(() => props.match.photos?.[0]?.url ?? null);

/**
 * Scoped by record id so two visible cards can never share a name — a
 * duplicate view-transition-name throws and silently skips the transition
 * for the whole page.
 */
const transitionName = computed(() => `match-card-${props.match.id}`);
</script>

<template>
    <article
        class="rv-match rv-pressable"
        :class="{
            'rv-match--open': isOpen,
            'rv-unsealing': unsealing,
            'rv-ceremony-dim': dimmed,
        }"
        :style="{ viewTransitionName: transitionName }"
    >
        <!-- Locked: a gradient panel. There is deliberately no image element
             in this branch at all. -->
        <div v-if="!isOpen" class="rv-veil">
            <span class="rv-veil__mark" aria-hidden="true">RV</span>
            <span class="rv-veil__sheen" aria-hidden="true" />
        </div>

        <img
            v-else-if="firstPhoto"
            class="rv-match__photo"
            :src="firstPhoto"
            :alt="`${match.firstName}`"
            loading="lazy"
            decoding="async"
        />

        <div class="rv-match__head">
            <CompatibilityRing :score="match.score" small />

            <div class="rv-match__who">
                <h3 class="rv-match__name">{{ name }}<template v-if="match.age">, {{ match.age }}</template></h3>
                <p v-if="match.city" class="rv-match__city">{{ match.city }}</p>
            </div>
        </div>

        <p v-if="match.rationale" class="rv-match__rationale">{{ match.rationale }}</p>

        <ul v-if="isOpen && match.commonalities?.length" class="rv-common">
            <li v-for="line in match.commonalities" :key="line">{{ line }}</li>
        </ul>

        <div class="rv-match__foot">
            <span v-if="!isOpen" class="rv-match__lozenge">Locked</span>

            <BaseButton v-if="isOpen" :href="`/matches/${match.id}`" variant="secondary">
                See their profile
            </BaseButton>
            <BaseButton v-else variant="primary" @click="$emit('unlock', match.id)">
                Open this introduction
            </BaseButton>
        </div>
    </article>
</template>

<style scoped>
.rv-match__foot {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-top: auto;
}

.rv-common {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin: 0;
    padding: 0;
    list-style: none;
    font-size: var(--fs-body-3);
    color: var(--ink-soft);
}

.rv-common li {
    position: relative;
    padding-left: 18px;
}

.rv-common li::before {
    content: '';
    position: absolute;
    left: 0;
    top: 8px;
    width: 8px;
    height: 1px;
    background: var(--brass);
}
</style>
