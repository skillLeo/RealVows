<script setup>
import { reactive, ref, watch } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseCard from '@/Components/BaseCard.vue';
import BaseField from '@/Components/BaseField.vue';
import SegmentedControl from '@/Components/SegmentedControl.vue';
import BaseCheckbox from '@/Components/BaseCheckbox.vue';
import RvIcon from '@/Components/RvIcon.vue';

const props = defineProps({
    groups: { type: Array, required: true },
    activeGroup: { type: String, required: true },
    activeLabel: { type: String, required: true },
    fields: { type: Array, required: true },
    testable: { type: Boolean, default: false },
});

const page = usePage();

/**
 * Secrets arrive masked. Leaving the mask in the field and sending it back
 * untouched is safe — the server refuses to write a value that still looks
 * like a mask, so a credential the client did not edit survives the save.
 */
function seed() {
    const values = {};
    props.fields.forEach((field) => {
        values[field.key] = field.value ?? '';
    });
    return values;
}

const form = useForm({ group: props.activeGroup, values: seed() });
const testing = ref(false);

watch(
    () => props.activeGroup,
    (group) => {
        form.defaults({ group, values: seed() });
        form.reset();
        form.group = group;
        form.values = seed();
    }
);

function submit() {
    form.post('/admin/settings', { preserveScroll: true });
}

function runTest() {
    testing.value = true;
    router.post(
        '/admin/settings/test',
        { group: props.activeGroup },
        {
            preserveScroll: true,
            onFinish: () => (testing.value = false),
        }
    );
}

/**
 * ⚠️ HIDDEN, NOT DISABLED — AND ONLY WHERE A FIELD IS GENUINELY IRRELEVANT.
 *
 * Choosing Resend fills the host, port and username in at runtime, because
 * those three have the same answer for every Resend account that has ever
 * existed. Leaving them on screen, empty, invites somebody to fill them in
 * with something plausible and wrong, and the failure mode is silent: mail
 * simply stops.
 *
 * ⚠️ IT DEFAULTS TO SHOWING. Every branch that is not a deliberate hide
 * returns true, so a typo in a key name, an unknown group, or an undefined
 * driver leaves the field visible. The first version of this function was
 * never actually inserted into the file — the template called an undefined
 * name and EVERY field vanished from the live admin screen. Defaulting to
 * visible means the worst case of a future mistake is clutter rather than a
 * screen the client cannot use.
 */
const isRelevant = (field) => {
    if (props.activeGroup !== 'mail') {
        return true;
    }

    const driver = form.values.driver;

    if (field.key === 'resend_api_key') {
        return driver === 'resend';
    }

    if (['host', 'port', 'username', 'password', 'encryption'].includes(field.key)) {
        // Shown for custom SMTP, and shown when the driver is somehow unset —
        // an empty screen is worse than an irrelevant field.
        return driver !== 'resend' && driver !== 'log';
    }

    return true;
};

const asOptions = (options) =>
    Object.entries(options ?? {}).map(([value, label]) => ({ value, label }));
</script>

<template>
    <AppLayout title="Settings">
        <div class="rv-shell rv-section--app rv-stack" style="gap: 32px">
            <header class="rv-stack" style="gap: 12px">
                <span class="rv-label">Administration</span>
                <h1 class="rv-d2">Settings</h1>
                <p class="rv-lead" style="max-width: 56ch">
                    Everything RealVows connects to lives here. Change a value, save, and the next
                    email or upload uses it — nothing needs to be deployed.
                </p>
            </header>

            <!--
                While the one-click sign-in is open, this page is reachable by
                anyone who finds the URL. Storing a live credential here now
                would hand it to them — and "Test connection" would happily
                exercise it. The warning goes away with the review build.
            -->
            <p v-if="$page.props.isDemo" class="rv-flash rv-flash--error" role="alert">
                <strong>Review build — do not store real credentials yet.</strong>
                One-click sign-in is open on this URL, so anyone who finds it can reach this
                page. Add real mail, storage and gateway keys only after this goes live.
            </p>

            <nav class="rv-tabs" aria-label="Settings groups">
                <Link
                    v-for="group in groups"
                    :key="group.key"
                    :href="`/admin/settings/${group.key}`"
                    class="rv-tab"
                    :class="{ 'is-active': group.key === activeGroup }"
                    :aria-current="group.key === activeGroup ? 'page' : undefined"
                >
                    <span>{{ group.label }}</span>

                    <!-- Same badge as the verification queue. A number means
                         "this many still need a value"; the mark means the
                         group is fully set. -->
                    <span
                        v-if="group.outstanding > 0"
                        class="rv-tab__count"
                        :title="`${group.outstanding} still to fill in`"
                    >
                        {{ group.outstanding }}
                    </span>
                    <span v-else class="rv-tab__count rv-tab__count--done" title="All set">
                        <RvIcon name="check" :size="12" :stroke-width="2" />
                    </span>
                </Link>
            </nav>

            <p
                v-if="page.props.flash?.success || page.props.flash?.error"
                class="rv-flash"
                :class="{ 'rv-flash--error': !!page.props.flash?.error }"
                role="status"
            >
                {{ page.props.flash?.success || page.props.flash?.error }}
            </p>

            <form class="rv-stack" style="gap: 32px" @submit.prevent="submit">
                <BaseCard>
                    <div class="rv-stack" style="gap: 24px">
                        <h2 class="rv-d3">{{ activeLabel }}</h2>

                        <template v-for="field in fields" :key="field.key">
                            <template v-if="isRelevant(field)">
                            <!-- Toggles read as a single considered choice, not a checkbox grid -->
                            <div v-if="field.type === 'toggle'" class="rv-field">
                                <BaseCheckbox
                                    :model-value="form.values[field.key] === '1'"
                                    @update:model-value="form.values[field.key] = $event ? '1' : '0'"
                                >
                                    {{ field.label }}
                                </BaseCheckbox>
                                <span v-if="field.description" class="rv-help">{{ field.description }}</span>
                            </div>

                            <!-- Four or fewer options is always a segmented control -->
                            <div
                                v-else-if="field.type === 'select' && Object.keys(field.options ?? {}).length <= 4"
                                class="rv-field"
                            >
                                <SegmentedControl
                                    v-model="form.values[field.key]"
                                    :label="field.label"
                                    :options="asOptions(field.options)"
                                />
                                <span v-if="field.description" class="rv-help">{{ field.description }}</span>
                            </div>

                            <div v-else-if="field.type === 'select'" class="rv-field">
                                <label class="rv-label" :for="`set-${field.key}`">{{ field.label }}</label>
                                <select :id="`set-${field.key}`" v-model="form.values[field.key]" class="rv-select">
                                    <option
                                        v-for="option in asOptions(field.options)"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                                <span v-if="field.description" class="rv-help">{{ field.description }}</span>
                            </div>

                            <div v-else-if="field.type === 'textarea'" class="rv-field">
                                <label class="rv-label" :for="`set-${field.key}`">{{ field.label }}</label>
                                <textarea
                                    :id="`set-${field.key}`"
                                    v-model="form.values[field.key]"
                                    class="rv-input rv-textarea"
                                    rows="6"
                                />
                                <span v-if="field.description" class="rv-help">{{ field.description }}</span>
                            </div>

                            <BaseField
                                v-else
                                v-model="form.values[field.key]"
                                :label="field.label"
                                :type="field.type === 'password' ? 'text' : field.type"
                                :help="
                                    field.is_secret && field.has_value
                                        ? 'Stored. Leave as it is to keep it, or type a new value to replace it.'
                                        : field.description
                                "
                                :error="form.errors[`values.${field.key}`]"
                                autocomplete="off"
                            />
                            </template>
                        </template>
                    </div>
                </BaseCard>

                <div class="rv-actions">
                    <BaseButton type="submit" variant="primary" :disabled="form.processing">
                        {{ form.processing ? 'Saving' : 'Save changes' }}
                    </BaseButton>

                    <BaseButton
                        v-if="testable"
                        type="button"
                        variant="secondary"
                        :disabled="testing"
                        @click="runTest"
                    >
                        {{ testing ? 'Testing' : 'Test connection' }}
                    </BaseButton>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<style scoped>
.rv-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding-bottom: 24px;
    border-bottom: 1px solid var(--hairline);
}




.rv-flash {
    margin: 0;
    padding: 16px 20px;
    border: 1px solid var(--brass);
    border-radius: var(--r);
    background: color-mix(in oklab, var(--brass) 10%, var(--card));
    color: var(--oxblood);
    font-size: var(--fs-body-2);
}

.rv-flash.is-error {
    border-color: var(--rose);
    background: color-mix(in oklab, var(--rose) 8%, var(--card));
}

.rv-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
}
</style>
