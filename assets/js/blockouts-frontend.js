
(function () {
    var CFG = window.WSB_BLOCKOUTS || {};
    var DAYS = CFG.days || {};
    var SEL = CFG.selectors || {};
    var DEBUG = !!CFG.debug;

    var CLOCK = (CFG.clock || {});

    // bookings site settings
    var TUNE = {
        outerInnerRadius: Number(CLOCK.outerInnerRadius || 0.66),
        outerOuterRadius: Number(CLOCK.outerOuterRadius || 1.08),
        innerInnerRadius: Number(CLOCK.innerInnerRadius || 0.36),
        innerOuterRadius: Number(CLOCK.innerOuterRadius || (matchMedia('(pointer: coarse)').matches ? 0.75 : 0.66)),
        outerThickness: Number(CLOCK.outerThickness || 0.8), // 0..1 of ring thickness
        innerThickness: Number(CLOCK.innerThickness || (matchMedia('(pointer: coarse)').matches ? 2.3 : 1.7)),
        hitPaddingPx: Number(CLOCK.hitPaddingPx || 0.5),
        overlayZ: String((CLOCK.overlayZ != null ? CLOCK.overlayZ : 999)),
        maskFudgePx: Number((CLOCK && CLOCK.maskFudgePx) || (matchMedia('(pointer: coarse)').matches ? 1.0 : 0.6)),
        wedgeAlpha: Number((CLOCK && CLOCK.wedgeAlpha) ||
            (matchMedia('(pointer: coarse)').matches ? 0 : 0)), // mobile a touch darker
        wedgeColor: (CLOCK && CLOCK.wedgeColor) || '#000',
    };

    // Simple responsive thickness
    (function () {
        var coarse = window.matchMedia && matchMedia('(pointer: coarse)').matches;

        // If PHP provides device-specific values, use them; else fall back to simple defaults
        var otFine = CLOCK && CLOCK.outerThicknessFine;
        var otCoarse = CLOCK && CLOCK.outerThicknessCoarse;
        var itFine = CLOCK && CLOCK.innerThicknessFine;
        var itCoarse = CLOCK && CLOCK.innerThicknessCoarse;

        TUNE.outerThickness = Number(
            (coarse ? otCoarse : otFine) ??
            CLOCK?.outerThickness ??
            (coarse ? 0.8 : 1.8)           // your requested defaults
        );

        TUNE.innerThickness = Number(
            (coarse ? itCoarse : itFine) ??
            CLOCK?.innerThickness ??
            (coarse ? 1.2 : 1.6)           // tweak if needed
        );
    })();



    function dbg() { if (!DEBUG) return; console.log.apply(console, arguments); }

    // live tuning 
    window.WSB_CLOCK_TUNE = function (overrides) {
        Object.assign(TUNE, overrides || {});
        console.log('[WSB] Tuned:', TUNE);
        // repaint any visible clocks using the last-known blocked hours we stored
        document.querySelectorAll('.clock-timepicker-popup').forEach(function (p) {
            if (p.style.display === 'none') return;
            var hourCanvas = p.querySelector('.clock-timepicker-hour-canvas');
            if (!hourCanvas) return;
            var wrap = hourCanvas.parentElement && hourCanvas.parentElement.parentElement;
            var set = {};
            try { set = JSON.parse((wrap && wrap.dataset.wsbBlockedHours) || '{}'); } catch (e) { }
            drawBlockedHourOverlay(hourCanvas, set);
        });
    };

    function log() { if (DEBUG && window.console) console.log.apply(console, ['[WSB]'].concat([].slice.call(arguments))); }

    // Is at least one range the entire day?
    function isFullDayRanges(rangesStrPairs) {
        // rangesStrPairs = [ ["HH:MM","HH:MM"], ... ]
        if (!rangesStrPairs || !rangesStrPairs.length) return false;
        for (var i = 0; i < rangesStrPairs.length; i++) {
            var s = timeToMinutes(rangesStrPairs[i][0]);
            var e = timeToMinutes(rangesStrPairs[i][1]);
            if (s === 0 && (e >= 1439 && e <= 1440)) return true; // 23:59 or 24:00
        }
        return false;
    }


    // ---- Date parsing ----
    function toISO(d) {
        if (!d) return '';
        d = String(d).trim();
        if (/^\d{4}-\d{2}-\d{2}$/.test(d)) return d;
        var m = d.match(/^(\d{2})\/(\d{2})\/(\d{2,4})$/);
        if (m) {
            var dd = m[1], mm = m[2], yy = m[3]; if (yy.length === 2) yy = '20' + yy;
            return yy + '-' + mm.padStart(2, '0') + '-' + dd.padStart(2, '0');
        }
        return '';
    }

    function getDialMetrics(hourCanvas) {
        var r = hourCanvas.getBoundingClientRect();
        var cx = r.left + r.width / 2, cy = r.top + r.height / 2;
        var R = Math.min(r.width, r.height) / 2;
        return {
            cx: cx, cy: cy,
            innerR: R * (TUNE.innerInnerRadius),
            outerR: R * (TUNE.outerOuterRadius)
        };
    }

    function isPointerInHourRing(hourCanvas, e) {
        if (!hourCanvas) return false;
        var t = (e.changedTouches && e.changedTouches[0]) || e;
        var m = getDialMetrics(hourCanvas);
        var dx = t.clientX - m.cx, dy = t.clientY - m.cy;
        var d = Math.hypot(dx, dy);
        return d >= m.innerR && d <= m.outerR;
    }


    // ---- Time parsing (13:00, 1:00 pm, 1pm, 0600) → minutes ----
    function timeToMinutes(s) {
        if (!s) return NaN;
        s = String(s).trim().toLowerCase();
        // 0600 or 6:00 or 6 or 6pm
        var m = s.match(/^(\d{1,2})(?::?(\d{2}))?\s*(am|pm)?$/i);
        if (!m) return NaN;
        var h = parseInt(m[1], 10), min = parseInt(m[2] || '0', 10), ap = (m[3] || '').toLowerCase();
        if (ap === 'pm' && h < 12) h += 12;
        if (ap === 'am' && h === 12) h = 0;
        return h * 60 + min;
    }

    function rangesFor(iso) {
        return (DAYS && DAYS[iso]) ? DAYS[iso].map(function (pair) {
            return [timeToMinutes(pair[0]), timeToMinutes(pair[1])];
        }) : [];
    }
    function isBlocked(mins, ranges) {
        if (!isFinite(mins)) return false;
        for (var i = 0; i < ranges.length; i++) {
            if (mins >= ranges[i][0] && mins < ranges[i][1]) return true;
        }
        return false;
    }
    function mkHint(el, text) {
        var id = (el.id || 'wsb_time') + '_hint';
        var hint = document.getElementById(id);
        if (!hint) {
            hint = document.createElement('div');
            hint.id = id; hint.className = 'wsb-time-hint';
            hint.style.fontSize = '12px'; hint.style.marginTop = '4px'; hint.style.opacity = '0.5';
            el.insertAdjacentElement('afterend', hint);
        }
        hint.textContent = text || '';
    }

    // --- Pairing: date input name 'X_date' → time name 'X_time'
    function pairedTimeElements(dateInput) {
        var list = [];
        var name = dateInput && dateInput.getAttribute('name') || '';
        var base = name.replace(/_date$/, '');
        if (!base || base === name) {
            // Fallback to global selectors
            pushAll(list, document.querySelectorAll(SEL.timeSelect || ''));
            pushAll(list, document.querySelectorAll(SEL.timeInput || ''));
            pushAll(list, document.querySelectorAll(SEL.timeText || ''));
            return list;
        }
        var tname = base + '_time';
        pushAll(list, document.querySelectorAll('select[name="' + tname + '"]'));
        pushAll(list, document.querySelectorAll('input[type="time"][name="' + tname + '"]'));
        pushAll(list, document.querySelectorAll('input[type="text"][name="' + tname + '"]'));
        return list;
    }
    function pushAll(dst, nodeList) { [].forEach.call(nodeList, function (n) { dst.push(n); }); }

    // --- Text timepicker popup decorating (generic .clock-timepicker)
    function decorateTextTimepicker(el, ranges, iso) {
        // Find wrapper and popup
        var wrap = el.closest && el.closest('.clock-timepicker');
        if (!wrap) return;
        var popup = wrap.querySelector('.clock-timepicker-popup');
        if (!popup) return;

        // Find clickable options (very generic)
        var items = popup.querySelectorAll('[data-time], button, a, li, div');
        [].forEach.call(items, function (node) {
            var t = node.getAttribute && node.getAttribute('data-time');
            t = t || (node.textContent || '').trim();
            var mins = timeToMinutes(t);
            var blocked = isBlocked(mins, ranges);
            if (blocked) {
                node.setAttribute('aria-disabled', 'true');
                node.style.pointerEvents = 'none';
                node.style.opacity = '0.35';
                node.title = 'Unavailable on ' + iso;
                node.classList.add('wsb-tp-disabled');
            } else {
                node.removeAttribute('aria-disabled');
                node.style.pointerEvents = '';
                node.style.opacity = '';
                node.title = '';
                node.classList.remove('wsb-tp-disabled');
            }
        });
    }

    // Walk up to find the text of the clicked time option.
    function extractTimeString(node) {
        if (!node) return '';
        // Common patterns: data-time attr, button/a text, li/div text
        if (node.getAttribute && node.getAttribute('data-time')) {
            return (node.getAttribute('data-time') || '').trim();
        }
        // prefer leaf text
        var t = (node.textContent || '').trim();
        if (t) return t;
        // climb a little if the click lands on inner spans
        var p = node.parentElement;
        for (var i = 0; i < 3 && p; i++, p = p.parentElement) {
            if (p.getAttribute && p.getAttribute('data-time')) return (p.getAttribute('data-time') || '').trim();
            t = (p.textContent || '').trim();
            if (t) return t;
        }
        return '';
    }

    // Attach one-time popup guards for a .clock-timepicker wrapper
    function bindTextPopupGuards(input, ranges, iso) {
        var wrap = input.closest && input.closest('.clock-timepicker');
        if (!wrap) return;
        var popup = wrap.querySelector('.clock-timepicker-popup');
        if (!popup) return;

        // Unbind previous guards (if any) so we don't keep stale ranges
        if (popup._wsbTextGuard) {
            popup.removeEventListener('pointerdown', popup._wsbTextGuard, true);
            popup.removeEventListener('mousedown', popup._wsbTextGuard, true);
            popup.removeEventListener('click', popup._wsbTextGuard, true);
            popup.removeEventListener('touchstart', popup._wsbTextGuard, true);
        }

        if (popup._wsbTextPostClick) {
            popup.removeEventListener('click', popup._wsbTextPostClick, true);
        }

        // Fresh handlers for the CURRENT date
        var captureHandler = function (ev) {
            var tStr = extractTimeString(ev.target);
            var mins = timeToMinutes(tStr);
            if (isBlocked(mins, ranges)) {
                ev.stopImmediatePropagation();
                ev.stopPropagation();
                ev.preventDefault();
                popup.style.animation = 'wsb-deny 120ms';
                setTimeout(function () { popup.style.animation = ''; }, 140);
            }
        };

        popup._wsbTextGuard = captureHandler;

        popup.addEventListener('pointerdown', captureHandler, true);
        popup.addEventListener('mousedown', captureHandler, true);
        popup.addEventListener('click', captureHandler, true);
        popup.addEventListener('touchstart', captureHandler, true); // capture=true

        // Post-click sanity for CURRENT date
        var postClick = function () {
            setTimeout(function () {
                var mins = timeToMinutes(input.value);
                if (isBlocked(mins, ranges)) {
                    input.value = '';
                    try { input.setCustomValidity('That time is unavailable.'); } catch (e) { }
                } else {
                    try { input.setCustomValidity(''); } catch (e) { }
                }
            }, 0);
        };

        popup._wsbTextPostClick = postClick;
        popup.addEventListener('click', postClick, true);
    }


    // Make a set {0..23} of blocked whole hours for given ranges [start,end) in minutes.
    function blockedHoursFromRanges(ranges) {
        var set = {};
        for (var h = 0; h < 24; h++) {
            var segStart = h * 60, segEnd = (h + 1) * 60;
            for (var i = 0; i < ranges.length; i++) {
                var rs = ranges[i][0], re = ranges[i][1];
                var overlaps = Math.max(segStart, rs) < Math.min(segEnd, re);
                if (overlaps) { set[h] = 1; break; }
            }
        }
        return set; // lookup: if (set[5]) hour 5 is blocked
    }

    // Convert pointer position → hour 0–23 for your clock:
    // - Outer ring: 1..12 (12 at top)
    // - Inner ring: 00 at top, then 13..23 clockwise
    function hourFromEvent(canvas, evt) {
        var r = canvas.getBoundingClientRect();
        var cx = r.left + r.width / 2, cy = r.top + r.height / 2;
        var cxp = (evt.clientX || (evt.touches && evt.touches[0].clientX) || 0) - cx;
        var cyp = (evt.clientY || (evt.touches && evt.touches[0].clientY) || 0) - cy;

        // angle 0 at 12 o'clock; increase clockwise
        var angle = Math.atan2(cyp, cxp);                // 0 at +X (east), CCW+
        var deg = (angle * 180 / Math.PI + 90);          // rotate so 0° at 12
        deg = (deg < 0 ? deg + 360 : deg) % 360;         // 0..360

        var sector = Math.round(deg / 30) % 12;          // 0..11 sectors (0 = 12 o'clock)
        var dist = Math.hypot(cxp, cyp);

        // Heuristic radii—tuned for your dial
        var innerR = Math.min(r.width, r.height) * 0.33; // inner ring radius
        var outerR = Math.min(r.width, r.height) * 0.52; // outer ring radius
        var useOuter = dist > (innerR + outerR) / 2;     // closer to outer ring?

        // Map to 24h
        // outer: 12,1..11  ; inner: 00,13..23
        if (useOuter) {
            return (sector === 0) ? 12 : sector;           // 12 at sector 0
        } else {
            return (sector === 0) ? 0 : (sector + 12);     // 00 at sector 0; 1→13, …, 11→23
        }
    }

    // Paint translucent wedges over blocked hours (visual cue)
    function drawBlockedHourOverlay(hourCanvas, blockedSet) {
        if (!hourCanvas) return;
        var wrap = hourCanvas.parentElement;
        if (!wrap) return;

        // Ensure wrap is a positioning context
        var cs = window.getComputedStyle(wrap);
        if (cs && cs.position === 'static') wrap.style.position = 'relative';

        var overlay = wrap.querySelector('canvas#wsb-hour-overlay');

        var hasAny = Object.keys(blockedSet || {}).length > 0;
        if (!hasAny) { if (overlay) overlay.style.display = 'none'; return; }

        if (!overlay) {
            overlay = document.createElement('canvas');
            overlay.id = 'wsb-hour-overlay';
            overlay.style.position = 'absolute';
            overlay.style.top = '0'; overlay.style.left = '0';
            overlay.style.pointerEvents = 'none';
            wrap.appendChild(overlay);
        } else {
            wrap.appendChild(overlay); // bring to front
        }
        overlay.style.zIndex = String(TUNE.overlayZ);
        overlay.style.display = 'block';

       // Match CSS size, then draw at device pixel resolution
        var dpr = Math.max(1, window.devicePixelRatio || 1);
        var cssW = hourCanvas.clientWidth  || hourCanvas.width;
        var cssH = hourCanvas.clientHeight || hourCanvas.height;

        overlay.style.width  = cssW + 'px';
        overlay.style.height = cssH + 'px';
        overlay.width  = Math.round(cssW * dpr);
        overlay.height = Math.round(cssH * dpr);

        var ctx = overlay.getContext('2d');
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);   // draw in CSS pixels

        var w = cssW, h = cssH, min = Math.min(w, h);
        ctx.clearRect(0, 0, w, h);

        var cx = w / 2, cy = h / 2;

        // Use **radius** as base scale
        var R = min / 2;
        var OUTER_INNER = R * TUNE.outerInnerRadius;
        var OUTER_OUTER = R * TUNE.outerOuterRadius;
        var INNER_INNER = R * TUNE.innerInnerRadius;
        var INNER_OUTER = R * TUNE.innerOuterRadius;


        ctx.fillStyle = 'rgba(0,0,0,0.18)';

        for (var hr = 0; hr < 24; hr++) {
            if (!blockedSet[hr]) continue;

            // OUTER ring for 1..12, INNER for 0 and 13..23
            var ringOuter = (hr >= 1 && hr <= 12);
            var sector = (hr === 0 ? 0 : (hr <= 12 ? hr : hr - 12)); // 0..11
            var startDeg = (sector * 30 - 15) * Math.PI / 180;
            var endDeg = (sector * 30 + 15) * Math.PI / 180;

            // Compute wedge thickness from tunables
            var R2, R1, ringThickness;
            if (ringOuter) {
                ringThickness = OUTER_OUTER - OUTER_INNER;
                R2 = OUTER_OUTER;
                R1 = R2 - ringThickness * Math.max(0, Math.min(1, TUNE.outerThickness));
            } else {
                ringThickness = INNER_OUTER - INNER_INNER;
                R2 = INNER_OUTER;
                R1 = R2 - ringThickness * Math.max(0, Math.min(1, TUNE.innerThickness));
            }

            // Draw donut wedge (rotate -90° so 12 is at top)
            ctx.beginPath();
            ctx.arc(cx, cy, R2, startDeg - Math.PI / 2, endDeg - Math.PI / 2);
            ctx.arc(cx, cy, R1, endDeg - Math.PI / 2, startDeg - Math.PI / 2, true);
            ctx.closePath();
            ctx.fill();
        }

        // --- Hard mask to keep paint strictly inside both rings (fixes mobile bleed) ---
        (function applyRingMask() {
            var w = overlay.clientWidth || overlay.width;
            var h = overlay.clientHeight || overlay.height;

            // We draw in CSS px after setTransform(dpr,...), so this is a CSS px fudge
            var AA = Number(TUNE.maskFudgePx || 1);

            var ctx = overlay.getContext('2d');
            ctx.save();
            ctx.globalCompositeOperation = 'source-over';
            ctx.globalAlpha = 1; // make sure nothing leaked from earlier ops
            ctx.fillStyle = `rgba(0,0,0,${TUNE.wedgeAlpha})`; // or use TUNE.wedgeColor

            ctx.beginPath();

            // OUTER ring: [OUTER_INNER .. OUTER_OUTER]
            ctx.arc(cx, cy, OUTER_OUTER - AA, 0, Math.PI * 2, false);
            ctx.arc(cx, cy, OUTER_INNER + AA, 0, Math.PI * 2, true);   // reverse to cut hole

            // Start a second sub-path so even-odd produces a *union* of two donuts
            ctx.moveTo(cx + (INNER_OUTER - AA), cy);
            // INNER ring: [INNER_INNER .. INNER_OUTER]
            ctx.arc(cx, cy, INNER_OUTER - AA, 0, Math.PI * 2, false);
            ctx.arc(cx, cy, INNER_INNER + AA, 0, Math.PI * 2, true);

            // Use even-odd rule to form two separate annuli
            if (ctx.fill) {
                // iOS Safari supports the rule argument
                ctx.fill('evenodd');
            } else {
                // Fallback (older engines)
                ctx.clip('evenodd');
                ctx.fillStyle = '#000';
                ctx.fillRect(0, 0, w, h);
            }

            ctx.restore();
        })();

    }

    // Decide which dial is actually on top by sampling several points around the ring
    function getDialState(popup, hourCanvas) {
        if (!popup || !hourCanvas) return 'unknown';
        var minuteCanvas = popup.querySelector('.clock-timepicker-minute-canvas');

        // 1) Style visibility first (most reliable)
        var hv = hourCanvas ? getComputedStyle(hourCanvas) : null;
        var mv = minuteCanvas ? getComputedStyle(minuteCanvas) : null;

        var hourVisible =
            hv && hv.display !== 'none' && hv.visibility !== 'hidden' &&
            (parseFloat(hv.opacity || '1') > 0.05);

        var minuteVisible =
            mv && mv.display !== 'none' && mv.visibility !== 'hidden' &&
            (parseFloat(mv.opacity || '1') > 0.05);

        if (hourVisible && !minuteVisible) return 'hour';
        if (minuteVisible && !hourVisible) return 'minute';
        if (minuteVisible && hourVisible) {
            // both "visible" — prefer whichever is really on top at a couple ring points
            var rect = hourCanvas.getBoundingClientRect();
            var pts = [[0.84, 0.50], [0.50, 0.16], [0.16, 0.50]]; // E, N, W
            var hourHits = 0, minuteHits = 0;
            for (var i = 0; i < pts.length; i++) {
                var px = rect.left + rect.width * pts[i][0];
                var py = rect.top + rect.height * pts[i][1];
                var el = document.elementFromPoint(px, py);
                if (el === hourCanvas) hourHits++;
                else if (minuteCanvas && el === minuteCanvas) minuteHits++;
            }
            if (hourHits > minuteHits) return 'hour';
            if (minuteHits > hourHits) return 'minute';
        }

        // 2) Default to hour (popup typically opens on hours)
        return 'hour';
    }

    // Hit-test for your clock: returns { hour:0..23, ring:'outer'|'inner' }
    function hourHit(canvas, evt) {
        var r = canvas.getBoundingClientRect();
        var cx = r.left + r.width / 2, cy = r.top + r.height / 2;
        var x = (evt.clientX || (evt.touches && evt.touches[0].clientX) || 0) - cx;
        var y = (evt.clientY || (evt.touches && evt.touches[0].clientY) || 0) - cy;

        // angle: 0 at 12 o'clock, clockwise
        var angle = Math.atan2(y, x);
        var deg = (angle * 180 / Math.PI + 90); if (deg < 0) deg += 360; deg %= 360;
        var sector = Math.round(deg / 30) % 12; // 0..11 (0=12 o'clock)
        var dist = Math.hypot(x, y), min = Math.min(r.width, r.height)/2;

        // Ring bands from tuning (with padding for easier capture)
        var OUT_IN = min * TUNE.outerInnerRadius - TUNE.hitPaddingPx;
        var OUT_OUT = min * TUNE.outerOuterRadius + TUNE.hitPaddingPx;
        var IN_IN = min * TUNE.innerInnerRadius - TUNE.hitPaddingPx;
        var IN_OUT = min * TUNE.innerOuterRadius + TUNE.hitPaddingPx;

        var ring = (dist >= OUT_IN && dist <= OUT_OUT) ? 'outer'
            : (dist >= IN_IN && dist <= IN_OUT) ? 'inner'
                : (Math.abs(dist - (OUT_IN + OUT_OUT) / 2) < Math.abs(dist - (IN_IN + IN_OUT) / 2) ? 'outer' : 'inner');

        var hour = (ring === 'outer')
            ? (sector === 0 ? 12 : sector)          // 12,1..11
            : (sector === 0 ? 0 : sector + 12);    // 00,13..23

        return { hour: hour, ring: ring };
    }

    function wsbClearAllBlockMaps(root) {
        var host = (root && root.closest) ? (root.closest('.clock-timepicker') || root) : document;
        host.querySelectorAll('[data-wsb-blocked-hours]').forEach(function (el) {
            try { delete el.dataset.wsbBlockedHours; } catch (e) { }
            el.removeAttribute('data-wsb-blocked-hours');
        });
    }


    // Small tooltip
    function ensureTooltip(container) {
        var tip = container.querySelector('.wsb-hour-tooltip');
        if (!tip) {
            tip = document.createElement('div');
            tip.className = 'wsb-hour-tooltip';
            tip.style.position = 'fixed';
            tip.style.zIndex = '100000';
            tip.style.pointerEvents = 'none';
            tip.style.padding = '4px 8px';
            tip.style.fontSize = '12px';
            tip.style.borderRadius = '4px';
            tip.style.background = 'rgba(0,0,0,0.8)';
            tip.style.color = '#fff';
            tip.style.transform = 'translate(8px, -8px)';
            tip.style.whiteSpace = 'nowrap';
            tip.style.display = 'none';
            container.appendChild(tip);
        }
        return tip;
    }

    function bindHourGuardsOnPopup(popup, _ignoredOldHourCanvas, showTip, hideTip) {
        if (!popup || popup.dataset.wsbHourGuardBound) return;
        popup.dataset.wsbHourGuardBound = '1';

        function ctx() {
            var hc = popup.querySelector('.clock-timepicker-hour-canvas');
            if (!hc) return { hourCanvas: null, wrap: null, set: {} };
            return { hourCanvas: hc, wrap: hc.parentElement, set: wsbGetBlockedSet(hc.parentElement) };
        }

        var coarse = window.matchMedia && matchMedia('(pointer: coarse)').matches;

        // Tooltips only on fine pointers
        if (!coarse) {
            popup.addEventListener('pointermove', function (e) {
                var c = ctx();
                if (!c.hourCanvas || getDialState(popup, c.hourCanvas) !== 'hour' || !isPointerInHourRing(c.hourCanvas, e)) { hideTip(); return; }
                var hit = hourHit(c.hourCanvas, e);
                if (c.set[hit.hour]) showTip('Unavailable (occupied): ' + (hit.hour < 10 ? '0' : '') + hit.hour + ':00', e);
                else hideTip();
            }, true);
        }

        function stopIfBlocked(e) {
            var c = ctx();
            if (!c.hourCanvas || getDialState(popup, c.hourCanvas) !== 'hour') return;
            if (!isPointerInHourRing(c.hourCanvas, e)) return; // let Cancel/Done etc. work

            var hit = hourHit(c.hourCanvas, e);
            if (c.set[hit.hour]) {
                e.stopImmediatePropagation(); e.stopPropagation(); e.preventDefault();
                showTip('Unavailable (occupied): ' + (hit.hour < 10 ? '0' : '') + hit.hour + ':00', e);
                popup.style.animation = 'wsb-deny 120ms'; setTimeout(function () { popup.style.animation = ''; }, 140);
            } else {
                hideTip();
            }
        }

        // On mobile bind to the CANVAS; on desktop keep popup capture
        var target = coarse ? (ctx().hourCanvas || popup) : popup;
        target.addEventListener('pointerdown', stopIfBlocked, true);
        target.addEventListener('click', stopIfBlocked, true);
        target.addEventListener('touchstart', stopIfBlocked, { capture: true, passive: false });
    }


    function observePopupForRedraw(popup /*, oldHourCanvas */) {
        if (!popup) return;

        function draw() {
            var hourCanvas = popup.querySelector('.clock-timepicker-hour-canvas');
            if (!hourCanvas) return;

            var state = getDialState(popup, hourCanvas);
            var wrap = hourCanvas.parentElement;

            // If minutes are definitely on top, hide overlay (visual only)
            if (state === 'minute') {
                var ov = wrap && wrap.querySelector('#wsb-hour-overlay');
                if (ov) ov.style.display = 'none';
                return;
            }

            // Bring overlay to top and paint with the LIVE set
            var set = wsbGetBlockedSet(wrap);
            var ov2 = wrap && wrap.querySelector('#wsb-hour-overlay');
            if (ov2) { wrap.appendChild(ov2); ov2.style.zIndex = TUNE.overlayZ; ov2.style.display = 'block'; }
            drawBlockedHourOverlay(hourCanvas, set);
        }

        var schedule = function () { clearTimeout(schedule.t); schedule.t = setTimeout(draw, 40); };
        popup.addEventListener('click', function () { schedule(); }, true);
        new MutationObserver(function () { schedule(); })
            .observe(popup, { attributes: true, childList: true, subtree: true, attributeFilter: ['style', 'class'] });
        schedule();
    }


    function wsbSetBlockedSet(node, set) {
        if (!node) return;
        var hasAny = set && Object.keys(set).some(function (k) { return !!set[k]; });
        if (!hasAny) {
            node.removeAttribute('data-wsb-blocked-hours');
        } else {
            node.dataset.wsbBlockedHours = JSON.stringify(set);
        }
    }


    function wsbGetBlockedSet(node) {
        if (!node || !node.dataset || !node.dataset.wsbBlockedHours) return {};
        try { return JSON.parse(node.dataset.wsbBlockedHours) || {}; }
        catch (e) { return {}; }
    }



    // Apply rules to a specific date + its paired time fields
    function applyForDate(dateInput) {
        var iso = toISO(dateInput.value);
        if (!iso) return;
        var prettyRanges = (DAYS[iso] || []).map(function (r) { return r[0] + ' – ' + r[1]; }).join(', ');
        var ranges = rangesFor(iso);
        var blockedSet = blockedHoursFromRanges(ranges);

        var fullDay = isFullDayRanges(DAYS[iso] || []);

        pairedTimeElements(dateInput).forEach(function (el) {
            // Always reset when switching dates
            el.disabled = false;
            el.classList.remove('wsb-time-disabled');

            if (fullDay) {
                // Full-day: disable time field entirely (any type)
                if (el.tagName === 'SELECT') {
                    [].forEach.call(el.options, function (opt) { opt.disabled = true; opt.hidden = true; });
                    el.value = '';
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                } else {
                    el.value = '';
                }
                el.disabled = true;
                el.classList.add('wsb-time-disabled');
                mkHint(el, 'Fully booked for the selected day');

                // Hide any hour overlay if popup is present
                var wrap = el.closest && el.closest('.clock-timepicker');
                if (wrap) {
                    var ov = wrap.querySelector('#wsb-hour-overlay');
                    if (ov) ov.style.display = 'none';
                }
                return; // skip the per-type logic below
            }

            // INPUT type="time"
            else if (el.type === 'time') {
                var mins = timeToMinutes(el.value);
                var blocked = isBlocked(mins, ranges);
                try { el.setCustomValidity(blocked ? 'That time is unavailable.' : ''); } catch (e) { }
                if (blocked) { el.value = ''; }
                mkHint(el, (ranges.length ? 'No availability between: ' + prettyRanges : ''));
            }
            // INPUT type="text" (your .clock-timepicker)
            else if (el.type === 'text') {
                var mins2 = timeToMinutes(el.value);
                var blocked2 = isBlocked(mins2, ranges);
                if (blocked2) el.value = '';

                mkHint(el, (ranges.length ? 'No availability between: ' + prettyRanges : ''));

                // Rebind the input validator so it always uses the CURRENT ranges
                if (el._wsbInputValidator) {
                    el.removeEventListener('input', el._wsbInputValidator);
                }

                el._wsbInputValidator = function () {
                    var m = timeToMinutes(el.value);
                    if (isBlocked(m, ranges)) {
                        el.value = '';
                        try { el.setCustomValidity('That time is unavailable.'); } catch (e) { }
                    } else {
                        try { el.setCustomValidity(''); } catch (e) { }
                    }
                };

                el.addEventListener('input', el._wsbInputValidator);

                try { el.setCustomValidity(''); } catch (e) { }


                // Find the popup + canvases
                var wrap = el.closest && el.closest('.clock-timepicker');
                if (wrap) {
                    var popup = wrap.querySelector('.clock-timepicker-popup');
                    var hourCanvas = popup && popup.querySelector('.clock-timepicker-hour-canvas');

                    wsbClearAllBlockMaps(el);

                    var dialWrap = (hourCanvas && hourCanvas.parentElement) || wrap;
                    wsbSetBlockedSet(dialWrap, blockedSet);


                    // tooltip (safe no-ops if no wrap)
                    var tip, showTip = function () { }, hideTip = function () { };
                    if (wrap) {
                        tip = ensureTooltip(wrap);
                        showTip = function (msg, e) { tip.textContent = msg; tip.style.left = (e.clientX || 0) + 'px'; tip.style.top = (e.clientY || 0) + 'px'; tip.style.display = 'block'; };
                        hideTip = function () { tip.style.display = 'none'; };
                    }

                    // NEW: bind guards at popup level (not hourCanvas), once
                    bindHourGuardsOnPopup(popup, hourCanvas, showTip, hideTip);

                    // If the popup is already visible *and* hours are active, paint now; otherwise just continue wiring
                    if (popup && popup.style.display !== 'none' && getDialState(popup, hourCanvas) === 'hour') {
                        drawBlockedHourOverlay(hourCanvas, blockedSet);
                    }


                }


                // Draw overlays when popup becomes visible (focus/click can toggle it)
                var drawIfVisible = function () {
                    if (!popup || popup.style.display === 'none') return;
                    var hc = popup.querySelector('.clock-timepicker-hour-canvas');
                    if (!hc) return;
                    var w = hc.parentElement;
                    var live = wsbGetBlockedSet(w);
                    drawBlockedHourOverlay(hc, live);
                    setTimeout(function () {
                        if (getDialState(popup, hc) === 'minute') {
                            var ov = w && w.querySelector('#wsb-hour-overlay');
                            if (ov) ov.style.display = 'none';
                        }
                    }, 60);
                };

                el.addEventListener('focus', drawIfVisible);
                el.addEventListener('click', drawIfVisible);
                setTimeout(drawIfVisible, 60);


                observePopupForRedraw(popup, hourCanvas);

                // Tooltip (safe if no .clock-timepicker exists yet)
                var tip, showTip = function () { }, hideTip = function () { };
                if (wrap) {
                    tip = ensureTooltip(wrap);
                    showTip = function (msg, e) {
                        tip.textContent = msg;
                        tip.style.left = (e.clientX || 0) + 'px';
                        tip.style.top = (e.clientY || 0) + 'px';
                        tip.style.display = 'block';
                    };
                    hideTip = function () { tip.style.display = 'none'; };
                }


                // Hover: show tooltip when hovering blocked hour
                if (hourCanvas) {
                    hourCanvas.addEventListener('pointermove', function (e) {
                        var wrap = hourCanvas.parentElement;
                        var live = wsbGetBlockedSet(wrap);           // <— always current for the selected date
                        var hit = hourHit(hourCanvas, e);
                        if (live[hit.hour]) showTip('Unavailable (occupied): ' + (hit.hour < 10 ? '0' : '') + hit.hour + ':00', e);
                        else hideTip();
                    });


                    // Capture clicks/taps: stop at source when blocked
                    var stopIfBlocked = function (e) {
                        var wrap = hourCanvas.parentElement;
                        var set = wsbGetBlockedSet(wrap);  // <-- live set per current date
                        var hit = hourHit(hourCanvas, e);
                        if (set[hit.hour]) {
                            e.stopImmediatePropagation(); e.stopPropagation(); e.preventDefault();
                            showTip('Unavailable (occupied): ' + (hit.hour < 10 ? '0' : '') + hit.hour + ':00', e);
                            popup.style.animation = 'wsb-deny 120ms'; setTimeout(function () { popup.style.animation = ''; }, 140);
                        } else {
                            hideTip();
                        }
                    };

                    hourCanvas.addEventListener('pointerdown', stopIfBlocked, true);
                    hourCanvas.addEventListener('click', stopIfBlocked, true);
                    hourCanvas.addEventListener('touchstart', stopIfBlocked, { capture: true, passive: false });
                }

                // NEW: ensure popup options themselves can’t be clicked
                bindTextPopupGuards(el, ranges, iso);
            }

        });

        log('Applied block-outs for', iso, DAYS[iso] || []);
    }

    function wireDate(d) {
        if (!d || d.dataset.wsbBound) return;
        d.dataset.wsbBound = '1';

        var handler = function () { applyForDate(d); };

        // Native edits
        d.addEventListener('change', handler);
        d.addEventListener('input', handler);

        // jQuery UI Datepicker hook
        if (window.jQuery && jQuery.fn && jQuery.fn.datepicker &&
            (d.classList.contains('hasDatepicker') || jQuery(d).data('datepicker'))) {
            try {
                var prev = jQuery(d).datepicker('option', 'onSelect');
                jQuery(d).datepicker('option', 'onSelect', function (dateText, inst) {
                    if (typeof prev === 'function') prev.call(this, dateText, inst);
                    handler();
                });
            } catch (e) { }
        }

        // NEW: run once on initial load so the block map exists before the first open
        // (handles server-prefilled dates and most init races)
        if (d.value) {
            requestAnimationFrame(handler);   // after current paint
            setTimeout(handler, 120);         // after picker markup settles
        }
    }


    function attach() {
        var dates = document.querySelectorAll(SEL.date || '');
        dates.forEach(wireDate);

        document.addEventListener('wsb:blockouts:rescan', attach);

        // Optional: if your form dynamically replaces these nodes, re-scan cheaply
        // setTimeout(() => document.querySelectorAll(SEL.date||'').forEach(wireDate), 300);
    }


    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', attach);
    else attach();
})();
