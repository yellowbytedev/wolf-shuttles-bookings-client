/*
 * Reference JS for realtime BookingPayload v2 preview.
 * Merge into the existing assets/js/booking-client-form.js rather than blindly replacing it.
 */
(function () {
  'use strict';

  const DEBUG = new URLSearchParams(window.location.search).get('debug') === '1';

  function debounce(fn, delay) {
    let timer = null;
    return function debounced(...args) {
      clearTimeout(timer);
      timer = setTimeout(() => fn.apply(this, args), delay);
    };
  }

  function value(form, name, fallback = '') {
    const field = form.querySelector(`[name="${name}"]`);
    if (!field) return fallback;
    if (field.type === 'checkbox') return field.checked;
    return field.value || fallback;
  }

  function intValue(form, name, fallback = 0) {
    const parsed = parseInt(value(form, name, fallback), 10);
    return Number.isFinite(parsed) ? parsed : fallback;
  }

  function textLocation(label) {
    return {
      label: label || '',
      place_id: '',
      lat: null,
      lng: null,
      formatted_address: ''
    };
  }

  function buildPayload(form) {
    const tripType = value(form, 'trip_type', 'one_way');
    const additionalStopEnabled = Boolean(value(form, 'additional_stop_enabled', false));
    const additionalStop = value(form, 'additional_stop', '');

    const outboundLeg = {
      type: 'outbound',
      from: textLocation(value(form, 'outbound_from', '')),
      to: textLocation(value(form, 'outbound_to', '')),
      pickup_date: value(form, 'outbound_pickup_date', ''),
      pickup_time: value(form, 'outbound_pickup_time', ''),
      pickup_datetime: `${value(form, 'outbound_pickup_date', '')} ${value(form, 'outbound_pickup_time', '')}`.trim(),
      stops: [],
      route: {}
    };

    if (additionalStopEnabled && additionalStop) {
      outboundLeg.stops.push({
        type: 'additional_stop',
        location: textLocation(additionalStop)
      });
    }

    const legs = [outboundLeg];

    if (tripType === 'return') {
      legs.push({
        type: 'return',
        from: textLocation(value(form, 'return_from', '')),
        to: textLocation(value(form, 'return_to', '')),
        pickup_date: value(form, 'return_pickup_date', ''),
        pickup_time: value(form, 'return_pickup_time', ''),
        pickup_datetime: `${value(form, 'return_pickup_date', '')} ${value(form, 'return_pickup_time', '')}`.trim(),
        stops: [],
        route: {}
      });
    }

    return {
      schema_version: '2.0',
      source: 'marketing_booking_builder',
      service_type: 'city_transfer',
      trip_type: tripType,
      passengers: intValue(form, 'passengers', 1),
      baby_seats: intValue(form, 'baby_seats', 0),
      check_in_bags: intValue(form, 'check_in_bags', 0),
      carry_on_bags: intValue(form, 'carry_on_bags', 0),
      add_ons: {
        trailer: Boolean(value(form, 'trailer', false)),
        oversize_luggage: Boolean(value(form, 'oversize_luggage', false))
      },
      legs
    };
  }

  function init(root) {
    const form = root.querySelector('form');
    const preview = root.querySelector('[data-wsb-payload-preview]');
    const status = root.querySelector('[data-wsb-preview-status]');

    if (!form || !preview) return;

    const render = (message = 'Live preview') => {
      const payload = buildPayload(form);
      preview.textContent = JSON.stringify(payload, null, 2);
      if (status) status.textContent = message;
      if (DEBUG) console.log('[WSB BookingPayload v2]', payload);
    };

    const debouncedRender = debounce(() => render('Live preview updated'), 200);

    form.addEventListener('input', debouncedRender);
    form.addEventListener('change', () => render('Preview updated'));
    form.addEventListener('blur', () => render('Preview updated'), true);
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      render('Preview updated. Real booking submission is not enabled yet.');
    });

    render('Live preview');
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-wsb-booking-builder]').forEach(init);
  });
}());
