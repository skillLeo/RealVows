<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseCard from '@/Components/BaseCard.vue';
import CompatibilityRing from '@/Components/CompatibilityRing.vue';

/**
 * The full view of an introduction the member has paid for.
 *
 * A teaser never reaches this page — the controller answers 404 rather than
 * 403, so an unpaid member is not told there is something here worth paying
 * for.
 */
defineProps({ match: { type: Object, required: true } });
</script>

<template>
    <AppLayout :title="match.firstName">
        <div class="rv-shell rv-section--app rv-stack" style="gap: 40px">
            <header class="rv-match-head">
                <CompatibilityRing :score="match.score" />
                <div class="rv-stack" style="gap: 8px">
                    <span class="rv-label">Introduced on {{ match.unlockedAt }}</span>
                    <h1 class="rv-d1" style="view-transition-name: match-name">
                        {{ match.firstName }}<template v-if="match.lastName"> {{ match.lastName }}</template>
                    </h1>
                    <p class="rv-lead" style="margin: 0">
                        {{ match.age }}<template v-if="match.city">, {{ match.city }}</template>
                    </p>
                </div>
            </header>

            <span class="rv-underline" aria-hidden="true" />

            <div v-if="match.photos?.length" class="rv-gallery">
                <img
                    v-for="photo in match.photos"
                    :key="photo.slot"
                    :src="photo.url"
                    :alt="match.firstName"
                    class="rv-gallery__img"
                    loading="lazy"
                    decoding="async"
                />
            </div>

            <BaseCard v-if="match.rationale" accent>
                <div class="rv-stack" style="gap: 16px">
                    <span class="rv-label rv-label--brass">Why we chose them for you</span>
                    <p class="rv-body" style="margin: 0">{{ match.rationale }}</p>

                    <ul v-if="match.commonalities?.length" class="rv-common">
                        <li v-for="line in match.commonalities" :key="line">{{ line }}</li>
                    </ul>
                </div>
            </BaseCard>

            <BaseCard v-if="match.bio">
                <div class="rv-stack" style="gap: 12px">
                    <span class="rv-label">In their words</span>
                    <p class="rv-body" style="margin: 0; white-space: pre-line">{{ match.bio }}</p>
                </div>
            </BaseCard>

            <BaseCard>
                <dl class="rv-facts">
                    <template v-if="match.occupation">
                        <dt>Work</dt><dd>{{ match.occupation }}</dd>
                    </template>
                    <template v-if="match.education">
                        <dt>Education</dt><dd>{{ match.education }}</dd>
                    </template>
                    <template v-if="match.languages?.length">
                        <dt>Languages</dt><dd>{{ match.languages.join(', ') }}</dd>
                    </template>
                    <template v-if="match.interests?.length">
                        <dt>Interests</dt><dd>{{ match.interests.join(', ') }}</dd>
                    </template>
                </dl>
            </BaseCard>

            <!--
                Expressing interest, passing, and contact details on mutual
                interest are Phase 3. Deliberately not stubbed here: a button
                that does nothing is worse than a button that is not there.
            -->
        </div>
    </AppLayout>
</template>

<style scoped>
.rv-match-head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 32px;
}

.rv-gallery {
    display: grid;
    gap: 16px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

@media (min-width: 768px) {
    .rv-gallery { grid-template-columns: repeat(4, minmax(0, 1fr)); }
}

.rv-gallery__img {
    width: 100%;
    aspect-ratio: 3 / 4;
    object-fit: cover;
    border: 1px solid var(--hairline);
    border-radius: var(--r);
}

.rv-common {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin: 0;
    padding: 0;
    list-style: none;
    font-size: var(--fs-body-2);
    color: var(--ink);
}

.rv-common li { position: relative; padding-left: 20px; }

.rv-common li::before {
    content: '';
    position: absolute;
    left: 0;
    top: 12px;
    width: 10px;
    height: 1px;
    background: var(--brass);
}

.rv-facts {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 12px 24px;
    margin: 0;
}

.rv-facts dt {
    font-size: var(--fs-body-3);
    text-transform: uppercase;
    letter-spacing: .14em;
    color: var(--ink-soft);
}

.rv-facts dd { margin: 0; font-size: var(--fs-body-2); }
</style>
