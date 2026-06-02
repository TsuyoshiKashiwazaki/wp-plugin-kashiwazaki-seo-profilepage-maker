<?php
/**
 * Shortcode (FRO-01, FRO-14)
 * [kashiwazaki_profilepage id="123" section="all" layout="default"]
 * [kashiwazaki_profile section="books"]
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KSPPM_Shortcode {

	public static function init() {
		add_shortcode( 'kashiwazaki_profilepage', array( __CLASS__, 'render' ) );
		add_shortcode( 'kashiwazaki_profile', array( __CLASS__, 'render' ) );
		// (v0.0.15-beta): 汎用 namespace alias 追加 (作者名 namespace の長期固定回避)
		add_shortcode( 'ksppm_profile', array( __CLASS__, 'render' ) );
	}

	public static function render( $atts ) {
		// F11: show_toc 属性は v0.0.19-beta で目次機能が削除されて以降 dead parameter (テンプレートで未参照)。
		// 削除は既存ショートコードの破壊変更になるため、shortcode_atts に残置しつつ非推奨を明示する。
		// 将来 TOC 機能を再追加する場合は本属性経由で再 wiring 可能。
		$atts = shortcode_atts(
			array(
				'id'       => 0,
				'section'  => 'all',
				'layout'   => 'default',
				'show_toc' => '1', // @deprecated v0.0.19-beta: 目次機能削除済、本属性は現在無効 (互換性維持のため残置)
			),
			$atts,
			'kashiwazaki_profilepage'
		);

		$post_id = absint( $atts['id'] );
		if ( ! $post_id ) {
			$settings = get_option( KSPPM_OPTION_KEY, array() );
			$post_id = isset( $settings['main_profile_post'] ) ? absint( $settings['main_profile_post'] ) : 0;
		}
		if ( ! $post_id ) {
			// fallback: 最新の ksppm_profile
			$q = new WP_Query( array(
				'post_type'      => KSPPM_CPT,
				'posts_per_page' => 1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			) );
			if ( ! empty( $q->posts ) ) $post_id = $q->posts[0];
		}
		if ( ! $post_id ) return '<!-- KSPPM: プロフィール未作成 -->';

		return KSPPM_Frontend::render( $post_id, array(
			'section'  => sanitize_key( $atts['section'] ),
			'layout'   => sanitize_key( $atts['layout'] ),
			'show_toc' => $atts['show_toc'] === '1',
		) );
	}
}
