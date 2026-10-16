<?php
/**
 * Newsletter list admin actions.
 *
 * @package OtterMail
 */

namespace OtterMail\Lists;

use OtterMail\Core\Capabilities;
use OtterMail\Support\Http;
use OtterMail\Support\Logger;

defined( 'ABSPATH' ) || exit;

class ListController {

	/**
	 * @var ContactListRepository
	 */
	private $repo;

	public function __construct() {
		$this->repo = new ContactListRepository();
	}

	/**
	 * Register handlers.
	 */
	public function register() {
		add_action( 'admin_post_ottermail_save_list', array( $this, 'save' ) );
		add_action( 'admin_post_ottermail_delete_list', array( $this, 'delete' ) );
		add_action( 'admin_post_ottermail_add_contact', array( $this, 'add_contact' ) );
		add_action( 'admin_post_ottermail_delete_contact', array( $this, 'delete_contact' ) );
		add_action( 'admin_post_ottermail_import_csv', array( $this, 'import_csv' ) );
		add_action( 'admin_post_ottermail_sync_elementor', array( $this, 'sync_elementor' ) );
		add_action( 'elementor_pro/forms/new_record', array( $this, 'on_elementor_record' ), 10, 2 );
	}

	/**
	 * Create or update a list.
	 */
	public function save() {
		$this->guard();
		check_admin_referer( 'ottermail_save_list' );

		$id   = Http::int( 'list_id', 'post' );
		$data = $this->read_list_fields();

		if ( $id ) {
			$this->repo->update( $id, $data );
		} else {
			$id = $this->repo->insert( $data );
			Logger::log( 'list_created', __( 'Lista de newsletter criada.', 'ottermail' ), 'list', $id );
		}

		$list = $this->repo->get( $id );
		if ( $list && 'elementor' === $list->source && $list->elementor_form_id ) {
			ElementorForms::sync( $list );
		}

		Http::redirect( Http::admin_url( 'ottermail-list-edit', array( 'id' => $id ) ), 'saved' );
	}

	/**
	 * Delete a list.
	 */
	public function delete() {
		$this->guard();
		check_admin_referer( 'ottermail_delete_list' );
		$this->repo->delete( Http::int( 'list_id', 'post' ) );
		Http::redirect( Http::admin_url( 'ottermail-lists' ), 'deleted' );
	}

	/**
	 * Add a single contact.
	 */
	public function add_contact() {
		$this->guard();
		check_admin_referer( 'ottermail_add_contact' );

		$id = Http::int( 'list_id', 'post' );
		if ( ! $this->repo->get( $id ) ) {
			Http::redirect( Http::admin_url( 'ottermail-lists' ), 'error_list' );
		}

		$email = Http::text( 'email', 'post' );
		$ok    = $this->repo->add_contact(
			$id,
			$email,
			Http::text( 'first_name', 'post' ),
			Http::text( 'last_name', 'post' ),
			'manual'
		);

		Http::redirect(
			Http::admin_url( 'ottermail-list-edit', array( 'id' => $id ) ),
			$ok ? 'contact_added' : 'error_contact'
		);
	}

	/**
	 * Remove a contact.
	 */
	public function delete_contact() {
		$this->guard();
		check_admin_referer( 'ottermail_delete_contact' );

		$id = Http::int( 'list_id', 'post' );
		$this->repo->delete_contact( Http::int( 'contact_id', 'post' ), $id );
		Http::redirect( Http::admin_url( 'ottermail-list-edit', array( 'id' => $id ) ), 'deleted' );
	}

	/**
	 * Import a CSV of emails.
	 */
	public function import_csv() {
		$this->guard();
		check_admin_referer( 'ottermail_import_csv' );

		$id = Http::int( 'list_id', 'post' );
		if ( ! $this->repo->get( $id ) ) {
			Http::redirect( Http::admin_url( 'ottermail-lists' ), 'error_list' );
		}

		$file = isset( $_FILES['csv'] ) && is_array( $_FILES['csv'] ) ? $_FILES['csv'] : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$name = isset( $file['name'] ) ? sanitize_file_name( wp_unslash( $file['name'] ) ) : '';
		$size = isset( $file['size'] ) ? absint( $file['size'] ) : 0;
		$error = isset( $file['error'] ) ? absint( $file['error'] ) : UPLOAD_ERR_NO_FILE;
		$tmp  = isset( $file['tmp_name'] ) ? $file['tmp_name'] : '';

		if (
			UPLOAD_ERR_OK !== $error ||
			! $tmp ||
			! is_uploaded_file( $tmp ) ||
			$size < 1 ||
			$size > 5 * MB_IN_BYTES ||
			'csv' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) )
		) {
			Http::redirect( Http::admin_url( 'ottermail-list-edit', array( 'id' => $id ) ), 'error_csv' );
		}

		$handle = fopen( $tmp, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			Http::redirect( Http::admin_url( 'ottermail-list-edit', array( 'id' => $id ) ), 'error_csv' );
		}

		$added   = 0;
		$max     = 5000;
		$seen    = 0;
		$header  = null;

		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			if ( $seen >= $max ) {
				break;
			}
			if ( null === $header ) {
				$maybe = array_map( 'strtolower', array_map( 'trim', $row ) );
				if ( in_array( 'email', $maybe, true ) || in_array( 'e-mail', $maybe, true ) ) {
					$header = $maybe;
					continue;
				}
				$header = array( 'email', 'first_name', 'last_name' );
			}

			$mapped = $this->map_csv_row( $header, $row );
			if ( $this->repo->add_contact( $id, $mapped['email'], $mapped['first_name'], $mapped['last_name'], 'csv' ) ) {
				++$added;
			}
			++$seen;
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		$list = $this->repo->get( $id );
		$this->repo->update(
			$id,
			array(
				'name'               => $list->name,
				'source'             => 'csv',
				'elementor_post_id'  => $list->elementor_post_id,
				'elementor_form_id'  => $list->elementor_form_id,
			)
		);

		Logger::log(
			'list_imported',
			sprintf(
				/* translators: %d imported contacts */
				__( 'CSV importado: %d contatos novos.', 'ottermail' ),
				$added
			),
			'list',
			$id
		);

		Http::redirect( Http::admin_url( 'ottermail-list-edit', array( 'id' => $id ) ), 'csv_imported' );
	}

	/**
	 * Re-import Elementor submissions.
	 */
	public function sync_elementor() {
		$this->guard();
		check_admin_referer( 'ottermail_sync_elementor' );

		$id   = Http::int( 'list_id', 'post' );
		$list = $this->repo->get( $id );
		if ( ! $list ) {
			Http::redirect( Http::admin_url( 'ottermail-lists' ), 'error_list' );
		}

		ElementorForms::sync( $list );
		Http::redirect( Http::admin_url( 'ottermail-list-edit', array( 'id' => $id ) ), 'elementor_synced' );
	}

	/**
	 * Live subscribe from Elementor Pro forms.
	 *
	 * @param object $record       Form record.
	 * @param object $ajax_handler Ajax handler.
	 */
	public function on_elementor_record( $record, $ajax_handler = null ) {
		unset( $ajax_handler );

		if ( ! is_object( $record ) || ! method_exists( $record, 'get_form_settings' ) ) {
			return;
		}

		$element_id = (string) $record->get_form_settings( 'id' );
		$post_id    = (int) $record->get_form_settings( 'form_post_id' );
		if ( ! $element_id ) {
			return;
		}

		$contact = ElementorForms::from_record( $record );
		if ( ! $contact ) {
			return;
		}

		$lists = $this->repo->by_elementor_form( $post_id, $element_id );
		foreach ( $lists as $list ) {
			$this->repo->add_contact( $list->id, $contact['email'], $contact['first_name'], $contact['last_name'], 'elementor' );
		}
	}

	/**
	 * @return array
	 */
	private function read_list_fields() {
		$source = Http::text( 'source', 'post' );
		$data   = array(
			'name'   => Http::text( 'name', 'post' ),
			'source' => $source ? $source : 'manual',
		);

		$el = $this->read_elementor_from_post();
		if ( 'elementor' === $data['source'] ) {
			$data = array_merge( $data, $el );
		} else {
			$data['elementor_post_id'] = 0;
			$data['elementor_form_id'] = '';
		}

		return $data;
	}

	/**
	 * @return array
	 */
	private function read_elementor_from_post() {
		$key = Http::text( 'elementor_form', 'post' );
		if ( ! $key || false === strpos( $key, '|' ) ) {
			return array(
				'elementor_post_id' => 0,
				'elementor_form_id' => '',
			);
		}

		$parts = explode( '|', $key, 2 );

		return array(
			'elementor_post_id' => absint( $parts[0] ),
			'elementor_form_id' => sanitize_text_field( $parts[1] ),
		);
	}

	/**
	 * @param array $header Header keys.
	 * @param array $row    CSV row.
	 * @return array
	 */
	private function map_csv_row( array $header, array $row ) {
		$assoc = array();
		foreach ( $header as $i => $key ) {
			$assoc[ $key ] = isset( $row[ $i ] ) ? trim( (string) $row[ $i ] ) : '';
		}

		$email = '';
		foreach ( array( 'email', 'e-mail', 'mail' ) as $key ) {
			if ( ! empty( $assoc[ $key ] ) ) {
				$email = $assoc[ $key ];
				break;
			}
		}
		if ( ! $email && ! empty( $row[0] ) ) {
			$email = trim( (string) $row[0] );
		}

		$first = '';
		foreach ( array( 'first_name', 'firstname', 'nome', 'name' ) as $key ) {
			if ( ! empty( $assoc[ $key ] ) ) {
				$first = $assoc[ $key ];
				break;
			}
		}
		if ( ! $first && isset( $row[1] ) ) {
			$first = trim( (string) $row[1] );
		}

		$last = '';
		foreach ( array( 'last_name', 'lastname', 'sobrenome' ) as $key ) {
			if ( ! empty( $assoc[ $key ] ) ) {
				$last = $assoc[ $key ];
				break;
			}
		}
		if ( ! $last && isset( $row[2] ) ) {
			$last = trim( (string) $row[2] );
		}

		return array(
			'email'      => $email,
			'first_name' => $first,
			'last_name'  => $last,
		);
	}

	/**
	 * Capability gate.
	 */
	private function guard() {
		if ( ! Capabilities::current_user_can_manage() ) {
			wp_die( esc_html__( 'Sem permissao.', 'ottermail' ) );
		}
	}
}
