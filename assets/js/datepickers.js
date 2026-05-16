(function ($) {
    // --- helpers ---------------------------------------------------------------
    var CFG = window.WSB_BLOCKOUTS || {};
    var DAYS = CFG.days || {};
    var FULL = new Set(Object.keys(DAYS).filter(function (iso) { return isFullDay(DAYS[iso]); }));

    // fallbacks if PHP didn't provide selectors
    var DATE_SEL = (CFG.selectors && CFG.selectors.date) || 'input[name$="_date"]';

    // Texts (can be overridden from PHP via WSB_BLOCKOUTS.i18n)
    var WSB_I18N = {};
    WSB_I18N.fullDay = WSB_I18N.fullDay || 'Unavailable (fully booked)';
    WSB_I18N.partial = WSB_I18N.partial || 'Partially unavailable';

    // Build a Set of fully blocked days (00:00—23:59 or 24:00)
    var FULL = new Set(Object.keys(DAYS).filter(function (iso) {
        var ranges = DAYS[iso] || [];
        return ranges.some(function (r) {
            var s = (r && r[0] || '').trim(), e = (r && r[1] || '').trim();
            return s === '00:00' && (e === '23:59' || e === '24:00');
        });
    }));

    // Find the next selectable calendar day starting from tomorrow.
    // Skips days that are fully blocked in DAYS/FULL.
    function wsbNextAvailableDate(fromDate) {
        var d = new Date(fromDate.getFullYear(), fromDate.getMonth(), fromDate.getDate());
        var max = 370; // safety
        for (var i = 0; i < max; i++) {
            d.setDate(d.getDate() + 1); // start at *tomorrow*
            var iso = jQuery.datepicker.formatDate('yy-mm-dd', d);
            if (!FULL.has(iso)) return new Date(d.getTime());
        }
        return null; // everything blocked (unlikely)
    }


    // Format [["05:00","07:00"],["09:00","12:00"]] -> "05:00—07:00, 09:00—12:00"
    function wsbFormatRanges(ranges) {
        if (!Array.isArray(ranges) || !ranges.length) return '';
        return ranges.map(function (r) { return (r[0] || '') + '—' + (r[1] || ''); }).join(', ');
    }

    // jQuery UI hook: controls disabled/selectable + class + native title
    function wsbBeforeShowDay(date) {
        var iso = jQuery.datepicker.formatDate('yy-mm-dd', date);
        var ranges = DAYS[iso];

        if (FULL.has(iso)) {
            // disabled + tooltip
            return [false, 'wsb-day-blocked', WSB_I18N.fullDay];
        }
        if (ranges && ranges.length) {
            // selectable + tooltip with the blocked windows
            return [true, 'wsb-day-partial', WSB_I18N.partial + ': ' + wsbFormatRanges(ranges)];
        }
        return [true, ''];
    }

    // Fallback: force titles on the rendered cells (covers all jQuery UI versions)
    function wsbPaintTitles(inst) {
        var $dp = inst && inst.dpDiv ? inst.dpDiv : jQuery('#ui-datepicker-div');
        if (!$dp.length) return;

        $dp.find('td > a, td > span').each(function () {
            var $a = jQuery(this);
            var $td = $a.closest('td');
            if ($td.hasClass('ui-datepicker-other-month')) return;

            var txt = $a.text();
            var day = parseInt(txt, 10);
            if (!day) return;

            var y = inst.drawYear, m = inst.drawMonth;              // month is 0-based
            var d = new Date(y, m, day);
            var iso = jQuery.datepicker.formatDate('yy-mm-dd', d);
            var ranges = DAYS[iso];

            if (FULL.has(iso)) {
                $a.attr('title', WSB_I18N.fullDay);
            } else if (ranges && ranges.length) {
                $a.attr('title', WSB_I18N.partial + ': ' + wsbFormatRanges(ranges));
            } else {
                $a.removeAttr('title');
            }
        });
    }

    function mins(t) {
        if (!t) return NaN;
        t = String(t).trim().toLowerCase();
        var m = t.match(/^(\d{1,2})(?::?(\d{2}))?\s*(am|pm)?$/); if (!m) return NaN;
        var h = +m[1], mi = +(m[2] || 0), ap = m[3] || '';
        if (ap === 'pm' && h < 12) h += 12; if (ap === 'am' && h === 12) h = 0;
        return h * 60 + mi;
    }

    function isFullDay(ranges) {
        if (!Array.isArray(ranges)) return false;
        for (var i = 0; i < ranges.length; i++) {
            var s = mins(ranges[i][0]), e = mins(ranges[i][1]);
            if (s === 0 && (e >= 1439 && e <= 1440)) return true;
        }
        return false;
    }

    function attachOne(el) {
        var $el = $(el);
        if ($el.data('wsbDp')) return;
        $el.attr('autocomplete', 'off');
        $el.datepicker({
            dateFormat: 'dd/mm/yy',
            minDate: 0,                          // TODAY selectable
            beforeShowDay: wsbBeforeShowDay,     // <- enables tooltip + disable logic
            beforeShow: function (input, inst) { // <- force titles when popup opens
                setTimeout(function () { wsbPaintTitles(inst); }, 0);
            },
            onChangeMonthYear: function (y, m, inst) { // <- repaint when month changes
                setTimeout(function () { wsbPaintTitles(inst); }, 0);
            },
            onSelect: function () {               // keep your existing change events
                var inst = jQuery(this).data('datepicker') || jQuery.datepicker._getInst(this);
                setTimeout(function () { if (inst) wsbPaintTitles(inst); }, 0);
                this.dispatchEvent(new Event('change', { bubbles: true }));
                document.dispatchEvent(new CustomEvent('wsb:blockouts:rescan'));
            }
        });

        // Prefill default date if the field is empty: tomorrow or next available day.
        if (!$el.val()) {
            var pick = wsbNextAvailableDate(new Date());
            if (pick) {
                try { $el.datepicker('setDate', pick); } catch (e) { }
                // Fire the same hooks your onSelect uses so the rest of your logic runs:
                setTimeout(function () {
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                    document.dispatchEvent(new CustomEvent('wsb:blockouts:rescan'));
                }, 0);
            }
        }

        $el.data('wsbDp', 1);
    }

    function scan() {
        if (!$.fn || !$.fn.datepicker) return;
        document.querySelectorAll(DATE_SEL).forEach(attachOne);
    }

    // run, retry, and observe
    var tries = 0, t = setInterval(function () { tries++; scan(); if (tries > 20) clearInterval(t); }, 150);
    new MutationObserver(scan).observe(document.documentElement, { subtree: true, childList: true });
    document.addEventListener('wsb:blockouts:rescan', scan);

    if (CFG.debug) console.info('[WSB] datepickers.js running; selector:', DATE_SEL);
})(jQuery);

