<script setup>
import { computed } from 'vue';
import CompatibilityRing from './CompatibilityRing.vue';
import RvSeal from './RvSeal.vue';
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

/*
 * ⚠️ NO PHOTOGRAPH REACHES THIS CARD, BY DESIGN.
 *
 * `match.photos` no longer exists in any member payload — the pictures go to
 * the trusted contact, who looks and advises. This used to read
 * `match.photos?.[0]?.url`, which under the new rule is permanently null: an
 * <img> that never renders, and a card built around a hole.
 *
 * The initial in an arch replaces it. Not a placeholder avatar — a placeholder
 * is an apology for a missing photograph, and nothing is missing here. The
 * arch is the shape this product already owns, the letter is theirs, and
 * together they occupy the space deliberately rather than by default.
 */
const initial = computed(() => (props.match.firstName ?? '?').trim().charAt(0).toUpperCase());

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
            <!-- The inner element clips the sheen; the veil does not, so the
                 seal can overhang its lower edge. -->
            <div class="rv-veil__inner">
                <span class="rv-veil__mark" aria-hidden="true">RealVows</span>
                <span class="rv-veil__sheen" aria-hidden="true" />
            </div>

            <!-- The seal sits on the flap and IS the locked state. A card
                 wearing an intact seal does not need a label saying so. -->
            <RvSeal on-flap breakable />
        </div>

        <div class="rv-match__head">
            <!-- ⚠️ AN ARCH WITH THEIR INITIAL, NOT AN AVATAR PLACEHOLDER.
                 A grey silhouette says "a photograph should be here". Nothing
                 should be here: the photographs are with the trusted contact,
                 and this is the doorway the product has used since the home
                 page. Drawn in CSS — no request, no layout shift. -->
            <span v-if="isOpen" class="rv-plate" aria-hidden="true">
                <span class="rv-plate__letter">{{ initial }}</span>
            </span>

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

        <!-- ⚠️ THE ABSENCE IS EXPLAINED, NOT LEFT TO BE NOTICED.
             A card with no picture and no comment on it reads as a card that
             failed to load one. Said plainly, it reads as the product working
             the way it promised. -->
        <p v-if="isOpen && match.photographsWithheld" class="rv-match__withheld">
            {{ $t('match.photos_with_contact') }}
        </p>

        <div class="rv-match__foot">
            <BaseButton v-if="isOpen" :href="`/matches/${match.id}`" variant="secondary">
                {{ $t('match.open') }}
            </BaseButton>
            <BaseButton v-else variant="primary" @click="$emit('unlock', match.id)">
                {{ $t('match.locked') }}
            </BaseButton>
        </div>
    </article>
</template>

<style scoped>

/* ── The plate ───────────────────────────────────────────────────────────
   An arch with their initial in it, where the photograph used to be.

   ⚠️ NOT A CIRCLE. Every product on earth puts an initial in a circle, and a
   circle with a letter in it reads as "avatar, image failed". The arch is the
   shape this product has used since the home page — a doorway you walk
   through to something serious — so the same letter in the same brass reads
   as deliberate rather than as a fallback. */
.rv-plate {
    flex: none;
    display: grid;
    place-items: center;
    width: 54px;
    height: 68px;
    /* Arch: square below, semicircle above. */
    border-radius: 27px 27px var(--r-sm, 8px) var(--r-sm, 8px);
    border: 1px solid color-mix(in oklab, var(--brass) 55%, transparent);
    /* Tone, never a shadow — the design system bans them, and depth here comes
       from the ground being a shade warmer than the card. */
    background: color-mix(in oklab, var(--brass) 9%, var(--card));
}

.rv-plate__letter {
    font-family: var(--display);
    font-size: 26px;
    line-height: 1;
    color: var(--brass-ink);
    /* Optical centring: the semicircular top makes a centred glyph read low. */
    transform: translateY(2px);
}

/* ── The explanation ─────────────────────────────────────────────────────
   Quiet, and above the actions rather than buried under them. It is a fact
   about how the product works, not a warning. */
.rv-match__withheld {
    font-size: 13px;
    line-height: 1.45;
    color: var(--ink-soft);
    padding-block-start: 2px;
    border-block-start: 1px solid var(--hairline);
    padding-top: 12px;
}

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
