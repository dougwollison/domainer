<?php
/**
 * Domainer Settings Helper
 *
 * @package Domainer
 * @subpackage Helpers
 *
 * @since 1.0.0
 */

namespace Domainer;

/**
 * The Settings Kit
 *
 * Internal-use utility kit for printing out
 * the option fields for the Manager.
 *
 * @internal Used by the Manager.
 *
 * @since 1.0.0
 */
final class Settings {
	/**
	 * Add the desired settings field.
	 *
	 * Prefixes option name with "domainer_"
	 * and the page name with "domainer-".
	 *
	 * @since 1.0.0
	 *
	 * @uses Settings::build_field() as the callback for the field.
	 *
	 * @param string $field   The name of the field.
	 * @param array  $options The options for the field.
	 * 		@option string "title" The title of the field.
	 * 		@option string "label" Additional label for the input.
	 *		@option string "help"  The help text for the field.
	 *		@option string "type"  The type of field to print out.
	 *		@option string "args"  Special arguments for the field callback.
	 * @param string $page    The name of the page to display on.
	 * @param string $section Optional. The name of the section to display in.
	 */
	public static function add_field( $field, $options, $page, $section = 'default' ) {
		// Parse the options
		$options = wp_parse_args( $options, array(
			'title' => '',
			'label' => '',
			'help'  => '',
			'type'  => '',
			'data'  => array()
		) );

		// Handle prefixing the name with domainer_options
		if ( preg_match( '/([^\[]+)(\[.+\])/', $field, $matches ) ) {
			$id = "domainer_" . trim( preg_replace( '/[\[\]]+/', '_', $field ), '_' );
			$name = "domainer_{$page}[{$matches[1]}]{$matches[2]}";
		} else {
			$id = "domainer_{$field}";
			$name = "domainer_{$page}[{$field}]";
		}

		// Build the callback arguments
		$class = sanitize_key( $field );
		$args = array(
			'class'     => "domainer-settings-field domainer-settings-{$page}-field domainer_{$class}-field",
			'option'    => $field,
			'id'        => $id,
			'name'      => $name,
			'label'     => $options['label'],
			'help'      => $options['help'],
			'type'      => $options['type'],
			'data'      => $options['data'],
			'context'   => $page,
		);

		// Add label_for arg if appropriate
		if ( ! in_array( $options['type'], array( 'radiolist', 'checklist', 'checkbox', 'sync_settings' ) ) ) {
			$args['label_for'] = $args['id'];
		}

		// Add the settings field
		add_settings_field(
			"domainer_{$field}", // id
			$options['title'], // title
			array( __CLASS__, 'build_field' ), // callback
			"domainer-{$page}", // page
			$section, // section
			$args // arguments
		);
	}

	/**
	 * Add multiple settings fields.
	 *
	 * @since 1.0.0
	 *
	 * @see Settings::build_field() for how the fields are built.
	 *
	 * @param array  $fields  The fields to add.
	 * @param string $page    The name of the page to display on.
	 * @param string $section Optional. The name of the section to display in.
	 */
	public static function add_fields( $fields, $page, $section = 'default' ) {
		foreach ( $fields as $field => $options ) {
			self::add_field( $field, $options, $page, $section );
		}
	}

	/**
	 * Given an array, extract the disired value defined like so: myvar[mykey][0].
	 *
	 * @since 1.0.0
	 *
	 * @uses Settings::extract_value() to handle any array map stuff.
	 *
	 * @param array        $array The array to extract from.
	 * @param array|string $map   The map to follow, in myvar[mykey] or [myvar, mykey] form.
	 *
	 * @return mixed The extracted value.
	 */
	private static function extract_value( array $array, $map ) {
		// Abort if not an array
		if ( ! is_array( $array ) ) return $array;

		// If $map is a string, turn it into an array
		if ( ! is_array( $map ) ) {
			$map = trim( $map, ']' ); // Get rid of last ] so we don't have an empty value at the end
			$map = preg_split( '/[\[\]]+/', $map );
		}

		// Extract the first key to look for
		$key = array_shift( $map );

		// See if it exists
		if ( isset( $array[ $key ] ) ) {
			// See if we need to go deeper
			if ( $map ) {
				return self::extract_value( $array[ $key ], $map );
			}

			return $array[ $key ];
		}

		// Nothing found.
		return null;
	}

	/**
	 * Retrieve the settings value.
	 *
	 * Handles names like option[suboption][] appropraitely.
	 *
	 * @since 1.0.0
	 *
	 * @uses Settings::extract_value() to get the value out of the array based on the map.
	 *
	 * @param string $name    The name of the setting to retrieve.
	 * @param string $context The context of the setting (how to retrieve it).
	 */
	private static function get_value( $name, $context = 'options' ) {
		if ( preg_match( '/([\w-]+)\[([\w-]+)\](.*)/', $name, $matches ) ) {
			// Field is an array map, get the actual key...
			$name = $matches[1];
			// ... and the map to use.
			$map = $matches[2] . $matches[3];
		}

		// Get the value
		if ( $context == 'domain' ) {
			$domain = Registry::get_domain( $_REQUEST['domain_id'] ) ?: new Domain();
			$value = $domain->$name;
		} else {
			$value = Registry::get( $name );
		}

		// Process the value via the map if necessary
		if ( ! empty( $map ) ) {
			$value = self::extract_value( $value, $map );
		}

		return $value;
	}

	/**
	 * Build a field.
	 *
	 * Calls appropriate build_%type%_field method,
	 * along with printing out help text.
	 *
	 * @since 1.0.0
	 *
	 * @uses Settings::get_value() to retrieve a value for the field.
	 *
	 * @param array $args The arguments for the field.
	 *		@option string "name" The field name/ID.
	 *		@option string "type" The field type.
	 *		@option mixed  "data" Optional. data for the field.
	 * 		@option string "help" Optional. Help text.
	 * @param mixed $value Optional. A specifi value to use
	 *                     instead of dynamically retrieving it.
	 */
	public static function build_field( $args, $value = null ) {
		// Get the value for the field if not provided
		if ( is_null( $value ) ) {
			$value = self::get_value( $args['option'], $args['context'] );
		}

		switch ( $args['type'] ) {
			// Not actually fields
			case 'notice':
				$method = "print_notice";
				$cb_args = array( $args['data'] );
				break;

			// Special fields
			case 'select':
			case 'radiolist':
			case 'checklist':
				$method = "build_{$args['type']}_field";
				if ( $args['type'] == 'select' ) {
					$cb_args = array( $args['name'], $args['id'], $value, $args['data'] );
				} else {
					$cb_args = array( $args['name'], $value, $args['data'] );
				}
				break;

			// Regular fields
			default:
				$method = "build_input_field";
				$cb_args = array( $args['name'], $args['id'], $value, $args['type'], $args['data'] );
		}

		$html = call_user_func_array( array( __CLASS__, $method ), $cb_args );

		if ( $args['help'] ) {
			// Wrap $html in lable with help text if checkbox or radio
			if ( $args['type'] == 'checkbox' || $args['type'] == 'radio' ) {
				$html = sprintf( '<label>%s %s</label>', $html, $args['help'] );
			} else {
				// Append as description paragraph otherwise
				$html .= sprintf( '<p class="description">%s</p>', $args['help'] );
			}
		}

		// Print the output
		echo $html;
	}

	/**
	 * Build a basic <input> field.
	 *
	 * Also handles <textarea> fields.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name       The name of the field.
	 * @param string $id         The ID of the field.
	 * @param mixed  $value      The value of the field.
	 * @param string $label      Optional. The label for the input.
	 * @param array  $attributes Optional. Custom attributes for the field.
	 *
	 * @return string The HTML of the field.
	 */
	private static function build_input_field( $name, $id, $value, $type, $attributes = array() ) {
		$html = '';

		// Ensure $attributes is an array
		$attributes = (array) $attributes;

		// If a checkbox, include a default=0 dummy field
		if ( $type == 'checkbox' ) {
			$html .= sprintf( '<input type="hidden" name="%s" value="0" />', $name );

			// Also add the checked attribute if $value is true-ish
			if ( $value ) {
				$attributes['checked'] = true;
			}

			// Replace $value with the TRUE (1)
			$value = 1;
		}

		// Build the $attributes list if needed
		$atts = '';
		foreach ( $attributes as $key => $val ) {
			if ( is_bool( $val ) ) {
				$atts .= $val ? " {$key}" : '';
			} else {
				$atts .= " {$key}=\"{$val}\"";
			}
		}

		// Build the input
		if ( $type == 'textarea' ) {
			// or <textarea> as the case may be.
			$html .= sprintf( '<textarea name="%s" id="%s"%s>%s</textarea>', $name, $id, $atts, $value );
		} else {
			$value = esc_attr( $value );
			$html .= sprintf( '<input type="%s" name="%s" id="%s" value="%s"%s />', $type, $name, $id, $value, $atts );
		}

		return $html;
	}

	/**
	 * Build a <select> field.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name       The name of the field.
	 * @param string $id         The ID of the field.
	 * @param mixed  $value   The value of the field.
	 * @param array  $options The options for the field.
	 */
	private static function build_select_field( $name, $id, $value, $options ) {
		$html = '';

		$html .= sprintf( '<select name="%s" id="%s">', $name, $id );
		foreach ( $options as $val => $label ) {
			$selected = $val == $value ? ' selected' : '';
			$html .= sprintf( '<option value="%s"%s>%s</option>', $val, $selected, $label );
		}
		$html .= '</select>';

		return $html;
	}

	/**
	 * Build a list of inputs.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type    The input type.
	 * @param string $name    The name of the field.
	 * @param mixed  $value   The value of the field.
	 * @param array  $options The options for the field.
	 */
	private static function build_inputlist_field( $type, $name, $value, $options ) {
		// Ensure $value is an array
		$value = (array) $value;

		// Checkbox field support array value
		$field_name = $name;
		if ( $type == 'checkbox' ) {
			$field_name .= '[]';
		}

		$inputs = array();
		foreach ( $options as $val => $label ) {
			$checked = in_array( $val, $value ) ? ' checked' : '';
			$inputs[] = sprintf( '<label><input type="%s" name="%s" value="%s"%s /> %s</label>', $type, $field_name, $val, $checked, $label );
		}

		// Build the list, including a fallback "none" input
		$html = '<fieldset class="nl-inputlist">' .
			sprintf( '<input type="hidden" name="%s" value="" />', $name ) .
			implode( '<br /> ', $inputs ) .
		'</fieldset>';

		return $html;
	}

	/**
	 * Build a list of radio inputs.
	 *
	 * @see Settings::build_inputlist_field() for what it all does.
	 */
	private static function build_radiolist_field( $name, $value, $options ) {
		return self::build_inputlist_field( 'radio', $name, $value, $options );
	}

	/**
	 * Build a list of checkbox inputs.
	 *
	 * @see Settings::build_input_list() for what it all does.
	 */
	private static function build_checklist_field( $name, $value, $options ) {
		return self::build_inputlist_field( 'checkbox', $name, $value, $options );
	}

	/**
	 *
	 *
	 * @since 1.0.0
	 *
	 * @param string $name  The name of the field.
	 * @param mixed  $value The value of the field.
	 * @param string $text  The notice text.
	 */
	private static function print_notice( $text ) {
		printf( '<p><span class="nl-settings-notice">%s</span></p>', $text );
	}
}
