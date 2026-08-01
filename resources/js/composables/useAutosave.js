import { onBeforeUnmount, ref, watch } from 'vue';

/**
 * Autosave for one step of the profile builder.
 *
 * A member is telling us their life story across seven steps. Losing any of
 * it to a closed tab or a dropped connection would be unforgivable, so every
 * change is persisted server-side 800ms after they stop typing — which also
 * means the draft survives a refresh, a browser close, and moving to another
 * device, because there is nothing held only in the browser.
 *
 * Deliberately not Inertia's form helper: a visit would re-render the page
 * under the member's cursor mid-sentence. This is a plain fetch that touches
 * nothing but the save indicator.
 *
 * @param {import('vue').Ref<object>} model  the step's reactive values
 * @param {string} url                       PUT target for this step
 * @param {(payload: object) => void} onSaved
 */
export function useAutosave(model, url, onSaved = () => {}) {
    const status = ref('idle'); // idle | saving | saved | error
    const errors = ref({});
    const lastSavedAt = ref(null);

    let timer = null;
    let inFlight = null;
    let dirtySinceRequest = false;

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    }

    async function flush() {
        if (timer) {
            clearTimeout(timer);
            timer = null;
        }

        // One request at a time. If the member kept typing while a save was
        // in flight, remember to run again rather than racing it.
        if (inFlight) {
            dirtySinceRequest = true;
            return inFlight;
        }

        status.value = 'saving';

        inFlight = fetch(url, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(model.value),
        })
            .then(async (response) => {
                if (response.status === 422) {
                    const body = await response.json();
                    errors.value = body.errors ?? {};
                    status.value = 'error';
                    return;
                }

                if (!response.ok) {
                    status.value = 'error';
                    return;
                }

                const body = await response.json();
                errors.value = {};
                lastSavedAt.value = body.saved_at;
                status.value = 'saved';
                onSaved(body);
            })
            .catch(() => {
                // Offline or the request was cancelled. The values stay in
                // the form, so the next keystroke tries again — nothing the
                // member typed is lost.
                status.value = 'error';
            })
            .finally(() => {
                inFlight = null;

                if (dirtySinceRequest) {
                    dirtySinceRequest = false;
                    schedule();
                }
            });

        return inFlight;
    }

    function schedule() {
        if (timer) clearTimeout(timer);
        timer = setTimeout(flush, 800);
    }

    watch(model, schedule, { deep: true });

    // A member who closes the tab mid-sentence still keeps that sentence.
    function onHidden() {
        if (document.visibilityState === 'hidden' && timer) flush();
    }

    document.addEventListener('visibilitychange', onHidden);

    onBeforeUnmount(() => {
        document.removeEventListener('visibilitychange', onHidden);
        if (timer) clearTimeout(timer);
    });

    return { status, errors, lastSavedAt, flush };
}
