(function ($) {
    'use strict';

    var CFG = window.WSB_BLOCKOUTS || {};
    var DAYS = CFG.days || {};
    var DATE_SEL = (CFG.selectors && CFG.selectors.date) || '[data-wsb-datepicker], input[type="date"], input[data-wsb-charter-day-field="date"]';
    var WSB_I18N = CFG.i18n || {};
    WSB_I18N.fullDay = WSB_I18N.fullDay || 'Unavailable (fully booked)';
    WSB_I18N.partial = WSB_I18N.partial || 'Partially unavailable';

    var FULL = new Set(Object.keys(DAYS).filter(function (iso) {
        var ranges = DAYS[iso] || [];
        return ranges.some(function (r) {
            var s = (r && r[0] || '').trim();
            var e = (r && r[1] || '').trim();
            return s === '00:00' && (e === '23:59' || e === '24:00');
        });
    }));

    var scanQueued = false;

    function formatIso(date) {
        return $.datepicker.formatDate('yy-mm-dd', date);
    }

    function wsbNextAvailableDate(fromDate) {
        var d = new Date(fromDate.getFullYear(), fromDate.getMonth(), fromDate.getDate());
        var max = 370;
        for (var i = 0; i < max; i++) {
            d.setDate(d.getDate() + 1);
            var iso = formatIso(d);
            if (!FULL.has(iso)) {
                return new Date(d.getTime());
            }
        }
        return null;
    }

    function wsbFormatRanges(ranges) {
        if (!Array.isArray(ranges) || !ranges.length) {
            return '';
        }
        return ranges.map(function (r) {
            return (r[0] || '') + '—' + (r[1] || '');
        }).join(', ');
    }

    function wsbBeforeShowDay(date) {
        var iso = formatIso(date);
        var ranges = DAYS[iso];

        if (FULL.has(iso)) {
            return [false, 'wsb-day-blocked', WSB_I18N.fullDay];
        }

        if (ranges && ranges.length) {
            return [true, 'wsb-day-partial', WSB_I18N.partial + ': ' + wsbFormatRanges(ranges)];
        }

        return [true, ''];
    }

    function wsbPaintTitles(inst) {
        var $dp = inst && inst.dpDiv ? inst.dpDiv : $('#ui-datepicker-div');
        if (!$dp.length) {
            return;
        }

        $dp.find('td > a, td > span').each(function () {
            var $a = $(this);
            var $td = $a.closest('td');
            if ($td.hasClass('ui-datepicker-other-month')) {
                return;
            }

            var day = parseInt($a.text(), 10);
            if (!day) {
                return;
            }

            var y = inst.drawYear;
            var m = inst.drawMonth;
            var d = new Date(y, m, day);
            var iso = formatIso(d);
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

    function schedulePaint(inst) {
        window.setTimeout(function () {
            if (inst) {
                wsbPaintTitles(inst);
            }
        }, 0);
    }

    function attachOne(el) {
        var $el = $(el);
        if ($el.data('wsbDp')) {
            return;
        }

        if (!$.fn || !$.fn.datepicker) {
            return;
        }

        $el.data('wsbDp', 1);
        $el.attr('autocomplete', 'off');

        $el.datepicker({
            dateFormat: 'yy-mm-dd',
            showOn: 'focus',
            showAnim: 'fadeIn',
            minDate: 0,
            beforeShowDay: wsbBeforeShowDay,
            beforeShow: function (input, inst) {
                schedulePaint(inst);
            },
            onChangeMonthYear: function (y, m, inst) {
                schedulePaint(inst);
            },
            onSelect: function () {
                var inst = $(this).data('datepicker') || $.datepicker._getInst(this);
                schedulePaint(inst);
                this.dispatchEvent(new Event('input', { bubbles: true }));
                this.dispatchEvent(new Event('change', { bubbles: true }));
                document.dispatchEvent(new CustomEvent('wsb:blockouts:date-selected'));
            }
        });

        $el.off('.wsbDatepicker').on('click.wsbDatepicker focus.wsbDatepicker', function () {
            try {
                $el.datepicker('show');
            } catch (error) {
                // Ignore browsers that block programmatic opening.
            }
        });

        if (!$el.val()) {
            var pick = wsbNextAvailableDate(new Date());
            if (pick) {
                try {
                    $el.datepicker('setDate', pick);
                } catch (e) {}
                window.setTimeout(function () {
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                    document.dispatchEvent(new CustomEvent('wsb:blockouts:date-prefilled'));
                }, 0);
            }
        }
    }

    function isVisible(el) {
        if (!el || !el.ownerDocument) {
            return false;
        }
        var style = window.getComputedStyle(el);
        if (!style || style.display === 'none' || style.visibility === 'hidden' || parseFloat(style.opacity || '1') === 0) {
            return false;
        }
        var rect = el.getBoundingClientRect();
        return rect.width > 0 && rect.height > 0;
    }

    function scan(root) {
        if (!$.fn || !$.fn.datepicker) {
            return;
        }

        var scope = root || document;
        var fields = scope.querySelectorAll ? scope.querySelectorAll(DATE_SEL) : [];
        fields.forEach(function (field) {
            if (isVisible(field)) {
                attachOne(field);
            }
        });
    }

    function scheduleScan(root) {
        if (scanQueued) {
            return;
        }
        scanQueued = true;
        window.requestAnimationFrame(function () {
            scanQueued = false;
            scan(root || document);
        });
    }

    function boot() {
        document.addEventListener('focusin', function (event) {
            var target = event.target;
            if (!target || !target.matches) {
                return;
            }

            if (!target.matches(DATE_SEL)) {
                if (!target.closest || !target.closest('#ui-datepicker-div')) {
                    try {
                        $.datepicker._hideDatepicker();
                    } catch (error) {
                        // The picker may not have been initialised yet.
                    }
                }
                return;
            }

            if (!isVisible(target)) {
                return;
            }
            attachOne(target);
        });
        scheduleScan(document);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    document.addEventListener('wsb:blockouts:rescan', function () {
        scheduleScan(document);
    });
    document.addEventListener('wsb:booking-builder:fields-added', function () {
        scheduleScan(document);
    });
    document.addEventListener('wsb:charter-days-updated', function () {
        scheduleScan(document);
    });

    if (CFG.debug) {
        console.info('[WSB] datepickers.js safe mode; selector:', DATE_SEL);
    }
})(jQuery);
