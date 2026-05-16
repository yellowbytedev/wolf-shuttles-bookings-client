<?php
// <Internal Doc Start>
/*
*
* @description: 
* @tags: 
* @group: 
* @name: Force display style on review sliders
* @type: js
* @status: draft
* @created_by: 1
* @created_at: 2026-01-18 12:09:05
* @updated_at: 2026-01-18 12:13:36
* @is_valid: 1
* @updated_by: 1
* @priority: 10
* @run_at: wp_footer
* @load_as_file: 
* @condition: {"status":"no","run_if":"assertive","items":[[{"source":["page","post_type"],"operator":"in","value":["location-service","service","bricks_template"]}],[{"source":["page","page_type"],"operator":"in","value":[]}]]}
*/
?>
<?php if (!defined("ABSPATH")) { return;} // <Internal Doc End> ?>
document.addEventListener('DOMContentLoaded', () => {
    console.log('eyy');
  document
    .querySelectorAll('.splide__arrows')
    .forEach(el => el.style.setProperty('display', 'flex', 'important'));
});