# BookingPayload v2 Contract

## Purpose

BookingPayload v2 is the canonical marketing-site booking intake shape for the Booking Builder.

The live shortcode preview now renders this payload in real time as the form changes, including page load, input, change, blur, trip-type toggles, additional-stop toggles, and submit.

## Naming decisions

Use these terms:

```text
schema_version: "2.0"
service_group: transfer | charter
service_type: airport_pickup | airport_dropoff | city_transfer | charter_hire
trip_type: one_way | return | charter
```

Avoid `service_family`.

Keep the canonical marketing field names:

```text
passengers
baby_seats
check_in_bags
carry_on_bags
trailer
oversize_luggage
outbound_from
outbound_to
outbound_pickup_date
outbound_pickup_time
return_from
return_to
return_pickup_date
return_pickup_time
additional_stop_enabled
additional_stop
```

## Core structure

The browser preview uses this shape:

```json
{
  "schema_version": "2.0",
  "source": {
    "site": "marketing",
    "channel": "shortcode_form",
    "page_url": "https://wolfshuttles.local/booking-builder/",
    "referrer": ""
  },
  "service_group": "transfer",
  "service_type": "city_transfer",
  "trip_type": "one_way",
  "customer": {
    "name": "",
    "email": "",
    "phone": ""
  },
  "passengers": 1,
  "baby_seats": 0,
  "luggage": {
    "check_in_bags": 0,
    "carry_on_bags": 0
  },
  "add_ons": {
    "baby_seats": 0,
    "trailer": false,
    "oversize_luggage": false
  },
  "legs": [],
  "route": {
    "place_ids": [],
    "toll_gates": [],
    "route_options": []
  },
  "tracking": {},
  "validation_flags": {},
  "meta": {
    "handover_mode": "preview_only",
    "created_at": ""
  },
  "charter": null
}
```

## Legs model

A direct one-way trip has one leg:

```json
{
  "sequence": 1,
  "leg_group": "outbound",
  "leg_type": "direct",
  "service_type": "city_transfer",
  "from": {
    "label": "",
    "name": "",
    "town": "",
    "neighbourhood": "",
    "place_id": "",
    "coords": {
      "lat": null,
      "lng": null
    },
    "formatted_address": ""
  },
  "to": {
    "label": "",
    "name": "",
    "town": "",
    "neighbourhood": "",
    "place_id": "",
    "coords": {
      "lat": null,
      "lng": null
    },
    "formatted_address": ""
  },
  "pickup_date": "",
  "pickup_time": "",
  "pickup_datetime": "",
  "stops": [],
  "route": {}
}
```

A return trip adds a second leg with `leg_group: "return"` and `sequence: 2`.

An additional stop is stored on the outbound leg:

```json
{
  "type": "additional_stop",
  "location": {
    "label": "",
    "name": "",
    "town": "",
    "neighbourhood": "",
    "place_id": "",
    "coords": {
      "lat": null,
      "lng": null
    },
    "formatted_address": ""
  }
}
```

## Preview behavior

The live preview should update:

- on page load
- on input
- on change
- on blur
- when the trip type toggles
- when the additional stop toggle changes
- on submit

Submit remains intercepted. The preview is local only.

If `?debug=1` is present, the browser logs the generated payload to the console.
