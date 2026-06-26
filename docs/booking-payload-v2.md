# BookingPayload v2 Contract

## Purpose

BookingPayload v2 is the new canonical payload contract for the Wolf Shuttles marketing-site booking intake.

The goals are:

- remove dependency on Bricks hashed field IDs
- remove hidden fields as the source of truth
- support one-way and return transfers immediately
- support charter next
- support additional stops / multi-leg trips later
- allow the booking site to branch cleanly between legacy and v2 payloads

## Naming decisions

Use these terms:

```text
schema_version: "2.0"
service_group: transfer | charter
service_type: airport_pickup | airport_dropoff | city_transfer | charter_hire
trip_type: one_way | return | charter
```

Avoid `service_family`.

Rename legacy luggage fields:

```text
largeBags      → luggage.check_in_bags
carryOnBags    → luggage.carry_on_bags
babySeatCount  → add_ons.baby_seats
```

## Core structure

```json
{
  "schema_version": "2.0",
  "source": {
    "site": "marketing",
    "channel": "shortcode_form",
    "page_url": "",
    "referrer": ""
  },
  "trip_type": "one_way",
  "service_group": "transfer",
  "service_type": "airport_pickup",
  "customer": {
    "name": "",
    "email": "",
    "phone": ""
  },
  "passengers": 1,
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
  "validation_flags": {}
}
```

## Legs model

A trip is made from one or more legs.

A direct one-way trip has one leg:

```json
{
  "sequence": 1,
  "leg_group": "outbound",
  "leg_type": "direct",
  "from": {},
  "to": {},
  "pickup_date": "27/06/2026",
  "pickup_time": "14:42",
  "distance_km": 31.4,
  "duration_text": "35 mins",
  "service_type": "airport_pickup"
}
```

A return trip has at least two legs:

```json
[
  {
    "sequence": 1,
    "leg_group": "outbound",
    "leg_type": "direct"
  },
  {
    "sequence": 2,
    "leg_group": "return",
    "leg_type": "direct"
  }
]
```

An additional stop should be represented as extra legs. For example, A → garage stop → B should become:

```json
[
  {
    "sequence": 1,
    "leg_group": "outbound",
    "leg_type": "stopover_segment",
    "from": "A",
    "to": "Garage stop"
  },
  {
    "sequence": 2,
    "leg_group": "outbound",
    "leg_type": "final_segment",
    "from": "Garage stop",
    "to": "B"
  }
]
```

The first implementation does not need to render additional stops in the form, but the schema must allow them.

## Location object

Each `from` and `to` object should use this structure:

```json
{
  "label": "Cape Town International Airport (CPT), Matroosfontein, Cape Town, South Africa",
  "name": "Cape Town International Airport",
  "town": "Cape Town",
  "neighbourhood": "Matroosfontein",
  "place_id": "",
  "coords": {
    "lat": -33.9688707,
    "lng": 18.5997595
  }
}
```

## Charter structure

Charter can use the same top-level structure with:

```json
{
  "trip_type": "charter",
  "service_group": "charter",
  "service_type": "charter_hire",
  "charter": {
    "poi": "Cape Point",
    "poi_other": "",
    "poi_reference": {},
    "poi_distance_from_pickup_km": 0,
    "duration_text": "10 hours"
  }
}
```

Charter does not need to be built in the first shortcode milestone, but v2 must not block charter.

## Handover modes

The marketing plugin should be able to switch between handover modes during development:

```text
legacy_hash
v2_token
```

`legacy_hash` keeps current behaviour:

```text
/bookings/?hash=<hash>
```

`v2_token` prepares the future URL:

```text
/bookings/?booking_token=<token>&trip_id=<trip_id>
```

The booking site will later branch based on payload version and URL parameters.

## Trust boundary

Frontend JS may collect route facts, but the backend must not blindly trust frontend-derived pricing flags.

The backend should validate/normalise:

- required fields
- date and time
- minimum lead time
- blockouts
- passengers and luggage values
- trip type
- service group/type
- route metadata shape
- add-on flags

The booking system remains the final source for pricing and vehicle availability.
