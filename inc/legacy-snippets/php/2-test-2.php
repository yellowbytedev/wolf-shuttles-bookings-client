<?php
// <Internal Doc Start>
/*
*
* @description: 
* @tags: 
* @group: 
* @name: Implement JQuery Timepicker on booking form
* @type: PHP
* @status: published
* @created_by: 
* @created_at: 
* @updated_at: 2026-02-25 19:45:21
* @is_valid: 
* @updated_by: 
* @priority: 10
* @run_at: all
* @load_as_file: 
* @load_in_block_editor: 
* @condition: {"status":"yes","run_if":"assertive","items":[[{"source":["page","page_ids"],"operator":"in","value":["6","1958"]}],[{"source":["page","post_type"],"operator":"in","value":["post","location_service","bricks_template","landing-pages","location-service"]}]]}
*/
?>
<?php if (!defined("ABSPATH")) { return;} // <Internal Doc End> ?>
<?php
// Defer only the timepicker script (keep jQuery as-is)
add_filter('script_loader_tag', function($tag, $handle, $src) {
    if ($handle === 'clock-timepicker') {
        return '<script src="' . esc_url($src) . '" defer></script>';
    }
    return $tag;
}, 10, 3);

// Enqueue the timepicker
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script(
        'clock-timepicker',
        get_stylesheet_directory_uri() . '/assets/js/jquery-clock-timepicker.min.js',
        ['jquery'],
        null,
        true
    );
});

// Init timepickers + AM/PM label as a sibling (no value mutations, no wrapping)
add_action('wp_footer', function () { ?>
<style>
/* Visual-only AM/PM badge — sibling of the input, cannot steal events */
.wsb-ampm-label {
  position: absolute;
  right: 10px;
  top: 22px;
  transform: translateY(-50%);
  pointer-events: none;
  font-size: .9em;
  color: #666;
  opacity: 0;                 /* hidden until we have a valid time */
  transition: opacity .12s linear;
}
</style>
<script>
jQuery(function($){
  // === selectors (keep your originals) ===
  const BASE = "input[name='pickup_time'], input[placeholder='Select time']";
  const CHARTER_PICKUP  = "input[name='charter_pickup_time']";
  const CHARTER_DROPOFF = "input[name='charter_drop-off_time']";
  const ALL = BASE + ', ' + CHARTER_PICKUP + ', ' + CHARTER_DROPOFF;

  // === 1) Initialize the pickers exactly like your working original ===
  if ($.fn.clockTimePicker) {
    $(BASE).clockTimePicker({
      alwaysSelectHoursFirst: true,
      duration: false,          // your original setting (keeps hour→minute behavior)
      precision: 5,
      i18n: { cancelButton: 'Cancel', okButton: 'Done' },
      colors: { popupHeaderBackgroundColor: '#ff0000', selectorColor: '#ff0000' }
    });

    [
      { selector: CHARTER_PICKUP,  defaultTime: "08:00", minimum: "07:00", maximum: "19:00" },
      { selector: CHARTER_DROPOFF, defaultTime: "17:00", minimum: "11:00", maximum: "23:00" }
    ].forEach(cfg => {
      const $el = $(cfg.selector);
      if (!$el.length) return;
      $el.clockTimePicker({
        alwaysSelectHoursFirst: true,
        duration: false,
        // minimum: cfg.minimum,
        // maximum: cfg.maximum,
        precision: 5,
        i18n: { cancelButton: 'Cancel', okButton: 'Done' },
        colors: { popupHeaderBackgroundColor: '#ff0000', selectorColor: '#ff0000' }
      });
      $el.val(cfg.defaultTime);
    });
  }

  // Default for BASE: tomorrow @ current time (your code)
  const tmr = new Date();
  tmr.setDate(tmr.getDate() + 1);
  const hh = String(tmr.getHours()).padStart(2,'0');
  const mm = String(tmr.getMinutes()).padStart(2,'0');
  $(BASE).val(hh + ':' + mm);

  // === 2) AM/PM label — sibling element, no wrapping, no value changes ===
  function getSuffix(v){
    if (!v || !/^\d{1,2}:\d{2}$/.test(v)) return '';
    const h = parseInt(v.split(':')[0], 10);
    return (h >= 12) ? 'PM' : 'AM';
  }
  function ensureLabel($input){
    // Make the parent positioned so the absolute label anchors correctly
    const $parent = $input.parent();
    if ($parent.css('position') === 'static') $parent.css('position','relative');

    // If label already exists, just update it
    let $label = $input.siblings('.wsb-ampm-label');
    if (!$label.length) {
      $label = $('<span class="wsb-ampm-label" aria-hidden="true"></span>');
      $input.after($label); // sibling after input (no wrapping)
      // Add a little right padding so text doesn't sit under the label
      const pr = parseInt($input.css('padding-right')) || 0;
      $input.css('padding-right', (pr + 42) + 'px');
    }
    updateLabel($input, $label);
  }
  function updateLabel($input, $label){
    const sfx = getSuffix($input.val());
    if (!$label || !$label.length) $label = $input.siblings('.wsb-ampm-label');
    $label.text(sfx).css('opacity', sfx ? 0.75 : 0);
  }

  // Init labels
  $(ALL).each(function(){ ensureLabel($(this)); });

  // Update label when the picker updates the value (or when user types)
  $(document).on('input change', ALL, function(){
    updateLabel($(this));
  });

  // Nothing on focus/blur; no wrapping; no touching the input value.
});
</script>
<?php });
