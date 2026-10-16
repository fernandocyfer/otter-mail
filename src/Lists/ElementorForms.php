<?php
/**
 * Read Elementor Pro form submissions into newsletter lists.
 *
 * @package OtterMail
 */

namespace OtterMail\Lists;

defined( 'ABSPATH' ) || exit;

class ElementorForms {

	/**
	 * Submissions tables exist (Elementor Pro Collect Submissions).
	 *
	 * @return bool
	 */
	public static function available() {
		global $wpdb;

		$table = $wpdb->prefix . 'e_submissions';
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );

		return $found === $table;
	}

	/**
	 * Distinct forms from submissions and Elementor documents.
	 *
	 * @return array
	 */
	public static function forms() {
		$forms = array();

		foreach ( self::forms_from_submissions() as $form ) {
			$forms[ $form['key'] ] = $form;
		}
		foreach ( self::forms_from_documents() as $form ) {
			if ( isset( $forms[ $form['key'] ] ) ) {
				continue;
			}
			$forms[ $form['key'] ] = $form;
		}

		return array_values( $forms );
	}

	/**
	 * @return array
	 */
	private static function forms_from_submissions() {
		global $wpdb;

		if ( ! self::available() ) {
			return array();
		}

		$table = $wpdb->prefix . 'e_submissions';
		$rows  = $wpdb->get_results(
			"SELECT post_id, element_id, MAX(form_name) AS form_name, COUNT(*) AS submissions
			FROM {$table}
			WHERE status != 'trash'
			GROUP BY post_id, element_id
			ORDER BY form_name ASC, post_id DESC" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		$forms = array();
		foreach ( (array) $rows as $row ) {
			$forms[] = self::format_form(
				(int) $row->post_id,
				(string) $row->element_id,
				$row->form_name ? (string) $row->form_name : __( 'Formulário', 'ottermail' ),
				(int) $row->submissions
			);
		}

		return $forms;
	}

	/**
	 * Scan published Elementor documents for form widgets.
	 *
	 * @return array
	 */
	private static function forms_from_documents() {
		$ids = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 80,
				'meta_key'       => '_elementor_data', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		$forms = array();
		foreach ( (array) $ids as $post_id ) {
			$raw = get_post_meta( $post_id, '_elementor_data', true );
			if ( ! $raw ) {
				continue;
			}
			$elements = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
			$forms    = array_merge( $forms, self::find_forms_in_elements( $elements, (int) $post_id ) );
		}

		return $forms;
	}

	/**
	 * @param mixed $elements Elements tree.
	 * @param int   $post_id  Post ID.
	 * @return array
	 */
	private static function find_forms_in_elements( $elements, $post_id ) {
		$out = array();
		if ( ! is_array( $elements ) ) {
			return $out;
		}

		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			if ( isset( $element['widgetType'] ) && 'form' === $element['widgetType'] && ! empty( $element['id'] ) ) {
				$name  = '';
				if ( isset( $element['settings']['form_name'] ) ) {
					$name = (string) $element['settings']['form_name'];
				}
				$out[] = self::format_form( $post_id, (string) $element['id'], $name ? $name : __( 'Formulário', 'ottermail' ), 0 );
			}
			if ( ! empty( $element['elements'] ) ) {
				$out = array_merge( $out, self::find_forms_in_elements( $element['elements'], $post_id ) );
			}
		}

		return $out;
	}

	/**
	 * @param int    $post_id     Post ID.
	 * @param string $element_id  Widget ID.
	 * @param string $form_name   Form name.
	 * @param int    $submissions Count.
	 * @return array
	 */
	private static function format_form( $post_id, $element_id, $form_name, $submissions ) {
		$title = $post_id ? get_the_title( $post_id ) : '';
		$label = $form_name;
		if ( $title ) {
			$label .= ' — ' . $title;
		}
		if ( $submissions ) {
			$label .= ' (' . number_format_i18n( $submissions ) . ')';
		}

		return array(
			'key'         => $post_id . '|' . $element_id,
			'post_id'     => $post_id,
			'element_id'  => $element_id,
			'form_name'   => $form_name,
			'submissions' => $submissions,
			'label'       => $label,
		);
	}

	/**
	 * Import existing submissions into a list.
	 *
	 * @param ContactList $list List.
	 * @return int Inserted contacts.
	 */
	public static function sync( ContactList $list ) {
		if ( ! $list->elementor_form_id || ! self::available() ) {
			return 0;
		}

		$contacts = self::extract_contacts( $list->elementor_post_id, $list->elementor_form_id );
		$repo     = new ContactListRepository();
		$added    = 0;

		foreach ( $contacts as $row ) {
			if ( $repo->add_contact( $list->id, $row['email'], $row['first_name'], $row['last_name'], 'elementor' ) ) {
				++$added;
			}
		}

		return $added;
	}

	/**
	 * Pull email/name pairs from a form's submissions.
	 *
	 * @param int    $post_id    Post ID (0 = match any post with this widget id).
	 * @param string $element_id Widget ID.
	 * @return array
	 */
	public static function extract_contacts( $post_id, $element_id ) {
		global $wpdb;

		if ( ! self::available() ) {
			return array();
		}

		$submissions = $wpdb->prefix . 'e_submissions';
		$values      = $wpdb->prefix . 'e_submissions_values';
		$element_id  = sanitize_text_field( $element_id );

		if ( $post_id ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT s.id, v.`key`, v.value
					FROM {$submissions} s
					INNER JOIN {$values} v ON v.submission_id = s.id
					WHERE s.element_id = %s AND s.post_id = %d AND s.status != 'trash'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$element_id,
					absint( $post_id )
				)
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT s.id, v.`key`, v.value
					FROM {$submissions} s
					INNER JOIN {$values} v ON v.submission_id = s.id
					WHERE s.element_id = %s AND s.status != 'trash'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$element_id
				)
			);
		}

		$grouped = array();
		foreach ( (array) $rows as $row ) {
			$sid = (int) $row->id;
			if ( ! isset( $grouped[ $sid ] ) ) {
				$grouped[ $sid ] = array();
			}
			$grouped[ $sid ][ (string) $row->key ] = (string) $row->value;
		}

		$out = array();
		foreach ( $grouped as $fields ) {
			$parsed = self::parse_fields( $fields );
			if ( $parsed ) {
				$out[] = $parsed;
			}
		}

		return $out;
	}

	/**
	 * Parse a live Elementor form record into a contact.
	 *
	 * @param object $record Form_Record.
	 * @return array|null
	 */
	public static function from_record( $record ) {
		if ( ! is_object( $record ) || ! method_exists( $record, 'get' ) ) {
			return null;
		}

		$fields = $record->get( 'fields' );
		$map    = array();

		if ( is_array( $fields ) ) {
			foreach ( $fields as $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}
				$key   = '';
				$value = isset( $field['value'] ) ? (string) $field['value'] : '';
				if ( ! empty( $field['id'] ) ) {
					$key = (string) $field['id'];
				} elseif ( ! empty( $field['title'] ) ) {
					$key = (string) $field['title'];
				}
				if ( $key ) {
					$map[ $key ] = $value;
				}
				if ( isset( $field['type'] ) && 'email' === $field['type'] && is_email( $value ) ) {
					$map['_email'] = $value;
				}
			}
		}

		$parsed = self::parse_fields( $map );
		if ( $parsed ) {
			return $parsed;
		}

		if ( ! empty( $map['_email'] ) && is_email( $map['_email'] ) ) {
			return array(
				'email'      => strtolower( sanitize_email( $map['_email'] ) ),
				'first_name' => '',
				'last_name'  => '',
			);
		}

		return null;
	}

	/**
	 * @param array $fields Key => value.
	 * @return array|null
	 */
	private static function parse_fields( array $fields ) {
		$email = '';
		$first = '';
		$last  = '';
		$name  = '';

		foreach ( $fields as $key => $value ) {
			$key   = strtolower( (string) $key );
			$value = trim( (string) $value );

			if ( ! $email && ( false !== strpos( $key, 'email' ) || false !== strpos( $key, 'e-mail' ) || is_email( $value ) ) && is_email( $value ) ) {
				$email = $value;
			}
			if ( ! $first && ( false !== strpos( $key, 'first' ) || 'nome' === $key || false !== strpos( $key, 'primeiro' ) ) ) {
				$first = $value;
			}
			if ( ! $last && ( false !== strpos( $key, 'last' ) || false !== strpos( $key, 'sobrenome' ) ) ) {
				$last = $value;
			}
			if ( ! $name && in_array( $key, array( 'name', 'nome', 'full_name', 'nome_completo' ), true ) ) {
				$name = $value;
			}
		}

		if ( ! $email ) {
			foreach ( $fields as $value ) {
				if ( is_email( trim( (string) $value ) ) ) {
					$email = trim( (string) $value );
					break;
				}
			}
		}

		$email = strtolower( sanitize_email( $email ) );
		if ( ! is_email( $email ) ) {
			return null;
		}

		if ( ! $first && $name ) {
			$parts = preg_split( '/\s+/', $name, 2 );
			$first = isset( $parts[0] ) ? $parts[0] : '';
			$last  = isset( $parts[1] ) ? $parts[1] : $last;
		}

		return array(
			'email'      => $email,
			'first_name' => sanitize_text_field( $first ),
			'last_name'  => sanitize_text_field( $last ),
		);
	}
}
