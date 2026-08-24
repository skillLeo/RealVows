/**
 * ⚠️ MEASURES FRAMES. DOES NOT JUDGE THEM.
 *
 * The performance target for this product is 60fps at 4x CPU throttle, and it
 * has been carried as "not measured" for weeks — honestly, which is better than
 * a guess, but it is still an open question with a number-shaped answer.
 *
 * This drives the real Chrome already installed on the machine over the DevTools
 * protocol. No puppeteer, no playwright, nothing added to package.json: a
 * benchmark that requires a dependency install is a benchmark nobody runs twice.
 *
 * ── ⚠️ WHAT IT ACTUALLY COUNTS ────────────────────────────────────────────
 *
 * requestAnimationFrame callbacks during a scripted scroll, bucketed by the gap
 * between them. That is the honest measure of what a person feels: a frame that
 * arrives 17ms after the last one is smooth, one that arrives 60ms later is a
 * visible stutter no average will show you.
 *
 * So the output is the DISTRIBUTION, not a mean. A page that renders 58 perfect
 * frames and then stalls for 300ms averages out to something respectable and
 * feels broken, which is exactly the failure an average is worst at reporting.
 *
 * ⚠️ THE THROTTLE IS APPLIED BEFORE THE SCROLL AND VERIFIED AFTER. Emulation
 * silently does nothing if the target detaches, and an unthrottled run produces
 * beautiful numbers that mean nothing about the phone this is really for.
 */

const ORIGIN = process.env.BENCH_ORIGIN ?? 'http://127.0.0.1:8123';
const RATE = Number(process.env.BENCH_CPU ?? 4);

/**
 * ⚠️ EACH PAGE NAMES THE ACCOUNT THAT CAN ACTUALLY OPEN IT.
 *
 * The first run measured /admin while signed in as a member, so it timed a 404
 * page: zero scrolled, viewport height, and a worst frame that belonged to an
 * error screen rather than to the dashboard. A benchmark that silently measures
 * the wrong page reports a number with total confidence, which is worse than
 * reporting nothing.
 *
 * `minHeight` is the guard. A page shorter than this could not have been the
 * page asked for, and the run fails rather than publishing the figure.
 */
/*
 * ⚠️ THE PROFILE IS SPECIFIED NOW, WHICH IS WHY THIS COULD NOT BE RUN BEFORE.
 * 390 by 844, DPR 1, CPU throttled 4x. The two public pages carrying the most
 * motion: home runs the orchestrated arrival, how-it-works carries the rail.
 */
const PAGES = [
  { name: 'home', path: '/', as: null, minHeight: 2000 },
  { name: 'how it works', path: '/how-it-works', as: null, minHeight: 1200 },
];

let nextId = 1;
const pending = new Map();
let socket;

function send(method, params = {}, sessionId) {
  const id = nextId++;
  return new Promise((resolve, reject) => {
    pending.set(id, { resolve, reject });
    socket.send(JSON.stringify({ id, method, params, sessionId }));
  });
}

async function connect() {
  const res = await fetch(`http://127.0.0.1:9222/json/version`);
  const { webSocketDebuggerUrl } = await res.json();

  socket = new WebSocket(webSocketDebuggerUrl);

  await new Promise((resolve, reject) => {
    socket.addEventListener('open', resolve, { once: true });
    socket.addEventListener('error', reject, { once: true });
  });

  socket.addEventListener('message', (event) => {
    const message = JSON.parse(event.data);
    if (message.id && pending.has(message.id)) {
      const { resolve, reject } = pending.get(message.id);
      pending.delete(message.id);
      message.error ? reject(new Error(JSON.stringify(message.error))) : resolve(message.result);
    }
  });
}

/**
 * ⚠️ RUN INSIDE THE PAGE, because only the page can see its own frames. A
 * measurement taken from outside would be timing the protocol round trip.
 */
const PROBE = `
  new Promise((resolve) => {
    const gaps = [];
    let last = performance.now();
    let frames = 0;
    const started = last;

    // Scroll steadily rather than jumping. A single jump measures one layout;
    // a steady scroll measures the thing a person actually does.
    const step = () => {
      const now = performance.now();
      gaps.push(now - last);
      last = now;
      frames++;

      /*
       * ⚠️ SCROLLS THE WINDOW, AND THE ADMIN SHELL DOES NOT SCROLL THE WINDOW.
       *
       * AdminLayout scrolls an inner container beside a fixed sidebar, so
       * window.scrollBy moves nothing there and 'scrolled' comes back 0. The
       * frame timings on those pages are therefore a STATIC page under load,
       * not a scroll. That is still a real measurement and it is a weaker one,
       * and the reported 'scrolled' figure is what lets a reader tell which
       * they are looking at rather than being told a scroll was measured.
       */
      window.scrollBy(0, 12);
      document.querySelector('[data-scroll-region]')?.scrollBy(0, 12);

      if (now - started < 3000) {
        requestAnimationFrame(step);
      } else {
        // The first gap is the time from probe start to first frame, which is
        // not a frame interval and would skew the worst-case.
        gaps.shift();
        resolve({
          frames,
          gaps,
          scrolled: window.scrollY,
          height: document.body.scrollHeight,
        });
      }
    };

    requestAnimationFrame(step);
  })
`;

function summarise(gaps) {
  const sorted = [...gaps].sort((a, b) => a - b);
  const at = (p) => sorted[Math.min(sorted.length - 1, Math.floor(sorted.length * p))] ?? 0;

  return {
    frames: gaps.length,
    median: at(0.5),
    p95: at(0.95),
    worst: sorted.at(-1) ?? 0,
    // The number that matters: how many frames took longer than one 60fps
    // interval, and how many were bad enough to see.
    over16: gaps.filter((g) => g > 16.7).length,
    over32: gaps.filter((g) => g > 32).length,
    over100: gaps.filter((g) => g > 100).length,
  };
}

async function main() {
  await connect();

  const { targetInfos } = await send('Target.getTargets');
  const page = targetInfos.find((t) => t.type === 'page');
  const { sessionId } = await send('Target.attachToTarget', { targetId: page.targetId, flatten: true });

  await send('Page.enable', {}, sessionId);
  await send('Runtime.enable', {}, sessionId);
  await send('Emulation.setDeviceMetricsOverride', {
    width: 390, height: 844, deviceScaleFactor: 1, mobile: true,
  }, sessionId);

  /*
   * ⚠️ THE CONTROL FOR THE MEASUREMENT, NOT FOR THE THROTTLE.
   *
   * throttleControl below proves the CPU is genuinely slowed. It does not
   * prove this harness can SEE a slow page. A probe that had stopped
   * collecting gaps would report a flawless median forever, and a flawless
   * median is exactly what a well-built page also reports.
   *
   * So a deliberately expensive element is injected and the frames must get
   * measurably worse. If they do not, every figure below describes the
   * harness rather than the page, and the run stops.
   */
  await send('Emulation.setCPUThrottlingRate', { rate: 1 }, sessionId);
  await send('Page.navigate', { url: ORIGIN + '/' }, sessionId);
  await new Promise((r) => setTimeout(r, 2500));
  await send('Emulation.setCPUThrottlingRate', { rate: RATE }, sessionId);

  const cleanRun = await send('Runtime.evaluate', {
    expression: PROBE, awaitPromise: true, returnByValue: true,
  }, sessionId);
  const clean = summarise(cleanRun.result.value.gaps);

  /*
   * ⚠️ MAIN-THREAD WORK, NOT A BLURRY DIV.
   *
   * The first attempt injected twelve hundred blurred gradients and the
   * control refused the run, which was the control being right: a filter is
   * composited on the GPU and never touches the thread requestAnimationFrame
   * is timed on. The probe was correct to see no change.
   *
   * A forced synchronous reflow on every scroll is jank the main thread
   * genuinely feels: read a geometry property, write a style, repeat.
   */
  const JANK = '(() => {'
    + 'window.__rvJank = () => {'
    + '  const nodes = document.querySelectorAll("p, div, span, h1, h2, h3");'
    + '  let sink = 0;'
    + '  for (let pass = 0; pass < 6; pass++) {'
    + '    for (let i = 0; i < nodes.length; i++) {'
    + '      sink += nodes[i].getBoundingClientRect().top;'
    + '      nodes[i].style.outlineOffset = (sink % 2) + "px";'
    + '    }'
    + '  }'
    + '  return sink;'
    + '};'
    + 'window.addEventListener("scroll", window.__rvJank, { passive: true });'
    + 'return true; })()';

  await send('Runtime.evaluate', { expression: JANK, returnByValue: true }, sessionId);

  const jankyRun = await send('Runtime.evaluate', {
    expression: PROBE, awaitPromise: true, returnByValue: true,
  }, sessionId);
  const janky = summarise(jankyRun.result.value.gaps);

  await send('Runtime.evaluate', {
    expression: 'window.removeEventListener("scroll", window.__rvJank)',
  }, sessionId);
  await send('Emulation.setCPUThrottlingRate', { rate: 1 }, sessionId);

  const sawIt = janky.worst > clean.worst * 1.25 || janky.over16 > clean.over16;

  const jankControl = {
    cleanWorst: clean.worst,
    jankyWorst: janky.worst,
    cleanOver16: clean.over16,
    jankyOver16: janky.over16,
    degradationDetected: sawIt,
  };

  if (!sawIt) {
    throw new Error('The harness could not see deliberately injected jank. Every number it '
      + 'reports describes the harness rather than the page.');
  }

  const results = [];
  let signedInAs = null;

  for (const { name, path, as, minHeight } of PAGES) {
    if (as !== signedInAs) {
      await send('Emulation.setCPUThrottlingRate', { rate: 1 }, sessionId);
      await send('Page.navigate', { url: `${ORIGIN}/demo-login/${as ?? 'boy1'}` }, sessionId);
      await new Promise((r) => setTimeout(r, 2500));
      signedInAs = as;
    }

    // ⚠️ THROTTLE OFF while loading, ON for the measurement. Otherwise this
    // reports how slowly a throttled machine parses a bundle, which is a real
    // number about a different question.
    await send('Emulation.setCPUThrottlingRate', { rate: 1 }, sessionId);
    await send('Page.navigate', { url: ORIGIN + path }, sessionId);
    await new Promise((r) => setTimeout(r, 2500));

    await send('Emulation.setCPUThrottlingRate', { rate: RATE }, sessionId);

    const { result } = await send('Runtime.evaluate', {
      expression: PROBE,
      awaitPromise: true,
      returnByValue: true,
    }, sessionId);

    const { frames, gaps, scrolled, height } = result.value;

    if (height < minHeight) {
      throw new Error(
        `${path} rendered only ${height}px tall as ${as ?? 'a guest'}. That is not the page ` +
        `being asked for, and measuring it would publish a figure about an error screen.`
      );
    }

    results.push({ name, path, as, scrolled, height, ...summarise(gaps), rawFrames: frames });
  }

  /*
   * ⚠️ THE CONTROL, AND WITHOUT IT THIS WHOLE FILE IS DECORATION.
   *
   * Emulation.setCPUThrottlingRate fails quietly. If the session detaches, if
   * the page navigates at the wrong moment, if the parameter is misspelled, it
   * returns success and does nothing — and an UNTHROTTLED run produces the
   * best numbers this benchmark can print. A clean result and a broken
   * benchmark look identical, which is the exact failure shape this project has
   * shipped ten times.
   *
   * So the same busy loop is timed at 1x and at RATE. If the throttled one is
   * not meaningfully slower, the throttle did nothing and every figure above
   * describes an unthrottled laptop rather than the phone this is for.
   */
  /*
   * ⚠️ A FIXED AMOUNT OF WORK, TIMED. NOT A LOOP THAT EXITS ON THE CLOCK.
   *
   * The first version of this control spun `while (performance.now() - t < 200)`
   * and reported a ratio of 1.01, concluding the throttle was dead. The throttle
   * was fine. A loop that stops when the wall clock says 200ms takes 200ms at
   * every throttle rate by construction — it simply completes fewer iterations.
   * The control was measuring its own exit condition.
   *
   * A control that cannot fail correctly is worse than no control: it had me
   * one step from reporting that CPU throttling does not work in this Chrome.
   */
  const spin = `(() => { let n = 0; for (let i = 0; i < 4000000; i++) { n += Math.sqrt(i); } return n; })()`;

  const timeSpin = async (rate) => {
    await send('Emulation.setCPUThrottlingRate', { rate }, sessionId);
    const started = Date.now();
    await send('Runtime.evaluate', { expression: spin, returnByValue: true }, sessionId);
    return Date.now() - started;
  };

  const atOne = await timeSpin(1);
  const atRate = await timeSpin(RATE);
  const ratio = atRate / atOne;

  await send('Emulation.setCPUThrottlingRate', { rate: 1 }, sessionId);

  const throttleWorked = ratio > 1.5;

  console.log(JSON.stringify({
    cpuThrottle: RATE,
    jankControl,
    throttleControl: { msAt1x: atOne, [`msAt${RATE}x`]: atRate, ratio: Number(ratio.toFixed(2)), throttleWorked },
    results,
  }, null, 2));

  socket.close();

  if (!throttleWorked) {
    console.error(
      `\nCPU throttling did not take effect (ratio ${ratio.toFixed(2)}). Every figure above ` +
      `describes an unthrottled machine and answers a different question.`
    );
    process.exit(2);
  }
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
