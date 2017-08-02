<?php


include_once( 'PostmenWoocommercePlugin_LifeCycle.php' );
include_once( 'PostmenWoocommercePlugin_Utilities.php' );
include_once( 'PostmenWoocommercePlugin_API.php' );

class PostmenWoocommercePlugin_Plugin extends PostmenWoocommercePlugin_LifeCycle {

	public function __construct() {
		$this->api = new PostmenWoocommercePlugin_API();
	}

	/**
	 * See: http://plugin.michael-simpson.com/?page_id=31
	 * @return array of option meta data.
	 */
	public function getOptionMetaData() {
		//	http://plugin.michael-simpson.com/?page_id=31
		return [
			//'_version' => array('Installed Version'), // Leave this one commented-out. Uncomment to test upgrades.
			'ATextInput'     => [ __( 'Enter in some text', 'my-awesome-plugin' ) ],
			'AmAwesome'      => [ __( 'I like this awesome plugin', 'my-awesome-plugin' ), 'false', 'true' ],
			'CanDoSomething' => [
				__( 'Which user role can do something', 'my-awesome-plugin' ),
				'Administrator',
				'Editor',
				'Author',
				'Contributor',
				'Subscriber',
				'Anyone'
			]
		];
	}

//		protected function getOptionValueI18nString($optionValue) {
//		$i18nValue = parent::getOptionValueI18nString($optionValue);
//		return $i18nValue;
//		}

	protected function initOptions() {
		$options = $this->getOptionMetaData();
		if ( ! empty( $options ) ) {
			foreach ( $options as $key => $arr ) {
				if ( is_array( $arr ) && count( $arr > 1 ) ) {
					$this->addOption( $key, $arr[1] );
				}
			}
		}
	}

	public function getPluginDisplayName() {
		return 'Postmen Woocommerce Plugin';
	}

	protected function getMainPluginFileName() {
		return 'postmen-woocommerce-plugin.php';
	}

	/**
	 * See: http://plugin.michael-simpson.com/?page_id=101
	 * Called by install() to create any database tables if needed.
	 * Best Practice:
	 * (1) Prefix all table names with $wpdb->prefix
	 * (2) make table names lower case only
	 * @return void
	 */
	protected function installDatabaseTables() {
		//		global $wpdb;
		//		$tableName = $this->prefixTableName('mytable');
		//		$wpdb->query("CREATE TABLE IF NOT EXISTS `$tableName` (
		//			`id` INTEGER NOT NULL");
	}

	/**
	 * See: http://plugin.michael-simpson.com/?page_id=101
	 * Drop plugin-created tables on uninstall.
	 * @return void
	 */
	protected function unInstallDatabaseTables() {
		//		global $wpdb;
		//		$tableName = $this->prefixTableName('mytable');
		//		$wpdb->query("DROP TABLE IF EXISTS `$tableName`");
	}


	/**
	 * Perform actions when upgrading from version X to version Y
	 * See: http://plugin.michael-simpson.com/?page_id=35
	 * @return void
	 */
	public function upgrade() {
	}

	public function addActionsAndFilters() {

		// Add options administration page
		// http://plugin.michael-simpson.com/?page_id=47
		// uncomment this to enable settings page again
		// add_action('admin_menu', array(&$this, 'addSettingsSubMenuPage'));

		/* creating API key field in user settings */
		add_action( 'show_user_profile', [ $this, 'postmen_connector_add_api_key_field' ] );
		add_action( 'edit_user_profile', [ $this, 'postmen_connector_add_api_key_field' ] );
		add_action( 'personal_options_update', [ $this, 'postmen_connector_generate_api_key' ] );
		add_action( 'edit_user_profile_update', [ $this, 'postmen_connector_generate_api_key' ] );

		// Example adding a script & style just for the options administration page
		// http://plugin.michael-simpson.com/?page_id=47
		//		if (strpos($_SERVER['REQUEST_URI'], $this->getSettingsSlug()) !== false) {
		//			wp_enqueue_script('my-script', plugins_url('/js/my-script.js', __FILE__));
		//			wp_enqueue_style('my-style', plugins_url('/css/my-style.css', __FILE__));
		//		}


		// Add Actions & Filters
		// http://plugin.michael-simpson.com/?page_id=37


		// Adding scripts & styles to all pages
		// Examples:
		//		wp_enqueue_script('jquery');
		//		wp_enqueue_style('my-style', plugins_url('/css/my-style.css', __FILE__));
		//		wp_enqueue_script('my-script', plugins_url('/js/my-script.js', __FILE__));


		// Register short codes
		// http://plugin.michael-simpson.com/?page_id=39


		// Register AJAX hooks
		// http://plugin.michael-simpson.com/?page_id=41

	}

	/**
	 * See: http://plugin.michael-simpson.com/?page_id=105
	 * @return void
	 */
	public function activate() {
		global $wp_roles;
		if ( class_exists( 'WP_Roles' ) ) {
			if ( ! isset( $wp_roles ) ) {
				$wp_roles = new WP_Roles();
			}
		}
		if ( is_object( $wp_roles ) ) {
			$wp_roles->add_cap( 'administrator', 'manage_postmen' );
		}
	}

	/**
	 * See: http://plugin.michael-simpson.com/?page_id=105
	 * @return void
	 */
	public function deactivate() {
	}

	public function postmen_connector_add_api_key_field( $user ) {
		if ( ! current_user_can( 'manage_postmen' ) ) {
			return;
		}
		if ( current_user_can( 'edit_user', $user->ID ) ) {
			?>
            <h3>Postmen</h3>
            <table class="form-table">
                <tbody>
                <tr>
                    <th><label
                                for="postmen_wp_api_key"><?php _e( 'Postmen\'s WordPress API Key',
								'postmen' ); ?></label>
                    </th>
                    <td>
						<?php if ( empty( $user->postmen_wp_api_key ) ) : ?>
                            <input name="postmen_wp_generate_api_key" type="checkbox"
                                   id="postmen_wp_generate_api_key" value="0"/>
                            <span class="description"><?php _e( 'Generate API Key', 'postmen' ); ?></span>
						<?php else : ?>
                            <code id="postmen_wp_api_key"><?php echo $user->postmen_wp_api_key ?></code>
                            <br/>
                            <input name="postmen_wp_generate_api_key" type="checkbox"
                                   id="postmen_wp_generate_api_key" value="0"/>
                            <span class="description"><?php _e( 'Revoke API Key', 'postmen' ); ?></span>
						<?php endif; ?>
                    </td>
                </tr>
                </tbody>
            </table>
			<?php
		}
	}

	public function postmen_connector_generate_api_key( $user_id ) {
		if ( current_user_can( 'edit_user', $user_id ) ) {
			$user = get_userdata( $user_id );
			// creating/deleting key
			if ( isset( $_POST['postmen_wp_generate_api_key'] ) ) {
				// consumer key
				if ( empty( $user->postmen_wp_api_key ) ) {
					$api_key = 'ck_' . hash( 'md5', $user->user_login . date( 'U' ) . mt_rand() );
					update_user_meta( $user_id, 'postmen_wp_api_key', $api_key );
				} else {
					delete_user_meta( $user_id, 'postmen_wp_api_key' );
				}
			}
		}
	}
}
