{{--
    The compatibility mark, Blade edition.

    Same markup and the same `.rv-mark` classes as
    resources/js/Components/CompatibilityMark.vue — the styling lives once,
    in app.css, so the two renderers cannot drift apart. Only the count-up
    differs: the Vue component animates itself, while here public.js drives
    it from the data-count-to attribute, because the home page ships no Vue.

    Rebuilt from the approved foundation with the two sign lozenges and their
    caption removed. The score and the brass rule are what carried the mark
    visually, and they are what survive.

    @param int $score
--}}
<div class="rv-mark rv-mark--lg rv-mark--dark">
    <span class="rv-label rv-label--brass">Compatibility score</span>

    <span class="rv-mark__score" data-count-to="{{ $score }}">{{ $score }}</span>

    <span class="rv-mark__rule-row">
        <span class="rv-mark__rule"></span>
        <span class="rv-mark__rule-label">Compatibility</span>
        <span class="rv-mark__rule"></span>
    </span>

    <p class="rv-mark__caption">
        Every introduction is chosen for both people, never for one.
    </p>
</div>
