<?php

if( ! class_exists('acf_field_reusable_field_group') ) :

class acf_field_reusable_field_group extends acf_field {

    private $included;

    /*
    *  __construct
    *
    *  This function will setup the field type data
    *
    *  @type    function
    *  @date    5/03/2014
    *  @since   5.0.0
    *
    *  @param   n/a
    *  @return  n/a
    */

    function __construct() {
        /*
        *  name (string) Single word, no spaces. Underscores allowed
        */

        $this->name = 'reusable_field_group';


        /*
        *  label (string) Multiple words, can include spaces, visible when selecting a field type
        */

        $this->label = __('Reusable Field Group', 'acf-reusable_field_group');


        /*
        *  category (string) basic | content | choice | relational | jquery | layout | CUSTOM GROUP NAME
        */

        $this->category = 'relational';


        /*
        *  defaults (array) Array of default settings which are merged into the field object. These are used later in settings
        */

        $this->defaults = array(
            'group_key' => 0,
        );

        /*
        *  a fake boolean (uses strings for easeness) for knowing if we are included somewhere or not
        */
        
        $this->included = "false";

        /*
        *  l10n (array) Array of strings that are used in JavaScript. This allows JS strings to be translated in PHP and loaded via:
        *  var message = acf._e('reusable_field_group', 'error');
        */

        $this->l10n = array(
            'error' => __('Error! Please select a field group', 'acf-reusable_field_group'),
        );

        add_filter("acf/get_field_types", array($this, 'get_field_types'), 10, 1);
        add_filter("acf/prepare_field_for_export", array($this, 'prepare_fields_for_export'), 10, 1);

        /*
        *  Functions to create a location rule for included field groups
        */
        add_filter('acf/location/rule_types', array( $this, 'acf_location_rule_types_included_field_group'), 10, 1 );
        add_filter('acf/location/rule_values/included', array( $this, 'acf_location_rule_values_included_field_group'), 10, 1 );
        add_filter('acf/location/rule_match/included', array( $this, 'acf_location_rule_match_included_field_group'), 10, 3 );


        // do not delete!
        parent::__construct();

    }


    /*
    *  render_field_settings()
    *
    *  Create extra settings for your field. These are visible when editing a field
    *
    *  @type    action
    *  @since   3.6
    *  @date    23/01/13
    *
    *  @param   $field (array) the $field being edited
    *  @return  n/a
    */

    function render_field_settings( $field ) {
        acf_render_field_setting( $field, array(
            'label'         => __('Field Group','acf'),
            'instructions'  => '',
            'type'          => 'select',
            'name'          => 'group_key',
            'choices'       => $this->pretty_field_groups($field),
            'multiple'      => 0,
            'ui'            => 1,
            'allow_null'    => 0,
            'placeholder'   => __("No Field Group",'acf'),
        ));
    }


    function pretty_field_groups($field) {
        global $post;

        $groups = acf_get_field_groups();
        $r      = array();

        $current_id = is_object( $post ) ? $post->ID : $_POST['parent'];
        $current_group = _acf_get_field_group_by_id($current_id);

        foreach( $groups as $group ) {
            $key = $group["key"];

            // don't show the current field group.
            if ($key == $current_group["key"]) {
                continue;
            }

            $r[$key] = $group["title"];
        }

        return $r;
    }


    function prepare_fields_for_export( $field ) {
        if ($field['type'] == $this->name) {
            $field_object = acf_get_field( $field["key"] );

            $field["group_key"] = $field_object["group_key"];

            unset($field['sub_fields']);
        }

        return $field;
    }


    /*
    *  render_field()
    *
    *  Create the HTML interface for your field
    *
    *  @param   $field (array) the $field being rendered
    *
    *  @type    action
    *  @since   3.6
    *  @date    23/01/13
    *
    *  @param   $field (array) the $field being edited
    *  @return  n/a
    */

    function render_field( $field ) {
        global $post, $self;

        if (is_object($post)) {
            $current_id = $post->ID;
        } elseif ($self === "profile.php") {
            $current_id = "user_" . $_GET["user_id"];
        } elseif ($self === "comment.php") {
            $current_id = "comment_" . $_GET["c"];
        } else {
            $current_id = "options";
        }

        $name_prefix = '';

        // Geniem addition: get field group to get the visibility settings

        $field_groups = acf_get_field_groups();

        foreach ( $field_groups as $group ) {
            if ( $group["key"] == $field["group_key"] ) {
                $contents = $group;
                break;
            }
        }

        $contents["active"] = true;

        // Geniem addition: set a variable that we are including the field group
        // this variable will be checked in the visibility condition check
        $this->included = "true";

        // Geniem addition: check the visibility rules and return false if not visible
        if ( ! acf_get_field_group_visibility( $contents ) ) {
            return false;
        }

        if (isset($field['parent'])) {
            preg_match_all('/\[(field_\w+)\](\[(\d+)\])?/', $field['prefix'], $parent_fields);


            if (isset($parent_fields[0])) {
                foreach ($parent_fields[0] as $parent_field_index => $parent_field) {
                    $field_name = $parent_fields[1][$parent_field_index];
                    $index = $parent_fields[3][$parent_field_index];
                    $parent_field_object = acf_get_field($field_name);
                    $parent_prefix = $parent_field_object['name'];

                    if ($index !== '') {
                        $parent_prefix .= '_' . $index;
                    }

                    $name_prefix .= $parent_prefix . '_';
                }
            }

            $name_prefix .= $field['_name'] . '_';
        }

        foreach ( $field['sub_fields'] as $sub_field ) {
            $sub_name_prefix = $name_prefix;
            $sub_field_name = $sub_field['name'];

            // update prefix to allow for nested values
            $sub_field['prefix'] = $field["name"];

            $sub_field['name'] = "{$name_prefix}{$sub_field_name}";

            // load value
            if( $sub_field['value'] === null ) {
                $sub_field['value'] = acf_get_value( $current_id, $sub_field );
            }

            // render input
            acf_render_field_wrap( $sub_field );
        }
    }

    /*
    *  field_group_admin_head()
    *
    *  This action is called in the admin_head action on the edit screen where your field is edited.
    *  Use this action to add CSS and JavaScript to assist your render_field_options() action.
    *
    *  @type    action (admin_head)
    *  @since   3.6
    *  @date    23/01/13
    *
    *  @param   n/a
    *  @return  n/a
    */



    function field_group_admin_head() {
        ?>
        <style type="text/css">

        .acf-field-list .acf-field-object-reusable-field-group tr[data-name="instructions"],
        .acf-field-list .acf-field-object-reusable-field-group tr[data-name="required"],
        .acf-field-list .acf-field-object-reusable-field-group tr[data-name="warning"],
        .acf-field-list .acf-field-object-reusable-field_group tr[data-name="instructions"],
        .acf-field-list .acf-field-object-reusable-field_group tr[data-name="required"],
        .acf-field-list .acf-field-object-reusable-field_group tr[data-name="warning"] {
            display: none !important;
        }

        </style>
            <?php
    }




    /*
    *  load_value()
    *
    *  This filter is applied to the $value after it is loaded from the db
    *
    *  @type    filter
    *  @since   3.6
    *  @date    23/01/13
    *
    *  @param   $value (mixed) the value found in the database
    *  @param   $post_id (mixed) the $post_id from which the value was loaded
    *  @param   $field (array) the field array holding all the field options
    *  @return  $value
    */



    function load_value( $value, $post_id, $field ) {
        $fields = array();

        foreach( $field['sub_fields'] as $sub_field ) {

            $sub_field_name = $sub_field['name'];

            // update prefix to allow for nested values
            $sub_field['prefix'] = $field["name"];

            $sub_field['name'] = "{$field['name']}_{$sub_field_name}";

            // load value
            if( $sub_field['value'] === null ) {
                $sub_field['value'] = acf_format_value(acf_get_value( $post_id, $sub_field ), $post_id, $sub_field);
            }

            $fields[$sub_field_name] = $sub_field['value'];
        }

        return $fields;

    }




    /*
    *  update_value()
    *
    *  This filter is applied to the $value before it is saved in the db
    *
    *  @type    filter
    *  @since   3.6
    *  @date    23/01/13
    *
    *  @param   $value (mixed) the value found in the database
    *  @param   $post_id (mixed) the $post_id from which the value was loaded
    *  @param   $field (array) the field array holding all the field options
    *  @return  $value
    */



    function update_value( $value, $post_id, $field ) {
        if (! empty($value)) {
            foreach ($value as $field_key => $field_value) {
                foreach ( $field['sub_fields'] as $sub_field ) {
                    if ($field_key == $sub_field['key']) {
                        // update field
                        $sub_field_name = $sub_field['name'];

                        $sub_field['name'] = "{$field['name']}_{$sub_field_name}";

                        acf_update_value( $field_value, $post_id, $sub_field );

                        break;
                    }
                }
            }
        }

        return null;
    }

    /*
    *  load_field()
    *
    *  This filter is applied to the $field after it is loaded from the database
    *
    *  @type    filter
    *  @date    23/01/2013
    *  @since   3.6.0
    *
    *  @param   $field (array) the field array holding all the field options
    *  @return  $field
    */

    function load_field( $field ) {

        $group  = _acf_get_field_group_by_key($field["group_key"]);
        $fields = acf_get_fields($group);
        $field['sub_fields'] = $fields;

        return $field;

    }

    /*
    *  update_field()
    *
    *  This filter is applied to the $field before it is saved to the database
    *
    *  @type    filter
    *  @date    23/01/2013
    *  @since   3.6.0
    *
    *  @param   $field (array) the field array holding all the field options
    *  @return  $field
    */



    function update_field( $field ) {

        // remove sub fields
        unset($field['sub_fields']);

        return $field;

    }

    /*
    *  delete_field()
    *
    *  This action is fired after a field is deleted from the database
    *
    *  @type    action
    *  @date    11/02/2014
    *  @since   5.0.0
    *
    *  @param   $field (array) the field array holding all the field options
    *  @return  n/a
    */

    function delete_field( $field ) {

        // loop through sub fields
        if( !empty($field['sub_fields']) ) {

            foreach( $field['sub_fields'] as $sub_field ) {

                acf_delete_field( $sub_field['ID'] );

            }

        }

    }

    /*
    *  acf_location_rule_types_included_field_group()
    *
    *  This action creates a new location rule for included field groups
    *  Function added by Geniem
    *
    *  @type    action
    *  @date    23/09/2015
    *  @since   5.0.0
    *
    *  @param   $choices (array) the array holding the location rules
    *  @return  n/a
    */

    function acf_location_rule_types_included_field_group( $choices ) {
        $choices['Other']['included'] = __('Included field', 'acf-reusable_field_group');

        return $choices;
    }

    /*
    *  acf_location_rule_values_included_field_group()
    *
    *  This action adds the choices to location rule menu
    *  Function added by Geniem
    *
    *  @type    action
    *  @date    23/09/2015
    *  @since   5.0.0
    *
    *  @param   $choices (array) the array holding the choices
    *  @return  n/a
    */

    function acf_location_rule_values_included_field_group( $choices ) {
        $choices["true"] = __("True", "acf-reusable_field_group");
        $choices["false"] = __("False", "acf-reusable_field_group");

        return $choices;
    }

    /*
    *  acf_location_rule_match_included_field_group()
    *
    *  This action checks if the location rule matches to current field groups
    *  Function added by Geniem
    *
    *  @type    action
    *  @date    23/09/2015
    *  @since   5.0.0
    *
    *  @param   $choices (array) the array holding the choices
    *  @return  n/a
    */    

    function acf_location_rule_match_included_field_group( $match, $rule, $options ) {
        global $group;

        $included = $this->included;

        if ( $rule["value"] == $included ) {
            $this->included = "false";
            $return = true;
        }
        else {
            $this->included = "false";
            $return = false;
        }

        return $return;
    }


}


// create field
new acf_field_reusable_field_group();

endif;