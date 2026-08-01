<script setup>
import { reactive, ref, watch } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseCard from '@/Components/BaseCard.vue';
import BaseField from '@/Components/BaseField.vue';
import SegmentedControl from '@/Components/SegmentedControl.vue';
import BaseCheckbox from '@/Components/BaseCheckbox.vue';

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

            <nav class="rv-settings__tabs" aria-label="Settings groups">
                <Link
                    v-for="group in groups"
                    :key="group.key"
                    :href="`/admin/settings/${group.key}`"
                    class="rv-settings__tab"
                    :class="{ 'is-active': group.key === activeGroup }"
                    :aria-current="group.key === activeGroup ? 'page' : undefined"
                >
                    {{ group.label }}
                </Link>
            </nav>

            <p
                v-if="page.props.flash?.success || page.props.flash?.error"
                class="rv-settings__flash"
                :class="{ 'is-error': !!page.props.flash?.error }"
                role="status"
            >
                {{ page.props.flash?.success || page.props.flash?.error }}
            </p>

            <form class="rv-stack" style="gap: 32px" @submit.prevent="submit">
                <BaseCard>
                    <div class="rv-stack" style="gap: 24px">
                        <h2 class="rv-d3">{{ activeLabel }}</h2>

                        <template v-for="field in fields" :key="field.key">
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
                    </div>
                </BaseCard>

                <div class="rv-settings__actions">
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
.rv-settings__tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding-bottom: 24px;
    border-bottom: 1px solid var(--hairline);
}

.rv-settings__tab {
    min-height: var(--tap-min);
    display: inline-flex;
    align-items: center;
    padding: 0 16px;
    border-radius: var(--r);
    border: 1px solid transparent;
    color: var(--ink-soft);
    font-size: var(--fs-body-2);
    font-weight: 500;
    text-decoration: none;
    transition:
        background var(--dur-tap) var(--ease),
        color var(--dur-tap) var(--ease),
        border-color var(--dur-tap) var(--ease);
}

.rv-settings__tab:hover {
    color: var(--oxblood);
    border-color: var(--hairline);
}

.rv-settings__tab.is-active {
    background: var(--brass);
    border-color: var(--brass);
    color: var(--oxblood-deep);
}

.rv-settings__flash {
    margin: 0;
    padding: 16px 20px;
    border: 1px solid var(--brass);
    border-radius: var(--r);
    background: color-mix(in oklab, var(--brass) 10%, var(--card));
    color: var(--oxblood);
    font-size: var(--fs-body-2);
}

.rv-settings__flash.is-error {
    border-color: var(--rose);
    background: color-mix(in oklab, var(--rose) 8%, var(--card));
}

.rv-settings__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
}
</style>
