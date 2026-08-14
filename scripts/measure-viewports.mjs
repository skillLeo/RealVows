/**
 * ⚠️ MEASURES LAYOUT AT THREE WIDTHS. DOES NOT JUDGE TASTE.
 *
 * R5 changed five surfaces a member walks through, and "I looked at it" is not
 * a measurement. This opens each one at 375, 834 and 1440 and reports the two
 * things that are facts rather than opinions:
 *
 *   · Does the page scroll sideways? A body wider than its viewport is a bug at
 *     every width and is invisible on a desktop browser that has room to spare.
 *   · Is anything the member has to tap smaller than 44px? That is the Apple
 *     HIG floor and the difference between a button and a coin toss.
 *
 * Like frame-benchmark.mjs this drives the Chrome already on the machine over
 * the DevTools protocol. Nothing added to package.json: a check that requires an
 * install is a check nobody runs twice.
 *
 *   1. Start the site:   php artisan serve --port=8123
 *   2. Start Chrome:     open -na "Google Chrome" --args --remote-debugging-port=9222
 *   3. node scripts/measure-viewports.mjs
 */

const ORIGIN = process.env.BENCH_ORIGIN ?? 'http://127.0.0.1:8123';

/**
 * ⚠️ 375 IS THE ONE THAT MATTERS AND IT IS NOT THE PHONE MOST PEOPLE HOLD.
 *
 * It is the narrowest width still in real use (iPhone SE, and every Android
 * that reports 360–375). A layout that survives 375 survives everything above
 * it; one tuned to 390 breaks for the people least likely to report it.
 */
const WIDTHS = [
  { width: 375, height: 812, mobile: true, label: '375 — smallest phone still in use' },
  { width: 834, height: 1112, mobile: true, label: '834 — tablet portrait' },
  { width: 1440, height: 900, mobile: false, label: '1440 — laptop' },
];

/**
 * ⚠️ ONLY THE SURFACES R5 CHANGED. A sweep of every route would take twenty
 * minutes and bury the five pages this release actually touched.
 *
 * `expect` is the guard: a string that must appear on the page. Without it a
 * redirect to the login screen measures perfectly and reports a clean layout
 * for a page nobody looked at — the exact way the frame benchmark first
 * measured a 404 with total confidence.
 */
const PAGES = [
  { name: 'register', path: '/register', as: null, expect: 'password' },
  { name: 'dashboard', path: '/dashboard', as: 'boy1', expect: 'introduction' },
  { name: 'profile builder', path: '/profile/values', as: 'boy1', expect: 'about you' },
  { name: 'trusted contact', path: '/trusted-contact', as: 'girl1', expect: 'trusted' },
  { name: 'privacy policy', path: '/privacy', as: null, expect: 'twelve months' },
];

let nextId = 1;
const pending = new Map();
let socket;

/*
 * ⚠️ EVERY CALL CARRIES A DEADLINE, BECAUSE A HUNG ONE LOOKS EXACTLY LIKE A
 * SLOW PAGE.
 *
 * Without this a protocol call that never comes back leaves the script sitting
 * silently forever, and the only information anybody gets is that it did not
 * finish. Naming the method that stalled turns twenty minutes of guessing into
 * one line.
 */
function send(method, params = {}, sessionId) {
  const id = nextId++;
  return new Promise((resolve, reject) => {
    const timer = setTimeout(() => {
      pending.delete(id);
      reject(new Error(`${method} did not respond within 30s.`));
    }, 30000);

    pending.set(id, {
      resolve: (v) => { clearTimeout(timer); resolve(v); },
      reject: (e) => { clearTimeout(timer); reject(e); },
    });

    socket.send(JSON.stringify({ id, method, params, sessionId }));
  });
}

async function connect() {
  const res = await fetch('http://127.0.0.1:9222/json/version');
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
 * ⚠️ RUN INSIDE THE PAGE. Overflow is a fact about the rendered document and
 * cannot be worked out from the outside.
 */
const PROBE = `
  (() => {
    const doc = document.documentElement;

    /*
     * ⚠️ THE WIDEST ELEMENT, NAMED. "The page overflows by 40px" sends somebody
     * hunting through a stylesheet. "<table class=packages> reaches 415px"
     * sends them to the line.
     */
    let widest = null;
    for (const el of document.body.querySelectorAll('*')) {
      const right = el.getBoundingClientRect().right;
      if (right > doc.clientWidth + 1 && (!widest || right > widest.right)) {
        widest = {
          right: Math.round(right),
          tag: el.tagName.toLowerCase(),
          cls: (el.className && typeof el.className === 'string')
            ? el.className.split(/\\s+/).slice(0, 3).join('.')
            : '',
        };
      }
    }

    /*
     * ⚠️ VISIBLE TARGETS ONLY. A collapsed mobile menu is full of buttons that
     * measure 0x0 because they are not on screen, and counting those reports a
     * dozen failures on a page with none.
     */
    /*
     * ⚠️ A LINK INSIDE A SENTENCE IS EXEMPT, AND THIS IS NOT A CONVENIENCE.
     *
     * WCAG 2.5.8 states the exception in as many words: a target is exempt when
     * it "is in a sentence or its size is otherwise constrained by the
     * line-height of non-target text". "Already have an account? Log in" is
     * that case exactly.
     *
     * ⚠️ THE ALTERNATIVE IS WORSE THAN THE FINDING. Padding a prose link to
     * 44px means its hit area covers the lines above and below it, so a tap
     * aimed at ordinary text opens a page. That is a real mis-tap introduced to
     * silence a reported one.
     *
     * ⚠️ AND A CHECK THAT REPORTS THINGS NOBODY SHOULD FIX GETS IGNORED, which
     * is how the finding that mattered — three 24px buttons that delete an
     * answer — ends up in the same list as three legal links and is skimmed
     * past with them.
     *
     * The test is narrow on purpose: an <a> whose parent holds other text. A
     * standalone link in its own container has no sentence around it and is
     * still reported.
     */
    const inSentence = (el) => {
      if (el.tagName !== 'A') return false;
      const parent = el.parentElement;
      if (!parent) return false;
      const around = (parent.innerText || '').replace(el.innerText || '', '').trim();
      return around.length > 0;
    };

    const small = [];
    const exempt = [];
    for (const el of document.querySelectorAll('a, button, input[type=submit], [role=button]')) {
      const r = el.getBoundingClientRect();
      if (r.width === 0 || r.height === 0) continue;
      if (getComputedStyle(el).visibility === 'hidden') continue;
      if (r.height >= 44) continue;

      const label = ((el.innerText || el.value || el.getAttribute('aria-label') || el.tagName).trim().slice(0, 28));

      if (inSentence(el)) {
        exempt.push(label);
      } else {
        small.push(label + ' → ' + Math.round(r.height) + 'px');
      }
    }

    return {
      scrollWidth: doc.scrollWidth,
      clientWidth: doc.clientWidth,
      height: document.body.scrollHeight,
      widest,
      small: [...new Set(small)].slice(0, 6),
      exempt: [...new Set(exempt)].length,
      text: document.body.innerText.toLowerCase().slice(0, 40000),
    };
  })()
`;

async function main() {
  await connect();

  const { targetInfos } = await send('Target.getTargets');
  const page = targetInfos.find((t) => t.type === 'page');
  const { sessionId } = await send('Target.attachToTarget', { targetId: page.targetId, flatten: true });

  await send('Page.enable', {}, sessionId);
  await send('Runtime.enable', {}, sessionId);
  await send('Network.enable', {}, sessionId);

  /*
   * ⚠️ THE READER IS PROVED BEFORE ITS FINDINGS ARE READ, AND THE EXEMPTION IS
   * THE REASON THIS IS NOT OPTIONAL.
   *
   * The probe now excuses links inside sentences. An exemption written slightly
   * too wide excuses everything, and a run that excuses everything prints the
   * same clean page as a site with no problems — the exact failure this project
   * has shipped ten times, arriving this time through the fix rather than the
   * bug.
   *
   * So: a standalone 20px button must be CAUGHT, and a 20px link inside a
   * sentence must be EXCUSED. If either comes back wrong, nothing below this
   * line means anything and the run stops.
   */
  await send('Page.navigate', { url: ORIGIN + '/privacy' }, sessionId);
  await new Promise((r) => setTimeout(r, 1500));

  const { result: control } = await send('Runtime.evaluate', {
    expression: `
      (() => {
        const box = document.createElement('div');
        box.innerHTML =
          '<button id="rv-ctl-a" style="height:20px;display:block">Alone</button>' +
          '<p id="rv-ctl-p">Some ordinary words and <a id="rv-ctl-b" href="#" style="line-height:20px">a link</a> inside them.</p>';
        document.body.appendChild(box);
        return true;
      })()
    `, returnByValue: true,
  }, sessionId);

  if (!control.value) throw new Error('Could not install the control elements.');

  const { result: check } = await send('Runtime.evaluate', {
    expression: PROBE, returnByValue: true,
  }, sessionId);

  const caughtAlone = check.value.small.some((s) => s.startsWith('Alone'));

  if (!caughtAlone) {
    throw new Error(
      'The probe did not catch a standalone 20px button. Every clean result it produces ' +
      'below would be a page it cannot see rather than a page with nothing wrong.'
    );
  }

  if (check.value.exempt < 1) {
    throw new Error(
      'The probe did not excuse a 20px link sitting inside a sentence, so the WCAG 2.5.8 ' +
      'exception is not being applied and the findings will be full of prose links.'
    );
  }

  console.log('\x1b[2mControl: standalone 20px button caught, in-sentence link excused.\x1b[0m');

  const problems = [];
  let measured = 0;

  for (const vp of WIDTHS) {
    console.log(`\n\x1b[1m${vp.label}\x1b[0m`);

    await send('Emulation.setDeviceMetricsOverride', {
      width: vp.width,
      height: vp.height,
      deviceScaleFactor: vp.mobile ? 2 : 1,
      mobile: vp.mobile,
    }, sessionId);

    let signedInAs;

    for (const { name, path, as, expect } of PAGES) {
      if (as !== signedInAs) {
        /*
         * ⚠️ THE GUEST PAGES NEED A REAL SIGN-OUT, NOT A DIFFERENT URL.
         *
         * /register redirects a signed-in visitor to their dashboard, so
         * measuring it while a session cookie is alive measures the dashboard
         * under the name "register". Clearing cookies through the protocol is
         * the one way to be certain, and it needs no logout route.
         */
        await send('Network.clearBrowserCookies', {}, sessionId);

        if (as !== null) {
          await send('Page.navigate', { url: `${ORIGIN}/demo-login/${as}` }, sessionId);
          await new Promise((r) => setTimeout(r, 2000));
        }

        signedInAs = as;
      }

      await send('Page.navigate', { url: ORIGIN + path }, sessionId);
      await new Promise((r) => setTimeout(r, 2200));

      const { result } = await send('Runtime.evaluate', {
        expression: PROBE, awaitPromise: false, returnByValue: true,
      }, sessionId);

      const m = result.value;

      /*
       * ⚠️ THE PAGE PROVES IT IS THE PAGE BEFORE ITS NUMBERS ARE BELIEVED. A
       * redirect to a login screen has a perfect layout and would be reported
       * as one, under the name of a page that was never opened.
       */
      if (expect && !m.text.includes(expect.toLowerCase())) {
        problems.push(`${vp.width}  ${name}: did not render — "${expect}" is absent. Not measured.`);
        console.log(`  \x1b[31m✗\x1b[0m ${name.padEnd(24)} not the page asked for`);
        continue;
      }

      measured++;

      const overflow = m.scrollWidth - m.clientWidth;
      const flags = [];

      if (overflow > 1) {
        const w = m.widest;
        flags.push(`scrolls sideways by ${overflow}px` + (w ? ` — widest is <${w.tag} class="${w.cls}"> reaching ${w.right}px` : ''));
      }

      if (m.small.length) {
        flags.push(`tap targets under 44px: ${m.small.join(', ')}`);
      }

      if (flags.length) {
        flags.forEach((f) => problems.push(`${vp.width}  ${name}: ${f}`));
        console.log(`  \x1b[31m✗\x1b[0m ${name.padEnd(24)} ${flags.join(' | ')}`);
      } else {
        const excused = m.exempt ? `  (${m.exempt} inline link${m.exempt > 1 ? 's' : ''} excused)` : '';
        console.log(`  \x1b[32m✓\x1b[0m ${name.padEnd(24)} ${m.clientWidth}px wide, ${m.height}px tall${excused}`);
      }
    }
  }

  /*
   * ⚠️ THE CONTROL. Every finding above is an absence, and a run where nothing
   * loaded reports a flawless site. If barely anything was measured, the clean
   * result means the harness was broken rather than the layout being right.
   */
  const expected = WIDTHS.length * PAGES.length;
  console.log(`\n${measured} of ${expected} page/width combinations measured.`);

  if (measured < expected * 0.6) {
    console.log('\n\x1b[31mToo few pages rendered for the result above to mean anything.\x1b[0m');
    process.exit(2);
  }

  if (problems.length) {
    console.log('\n\x1b[1mFindings\x1b[0m');
    problems.forEach((p) => console.log('  · ' + p));
    process.exit(1);
  }

  console.log('\n\x1b[32mNo sideways scroll and no undersized tap target at any width.\x1b[0m');
}

main().catch((e) => {
  console.error('\n' + e.message);
  console.error('\nChrome must be listening on 9222 and the site served at ' + ORIGIN + '.');
  process.exit(2);
});
