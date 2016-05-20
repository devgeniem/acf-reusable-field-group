<?php

/*
Plugin Name: Advanced Custom Fields: Reusable Field Group
Plugin URI: https://github.com/devgeniem/acf-reusable-field-group
Description: Include an existing ACF Field Group in the template for another Field Group
Version: 1.1.0
Author: Miika Arponen / Geniem
Author URI: https://github.com/devgeniem
Original Author: Tyler Bruffy
Original Author URI: https://github.com/tybruffy/
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

// Let's set the text domain
load_plugin_textdomain( 'acf-reusable_field_group', false, dirname( plugin_basename(__FILE__) ) . '/lang/' ); 

// For now this fork only supports version 5, use the original version with ACF 4.
function include_field_types_reusable_field_group( $version ) {
    include_once('acf-reusable_field_group-v5.php');    
}

add_action('acf/include_field_types', 'include_field_types_reusable_field_group');  
    
?>