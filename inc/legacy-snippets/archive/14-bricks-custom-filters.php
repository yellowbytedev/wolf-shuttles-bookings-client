<?php
// <Internal Doc Start>
/*
*
* @description: 
* @tags: 
* @group: 
* @name: Bricks Custom Filters
* @type: PHP
* @status: draft
* @created_by: 
* @created_at: 
* @updated_at: 2025-02-26 07:03:23
* @is_valid: 
* @updated_by: 
* @priority: 10
* @run_at: all
* @load_as_file: 
* @condition: {"status":"no","run_if":"assertive","items":[[{"source":["page","page_ids"],"operator":"in","value":[]}]]}
*/
?>
<?php if (!defined("ABSPATH")) { return;} // <Internal Doc End> ?>
<?php
// add_filter( 'bricks/element/set_root_attributes', function( $attributes, $element ) {
//     if ( isset( $element->settings['name'] ) && 'pickup_time' === $element->settings['name'] ) {
//         $attributes['class'] .= ' time-picker'; // Add the class to the element
//     }
//     return $attributes;
// }, 10, 2 );

// add_filter( 'bricks/element/form/datepicker_options', function( $options, $element ) {
//     $tomorrow = new DateTime();
//     $tomorrow->modify('+1 day');

//     error_log(' $options: ' . print_r( $options));
    
//     $options['locale'] = [
//         'firstDayOfWeek' => 1, // Set Monday as the first week day
//         'minDate' => 1,
//         'defaultDate' => $tomorrow,
//         'dateFormat' => "dd/mm/yy"
//     ];

//     return $options;
// }, 10, 2 );