<?php
// <Internal Doc Start>
/*
*
* @description: 
* @tags: 
* @group: 
* @name: Custom Date Picker on Gravity Form
* @type: js
* @status: draft
* @created_by: 
* @created_at: 
* @updated_at: 2025-10-29 05:57:50
* @is_valid: 
* @updated_by: 
* @priority: 11
* @run_at: wp_footer
* @load_as_file: 
* @condition: {"status":"yes","run_if":"assertive","items":[[{"source":["page","page_ids"],"operator":"in","value":["6","1958"]}],[{"source":["page","post_type"],"operator":"in","value":["post"]}]]}
*/
?>
<?php if (!defined("ABSPATH")) { return;} // <Internal Doc End> ?>
document.addEventListener('DOMContentLoaded', () => {
const gfDatePickers = document.querySelectorAll('input[placeholder="Select Date"], input[name="pickup_date"], input[name="charter_pickup_date"]');

    if (typeof jQuery === 'undefined' || typeof jQuery.datepicker === 'undefined') {
        console.error('jQuery or jQuery UI Datepicker is not available.');
        return;
    }

    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);

     const today = new Date();
  today.setHours(0,0,0,0);


    // Map each blocked date to a reason (dd/mm/yy)
    const BLOCKED_DATES = new Map([
      ["07/06/2025", "Holiday – no service"],
      ["19/10/2025", "Fully booked"],
    ]);
    
    gfDatePickers.forEach(datePicker => {
      if (!datePicker.classList.contains('initialized')) {
        jQuery(datePicker).datepicker({
          minDate: 0,
          defaultDate: tomorrow,
          dateFormat: "dd/mm/yy",
          beforeShowDay: function(date) {
            const formattedDate = jQuery.datepicker.formatDate("dd/mm/yy", date);
            const reason = BLOCKED_DATES.get(formattedDate);
            if (reason) {
              // [selectable?, extraClass, tooltipText]
              return [false, "wsb-blocked", reason];
            }
            return [true, "", ""];
          }
        });
    
        datePicker.value = jQuery.datepicker.formatDate("dd/mm/yy", tomorrow);
        datePicker.classList.add('initialized');
        jQuery(datePicker).attr('readonly', 'readonly');
      }
    });


});
