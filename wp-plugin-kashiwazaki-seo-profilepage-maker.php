<?php
/**
 * Plugin Name: Kashiwazaki SEO ProfilePage Maker
 * Plugin URI: https://www.tsuyoshikashiwazaki.jp
 * Description: schema.org ProfilePage / Person 構造化データを完備した SEO・E-E-A-T 強化型プロフィールページ生成プラグイン。人物の知的資産 (著作・特許・論文・取材・登壇) を機械可読化し、Google Knowledge Graph と AI 検索の双方から認知される Authority Page を作成します。
 * Version: 1.0.1
 * Author: 柏崎剛 (Tsuyoshi Kashiwazaki)
 * Author URI: https://www.tsuyoshikashiwazaki.jp/profile/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: kashiwazaki-seo-profilepage-maker
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KSPPM_VERSION', '1.0.1' );
define( 'KSPPM_PLUGIN_FILE', __FILE__ );
define( 'KSPPM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'KSPPM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'KSPPM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'KSPPM_CPT', 'ksppm_profile' );
define( 'KSPPM_META_PREFIX', '_ksppm_' );
define( 'KSPPM_OPTION_KEY', 'ksppm_settings' );
// (multiprefix) URL プレフィックス・レジストリの定数
// KSPPM_MAX_PREFIXES: 登録可能な最大プレフィックス数 (rewrite rule 暴発防止、S1/S6)
// KSPPM_DEFAULT_PREFIX: 削除不可・常に先頭のデフォルトプレフィックス (V: profile 残置)
define( 'KSPPM_MAX_PREFIXES', 20 );
define( 'KSPPM_DEFAULT_PREFIX', 'profile' );
// (custom blocks) 任意名称ブロック機能の上限 (max_input_vars/暴発防止、spec v3 S1)
define( 'KSPPM_MAX_BLOCKS', 20 );
define( 'KSPPM_MAX_BLOCK_ITEMS', 50 );

require_once KSPPM_PLUGIN_DIR . 'includes/class-ksppm-fields.php';
require_once KSPPM_PLUGIN_DIR . 'includes/class-ksppm-cpt.php';
require_once KSPPM_PLUGIN_DIR . 'includes/class-ksppm-meta.php';
require_once KSPPM_PLUGIN_DIR . 'includes/class-ksppm-schema.php';
require_once KSPPM_PLUGIN_DIR . 'includes/class-ksppm-frontend.php';
require_once KSPPM_PLUGIN_DIR . 'includes/class-ksppm-shortcode.php';
require_once KSPPM_PLUGIN_DIR . 'admin/class-ksppm-admin.php';
require_once KSPPM_PLUGIN_DIR . 'admin/class-ksppm-settings.php';

add_action( 'plugins_loaded', function () {
	KSPPM_CPT::init();
	KSPPM_Meta::init();
	KSPPM_Schema::init();
	KSPPM_Frontend::init();
	KSPPM_Shortcode::init();

	if ( is_admin() ) {
		KSPPM_Admin::init();
		KSPPM_Settings::init();
	}
} );

// (v0.0.26-beta + i18n fix): WordPress 6.7+ では load_plugin_textdomain() は init action 以降が公式推奨。
// plugins_loaded で呼ぶと `_load_textdomain_just_in_time was called incorrectly` Notice が発生し得るため init priority 1 に移動。
add_action( 'init', function () {
	load_plugin_textdomain(
		'kashiwazaki-seo-profilepage-maker',
		false,
		dirname( KSPPM_PLUGIN_BASENAME ) . '/languages'
	);
}, 1 );

/**
 * (v0.0.13-beta): PHP DOM extension 不在環境向け admin notice
 * 背景: normalize_html_fragment は DOMDocument に依存。不在環境では不正 HTML の auto-close 補完が機能せず
 *      古い tinyMCE エディタの不正 HTML (table 閉じタグ欠落) でサイドバー破壊事故が再発するリスクがある。
 * 対策: activation 時 + 管理画面読み込み時に DOMDocument の存在をチェック、不在なら admin notice を永続表示。
 *      deactivate はしない (運用継続を優先)。regex fallback も実装しない (副作用が大きい)。
 */
add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_options' ) ) return;
	if ( class_exists( 'DOMDocument' ) ) return;
	echo '<div class="notice notice-error"><p><strong>Kashiwazaki SEO ProfilePage Maker:</strong> ';
	esc_html_e( 'PHP DOM extension (ext-dom) が見つかりません。プロフィールの来歴 HTML 正規化が機能せず、不正な HTML が含まれる場合にテーマレイアウトを破壊する可能性があります。サーバー管理者に php-xml / php-dom パッケージのインストールを依頼してください。', 'kashiwazaki-seo-profilepage-maker' );
	echo '</p></div>';
} );

register_activation_hook( __FILE__, function () {
	require_once KSPPM_PLUGIN_DIR . 'includes/class-ksppm-fields.php';
	require_once KSPPM_PLUGIN_DIR . 'includes/class-ksppm-cpt.php';
	KSPPM_CPT::register_cpt();
	flush_rewrite_rules();

	// F9: sensitive_default / address_visibility は未実装機能のため削除
	$defaults = array(
		'enable_jsonld'       => 1,
		'enable_breadcrumb'   => 1,
		'enable_speakable'    => 1,
		'main_profile_post'   => 0,
		'jsonld_position'     => 'head',
		'post_type_behavior'  => 'custom', // custom | post | page (デフォルト custom = プラグイン独自 CPT)
	);
	if ( false === get_option( KSPPM_OPTION_KEY ) ) {
		add_option( KSPPM_OPTION_KEY, $defaults );
	}

	// custom 挙動時にカスタム capability を管理者・編集者ロールに付与
	KSPPM_CPT::grant_custom_capabilities();
} );

register_deactivation_hook( __FILE__, function () {
	flush_rewrite_rules();
} );

add_filter( 'plugin_action_links_' . KSPPM_PLUGIN_BASENAME, function ( $links ) {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'admin.php?page=kashiwazaki-seo-profilepage-maker' ) ),
		esc_html__( '設定', 'kashiwazaki-seo-profilepage-maker' )
	);
	array_unshift( $links, $settings_link );
	return $links;
} );

/**
 * F8: テンプレートタグ (テーマから呼び出し可能、設定画面で案内されている関数)
 *
 * @param int   $post_id プロフィール投稿 ID (0 なら設定のメイン or 最新)
 * @param array $args    {section, layout, show_toc}
 * @return string HTML 出力
 */
if ( ! function_exists( 'ksppm_render_profile' ) ) {
	function ksppm_render_profile( $post_id = 0, $args = array() ) {
		if ( ! $post_id ) {
			$settings = get_option( KSPPM_OPTION_KEY, array() );
			$post_id  = isset( $settings['main_profile_post'] ) ? absint( $settings['main_profile_post'] ) : 0;
		}
		if ( ! $post_id ) {
			$q = new WP_Query( array(
				'post_type'      => KSPPM_CPT,
				'posts_per_page' => 1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			) );
			if ( ! empty( $q->posts ) ) {
				$post_id = $q->posts[0];
			}
		}
		if ( ! $post_id ) {
			return '';
		}
		return KSPPM_Frontend::render( $post_id, $args );
	}
}
