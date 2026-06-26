# Known Issues From Uploaded Debug Logs

The uploaded `debug.zip` production logs were scanned for recurring PHP warnings/notices. These are not all Phase 2 blockers, but they should be tracked so the refactor does not preserve avoidable noise.

## Highest-volume issues

1. `117586` occurrences — PHP Notice:  Function wp_register_script was called incorrectly. Unrecognized key(s) in the $args param: defer. Supported keys: strategy, in_footer, fetchpriority, module_dependencies Please see Debugging in WordPress for more inf
2. `58794` occurrences — PHP Notice:  Function _load_textdomain_just_in_time was called incorrectly. Translation loading for the woocommerce domain was triggered too early. This is usually an indicator for some code in the plugin or theme running too earl
3. `58794` occurrences — PHP Notice:  Function _load_textdomain_just_in_time was called incorrectly. Translation loading for the gravityforms domain was triggered too early. This is usually an indicator for some code in the plugin or theme running too ear
4. `1314` occurrences — PHP Warning:  Undefined variable $bandKey in /PATH on line N
5. `916` occurrences — PHP Warning:  Undefined variable $pickupKm in /PATH on line N
6. `362` occurrences — PHP Deprecated:  Function wc_enqueue_js is deprecated since version 10.4.0! Use wp_add_inline_script instead. in /PATH on line N
7. `137` occurrences — PHP Warning:  Undefined array key "return_service_type" in /PATH on line N
8. `136` occurrences — PHP Warning:  Undefined array key "serviceType" in /PATH on line N
9. `136` occurrences — PHP Warning:  Undefined array key "returnNameFrom" in /PATH on line N
10. `136` occurrences — PHP Warning:  Undefined array key "returnNameTo" in /PATH on line N
11. `136` occurrences — PHP Warning:  Undefined array key "return_origin_hq_km" in /PATH on line N
12. `136` occurrences — PHP Warning:  Undefined array key "return_destination_hq_km" in /PATH on line N
13. `136` occurrences — PHP Warning:  Undefined array key "outboundDuration" in /PATH on line N
14. `136` occurrences — PHP Warning:  Undefined array key "emptyLegsDuration" in /PATH on line N
15. `136` occurrences — PHP Warning:  Undefined array key "pickupDuration" in /PATH on line N
16. `136` occurrences — PHP Warning:  Undefined array key "dropoffDuration" in /PATH on line N
17. `136` occurrences — PHP Warning:  Undefined array key "returnDuration" in /PATH on line N
18. `136` occurrences — PHP Warning:  Undefined array key "returnEmptyLegsDuration" in /PATH on line N
19. `136` occurrences — PHP Warning:  Undefined array key "returnPickupDuration" in /PATH on line N
20. `136` occurrences — PHP Warning:  Undefined array key "returnDropoffDuration" in /PATH on line N

## Immediate interpretation

- The `wp_register_script` `defer` notices are likely caused by old enqueue argument shape and should be replaced with the modern `strategy => defer` pattern where applicable.
- The early textdomain notices indicate one or more plugins/themes are triggering translation loading too early. These are noisy but not directly part of the booking intake logic.
- The charter undefined-array-key warnings show that the legacy flat payload expects transfer/return fields even for charter payloads. The v2 schema should avoid this by making charter fields explicit and optional transfer-return fields safe.
- Undefined `$bandKey`, `$pickupKm`, and `$html` warnings should be captured in the booking-site bug queue, not fixed inside the marketing intake refactor unless touched by the current work.

## Phase 2 rule

Do not stop the booking intake foundation to fix all historical warnings. Only fix issues directly touched by the new intake code or required for v2 handover to work locally.