<?php
/**
 * Kashiwazaki SEO ProfilePage Maker - Uninstall handler
 *
 * (v0.0.15-beta): プラグイン削除時に option / meta / transient / capability を完全 cleanup。
 * WordPress.org Plugin Directory ガイドライン推奨。
 *
 * 注意: 本ファイルは WordPress により plugin deletion 時のみロードされる (uninstall hook)。
 *       activation/deactivation 時には実行されない。
 *       単にプラグインを deactivate しただけではデータは残る (意図的、誤削除防止)。
 *       完全削除時 (Plugins > Delete plugin) のみ実行。
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// (1) 全 ksppm_profile CPT の post + meta を削除
$cpt = 'ksppm_profile';
$post_ids = $wpdb->get_col( $wpdb->prepare(
	"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s",
	$cpt
) );
foreach ( $post_ids as $post_id ) {
	wp_delete_post( (int) $post_id, true ); // force delete (skip trash)
}

// (2) Plugin options を削除
delete_option( 'ksppm_settings' );
delete_site_option( 'ksppm_settings' ); // multisite 用 (将来対応)

// (3) Transient cache を削除
$transient_prefix = '_transient_ksppm_';
$site_transient_prefix = '_site_transient_ksppm_';
$wpdb->query( $wpdb->prepare(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
	$wpdb->esc_like( $transient_prefix ) . '%',
	$wpdb->esc_like( $site_transient_prefix ) . '%'
) );
// timeout meta も削除 (F12: site transient の timeout 行も削除して孤立行を残さない)
$wpdb->query( $wpdb->prepare(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
	$wpdb->esc_like( '_transient_timeout_ksppm_' ) . '%',
	$wpdb->esc_like( '_site_transient_timeout_ksppm_' ) . '%'
) );

// (4) Custom capabilities を全 role から削除
$capabilities = array(
	'edit_ksppm_profile', 'read_ksppm_profile', 'delete_ksppm_profile',
	'edit_ksppm_profiles', 'edit_others_ksppm_profiles', 'publish_ksppm_profiles',
	'read_private_ksppm_profiles', 'delete_ksppm_profiles', 'delete_private_ksppm_profiles',
	'delete_published_ksppm_profiles', 'delete_others_ksppm_profiles',
	'edit_private_ksppm_profiles', 'edit_published_ksppm_profiles',
);
$roles = wp_roles()->roles;
foreach ( $roles as $role_slug => $role_data ) {
	$role = get_role( $role_slug );
	if ( ! $role ) continue;
	foreach ( $capabilities as $cap ) {
		$role->remove_cap( $cap );
	}
}

// (5) Rewrite rules を flush (CPT rewrite を確実に削除)
flush_rewrite_rules();
