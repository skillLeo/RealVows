<script setup>
/**
 * Inline SVG icon set.
 *
 * Deliberately not an icon font: the design's original `--glyph` face
 * (Noto Sans Symbols 2) existed only to draw symbols that are no longer
 * permitted anywhere a user can see — see app/Services/Sign/SignCalculator.php.
 * Inline SVG also removes a render-blocking font request on shared hosting.
 *
 * Every icon inherits `currentColor` so it reads from the design tokens.
 */
defineProps({
    name: { type: String, required: true },
    size: { type: [Number, String], default: 20 },
    strokeWidth: { type: [Number, String], default: 1.25 },
});
</script>

<template>
    <svg
        :width="size"
        :height="size"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        :stroke-width="strokeWidth"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
        focusable="false"
    >
        <!-- Free to join -->
        <path v-if="name === 'diamond'" d="M12 3 21 12l-9 9-9-9 9-9Z" />

        <!-- Verified / approved -->
        <path v-else-if="name === 'check'" d="m4.5 12.5 5 5 10-11" />

        <!-- Email and WhatsApp delivery -->
        <template v-else-if="name === 'envelope'">
            <rect x="2.5" y="5" width="19" height="14" rx="2" />
            <path d="m3 7 9 6 9-6" />
        </template>

        <!-- Locked teaser -->
        <template v-else-if="name === 'lock'">
            <rect x="4.5" y="10.5" width="15" height="10" rx="2" />
            <path d="M8 10.5V7.5a4 4 0 0 1 8 0v3" />
        </template>

        <!-- Password reveal -->
        <template v-else-if="name === 'eye'">
            <path d="M2 12s3.6-6.5 10-6.5S22 12 22 12s-3.6 6.5-10 6.5S2 12 2 12Z" />
            <circle cx="12" cy="12" r="2.75" />
        </template>
        <template v-else-if="name === 'eye-off'">
            <path d="M2 12s3.6-6.5 10-6.5c1.6 0 3 .4 4.2 1M22 12s-3.6 6.5-10 6.5c-1.6 0-3-.4-4.2-1" />
            <path d="m4 4 16 16" />
        </template>

        <!-- App tab bar -->
        <template v-else-if="name === 'matches'">
            <path d="M12 20.5S3.5 15.2 3.5 9.4A4.4 4.4 0 0 1 12 7.3a4.4 4.4 0 0 1 8.5 2.1c0 5.8-8.5 11.1-8.5 11.1Z" />
        </template>
        <template v-else-if="name === 'profile'">
            <circle cx="12" cy="8" r="3.75" />
            <path d="M4.5 20.5a7.5 7.5 0 0 1 15 0" />
        </template>
        <template v-else-if="name === 'packages'">
            <rect x="2.5" y="5.5" width="19" height="13" rx="2" />
            <path d="M2.5 10h19" />
        </template>
        <template v-else-if="name === 'account'">
            <circle cx="12" cy="12" r="3" />
            <path d="M12 2.5v2.2M12 19.3v2.2M21.5 12h-2.2M4.7 12H2.5M18.7 5.3l-1.6 1.6M6.9 17.1l-1.6 1.6M18.7 18.7l-1.6-1.6M6.9 6.9 5.3 5.3" />
        </template>

        <!-- Uploads -->
        <template v-else-if="name === 'upload'">
            <path d="M12 16.5V4.5m0 0L7.5 9M12 4.5 16.5 9" />
            <path d="M3.5 15v3.5a2 2 0 0 0 2 2h13a2 2 0 0 0 2-2V15" />
        </template>
        <template v-else-if="name === 'image'">
            <rect x="3" y="4.5" width="18" height="15" rx="2" />
            <circle cx="8.5" cy="10" r="1.5" />
            <path d="m4 17 5-4.5 4.5 4 3-2.5L20 18" />
        </template>

        <!-- Misc -->
        <path v-else-if="name === 'shield'" d="M12 2.8 20 6v6c0 4.4-3.3 7.9-8 9.2-4.7-1.3-8-4.8-8-9.2V6l8-3.2Z" />
        <path v-else-if="name === 'arrow-right'" d="M4.5 12h15m0 0-5.5-5.5M19.5 12 14 17.5" />
        <path v-else-if="name === 'arrow-left'" d="M19.5 12h-15m0 0L10 6.5M4.5 12 10 17.5" />
        <path v-else-if="name === 'four'" d="M14.5 19V5l-8 9.5h11" />
    </svg>
</template>
