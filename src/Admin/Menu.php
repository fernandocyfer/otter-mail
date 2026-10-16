<?php
/**
 * Admin menu and page router.
 *
 * @package OtterMail
 */

namespace OtterMail\Admin;

use OtterMail\Audiences\AudienceRepository;
use OtterMail\Automations\AutomationRepository;
use OtterMail\Campaigns\CampaignRepository;
use OtterMail\Cart\CartRepository;
use OtterMail\Core\Capabilities;
use OtterMail\Core\Settings;
use OtterMail\Lists\ContactListRepository;
use OtterMail\Lists\ElementorForms;
use OtterMail\Reports\ReportService;
use OtterMail\Support\Http;
use OtterMail\Support\Logger;
use OtterMail\Support\View;
use OtterMail\Templates\TemplateRepository;

defined( 'ABSPATH' ) || exit;

class Menu {

	/**
	 * Register menus.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'pages' ) );
		add_action( 'admin_head', array( $this, 'hide_edit_pages_css' ) );
		add_filter( 'parent_file', array( $this, 'parent_file' ) );
		add_filter( 'submenu_file', array( $this, 'submenu_file' ), 10, 2 );
	}

	/**
	 * Hidden edit screens mapped to the visible submenu they belong to.
	 *
	 * @return array<string, string>
	 */
	private function hidden_pages() {
		return array(
			'ottermail-campaign-edit'   => 'ottermail-campaigns',
			'ottermail-audience-edit'   => 'ottermail-audiences',
			'ottermail-list-edit'       => 'ottermail-lists',
			'ottermail-template-edit'   => 'ottermail-templates',
			'ottermail-automation-edit' => 'ottermail-automations',
			'ottermail-cart-view'       => 'ottermail-carts',
		);
	}

	/**
	 * Keep OtterMail expanded in the admin sidebar.
	 *
	 * @param string $parent_file Parent menu slug.
	 * @return string
	 */
	public function parent_file( $parent_file ) {
		$page = $this->current_page();
		if ( $page && 0 === strpos( $page, 'ottermail' ) ) {
			return 'ottermail';
		}

		return $parent_file;
	}

	/**
	 * Highlight the visible submenu that matches the current screen.
	 *
	 * @param string $submenu_file Current submenu slug.
	 * @param string $parent_file  Parent menu slug.
	 * @return string
	 */
	public function submenu_file( $submenu_file, $parent_file ) {
		unset( $parent_file );
		$page = $this->current_page();
		if ( ! $page ) {
			return $submenu_file;
		}

		$map = $this->hidden_pages();
		if ( 'ottermail-template-edit' === $page && Http::int( 'return_campaign', 'get' ) ) {
			return 'ottermail-campaigns';
		}
		if ( isset( $map[ $page ] ) ) {
			return $map[ $page ];
		}

		if ( 0 === strpos( $page, 'ottermail' ) ) {
			return $page;
		}

		return $submenu_file;
	}

	/**
	 * Current admin.php?page= slug.
	 *
	 * @return string
	 */
	private function current_page() {
		if ( empty( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return '';
		}

		return sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Add OtterMail menus.
	 */
	public function pages() {
		$cap = Capabilities::MANAGE;

		add_menu_page(
			'OtterMail',
			'OtterMail',
			$cap,
			'ottermail',
			array( $this, 'dashboard' ),
			'dashicons-email-alt',
			56
		);

		add_submenu_page( 'ottermail', __( 'Dashboard', 'ottermail' ), __( 'Dashboard', 'ottermail' ), $cap, 'ottermail', array( $this, 'dashboard' ) );
		add_submenu_page( 'ottermail', __( 'Campanhas', 'ottermail' ), __( 'Campanhas', 'ottermail' ), $cap, 'ottermail-campaigns', array( $this, 'campaigns' ) );
		add_submenu_page( 'ottermail', __( 'Automacoes', 'ottermail' ), __( 'Automacoes', 'ottermail' ), $cap, 'ottermail-automations', array( $this, 'automations' ) );
		add_submenu_page( 'ottermail', __( 'Publicos', 'ottermail' ), __( 'Publicos', 'ottermail' ), $cap, 'ottermail-audiences', array( $this, 'audiences' ) );
		add_submenu_page( 'ottermail', __( 'Listas', 'ottermail' ), __( 'Listas', 'ottermail' ), $cap, 'ottermail-lists', array( $this, 'lists' ) );
		add_submenu_page( 'ottermail', __( 'Templates', 'ottermail' ), __( 'Templates', 'ottermail' ), $cap, 'ottermail-templates', array( $this, 'templates' ) );
		add_submenu_page( 'ottermail', __( 'Carrinhos', 'ottermail' ), __( 'Carrinhos', 'ottermail' ), $cap, 'ottermail-carts', array( $this, 'carts' ) );
		add_submenu_page( 'ottermail', __( 'Relatorios', 'ottermail' ), __( 'Relatorios', 'ottermail' ), $cap, 'ottermail-reports', array( $this, 'reports' ) );
		add_submenu_page( 'ottermail', __( 'Configuracoes', 'ottermail' ), __( 'Configuracoes', 'ottermail' ), $cap, 'ottermail-settings', array( $this, 'settings' ) );

		add_submenu_page( 'ottermail', __( 'Editar campanha', 'ottermail' ), __( 'Editar campanha', 'ottermail' ), $cap, 'ottermail-campaign-edit', array( $this, 'campaign_edit' ) );
		add_submenu_page( 'ottermail', __( 'Editar publico', 'ottermail' ), __( 'Editar publico', 'ottermail' ), $cap, 'ottermail-audience-edit', array( $this, 'audience_edit' ) );
		add_submenu_page( 'ottermail', __( 'Editar lista', 'ottermail' ), __( 'Editar lista', 'ottermail' ), $cap, 'ottermail-list-edit', array( $this, 'list_edit' ) );
		add_submenu_page( 'ottermail', __( 'Editar template', 'ottermail' ), __( 'Editar template', 'ottermail' ), $cap, 'ottermail-template-edit', array( $this, 'template_edit' ) );
		add_submenu_page( 'ottermail', __( 'Editar automacao', 'ottermail' ), __( 'Editar automacao', 'ottermail' ), $cap, 'ottermail-automation-edit', array( $this, 'automation_edit' ) );
		add_submenu_page( 'ottermail', __( 'Carrinho', 'ottermail' ), __( 'Carrinho', 'ottermail' ), $cap, 'ottermail-cart-view', array( $this, 'cart_view' ) );
	}

	/**
	 * Hide edit screens from the sidebar without removing them from WP access checks.
	 */
	public function hide_edit_pages_css() {
		$selectors = array();
		foreach ( array_keys( $this->hidden_pages() ) as $slug ) {
			$selectors[] = '#adminmenu #toplevel_page_ottermail .wp-submenu li:has(a[href*="page=' . $slug . '"])';
		}

		echo '<style id="ottermail-hide-edit-pages">' . implode( ',', $selectors ) . '{display:none!important}</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public function dashboard() {
		$stats = ( new ReportService() )->dashboard();
		View::render(
			'admin/dashboard',
			array(
				'stats'    => $stats,
				'activity' => Logger::recent( 8 ),
			)
		);
	}

	public function campaigns() {
		View::render(
			'admin/campaigns',
			array(
				'campaigns' => ( new CampaignRepository() )->latest( 50 ),
			)
		);
	}

	public function campaign_edit() {
		$id       = Http::int( 'id', 'get' );
		$campaign = $id ? ( new CampaignRepository() )->get( $id ) : null;
		$step     = max( 1, min( 4, Http::int( 'step', 'get' ) ? Http::int( 'step', 'get' ) : 1 ) );

		View::render(
			'admin/campaign-edit',
			array(
				'campaign'  => $campaign,
				'step'      => $step,
				'templates' => ( new TemplateRepository() )->all(),
				'audiences' => ( new AudienceRepository() )->all(),
				'lists'     => ( new ContactListRepository() )->all(),
				'settings'  => Settings::all(),
			)
		);
	}

	public function audiences() {
		View::render( 'admin/audiences', array( 'audiences' => ( new AudienceRepository() )->all() ) );
	}

	public function audience_edit() {
		$id = Http::int( 'id', 'get' );
		View::render(
			'admin/audience-edit',
			array(
				'audience' => $id ? ( new AudienceRepository() )->get( $id ) : null,
			)
		);
	}

	public function lists() {
		View::render( 'admin/lists', array( 'lists' => ( new ContactListRepository() )->all() ) );
	}

	public function list_edit() {
		$id   = Http::int( 'id', 'get' );
		$repo = new ContactListRepository();
		$list = $id ? $repo->get( $id ) : null;
		View::render(
			'admin/list-edit',
			array(
				'list'                 => $list,
				'contacts'             => $list ? $repo->get_contacts( $list->id, 200 ) : array(),
				'contact_count'        => $list ? $repo->count_contacts( $list->id ) : 0,
				'elementor_forms'      => ElementorForms::forms(),
				'elementor_available'  => ElementorForms::available(),
			)
		);
	}

	public function templates() {
		View::render( 'admin/templates', array( 'templates' => ( new TemplateRepository() )->all() ) );
	}

	public function template_edit() {
		$id = Http::int( 'id', 'get' );
		View::render(
			'admin/template-edit',
			array(
				'template'        => $id ? ( new TemplateRepository() )->get( $id ) : null,
				'return_campaign' => Http::int( 'return_campaign', 'get' ),
			)
		);
	}

	public function automations() {
		View::render( 'admin/automations', array( 'automations' => ( new AutomationRepository() )->all() ) );
	}

	public function automation_edit() {
		$id  = Http::int( 'id', 'get' );
		$repo = new AutomationRepository();
		View::render(
			'admin/automation-edit',
			array(
				'automation' => $id ? $repo->get( $id ) : null,
				'steps'      => $id ? $repo->get_steps( $id ) : array(),
				'templates'  => ( new TemplateRepository() )->all(),
			)
		);
	}

	public function carts() {
		$status = Http::text( 'status', 'get' );
		View::render(
			'admin/carts',
			array(
				'carts'  => ( new CartRepository() )->list_carts( $status, 100 ),
				'status' => $status,
			)
		);
	}

	public function cart_view() {
		$id = Http::int( 'id', 'get' );
		View::render(
			'admin/cart-view',
			array(
				'cart' => $id ? ( new CartRepository() )->get( $id ) : null,
				'logs' => $id ? Logger::for_object( 'cart', $id ) : array(),
			)
		);
	}

	public function reports() {
		View::render(
			'admin/reports',
			array(
				'report'    => ( new ReportService() )->report(),
				'campaigns' => ( new CampaignRepository() )->latest( 100 ),
			)
		);
	}

	public function settings() {
		View::render( 'admin/settings', array( 'settings' => Settings::all() ) );
	}
}
