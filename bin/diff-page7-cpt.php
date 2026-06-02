<?php
/**
 * (v0.0.21-beta): page 7 (固定 /profile/) と CPT 24246 (/tsuyoshi-kashiwazaki/) の
 * 表示差分検査スタンドアロン script。配布版にはバンドルしない (柏崎さん個人サイト固有)。
 *
 * Usage:
 *   php bin/diff-page7-cpt.php [--source=7] [--target=24246] [--detail]
 *
 * 比較対象:
 *   - 各セクション (h3 単位) の存在
 *   - 各 anchor (a href) の URL セット
 *   - 主要 field (動画 name / 商標 name+classes / 取材 publisher / 講演 organizer) の値
 */

if ( php_sapi_name() !== 'cli' ) { fwrite( STDERR, "CLI only\n" ); exit( 1 ); }

$wp_load_candidates = array( __DIR__ . '/../../../../wp-load.php', '/var/www/html/wp-load.php' );
foreach ( $wp_load_candidates as $p ) {
	if ( file_exists( $p ) ) { require_once $p; break; }
}
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "wp-load.php not found\n" ); exit( 1 ); }

$opts = getopt( '', array( 'source::', 'target::', 'detail' ) );
$source_id = isset( $opts['source'] ) ? absint( $opts['source'] ) : 7;
$target_id = isset( $opts['target'] ) ? absint( $opts['target'] ) : 24246;
$detail = isset( $opts['detail'] );

$src = get_post( $source_id );
if ( ! $src ) { fwrite( STDERR, "source post #{$source_id} not found\n" ); exit( 1 ); }

$tgt_url = get_permalink( $target_id );
$tgt_html = wp_remote_retrieve_body( wp_remote_get( $tgt_url, array( 'timeout' => 10 ) ) );
if ( $tgt_html === '' ) { fwrite( STDERR, "failed to fetch {$tgt_url}\n" ); exit( 1 ); }

// 内部のみ抽出
if ( preg_match( '/<div class="ksppm-profile"[^>]*>(.*?)<\/div>\s*<\/article>/s', $tgt_html, $m ) ) {
	$tgt_section_html = $m[1];
} else {
	$tgt_section_html = $tgt_html;
}

function extract_h3_sections( $html ) {
	$result = array();
	if ( preg_match_all( '/<h3[^>]*>(.*?)<\/h3>/s', $html, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $h ) {
			$title = trim( strip_tags( $h[1] ) );
			$title = preg_replace( '/\s+/', ' ', $title );
			$result[] = $title;
		}
	}
	return $result;
}
function extract_urls( $html ) {
	if ( preg_match_all( '/href="(https?:\/\/[^"]+)"/', $html, $m ) ) {
		return array_unique( $m[1] );
	}
	return array();
}

$src_h3 = extract_h3_sections( $src->post_content );
$tgt_h3 = extract_h3_sections( $tgt_section_html );

echo "=== セクション (h3) 数: source=" . count( $src_h3 ) . ", target=" . count( $tgt_h3 ) . " ===\n";
if ( $detail ) {
	echo "source: " . implode( ' / ', $src_h3 ) . "\n";
	echo "target: " . implode( ' / ', $tgt_h3 ) . "\n";
}

$src_urls = extract_urls( $src->post_content );
$tgt_urls = extract_urls( $tgt_section_html );
$only_src = array_values( array_diff( $src_urls, $tgt_urls ) );
$only_tgt = array_values( array_diff( $tgt_urls, $src_urls ) );

echo "\n=== URL: source=" . count( $src_urls ) . ", target=" . count( $tgt_urls ) . " ===\n";
echo "source のみ (target に無い): " . count( $only_src ) . "\n";
echo "target のみ (source に無い): " . count( $only_tgt ) . "\n";
if ( $detail ) {
	echo "\n[source-only URLs] (page 7 にあるが CPT に無い):\n";
	foreach ( $only_src as $u ) echo "  - $u\n";
	echo "\n[target-only URLs] (CPT にあるが page 7 に無い):\n";
	foreach ( $only_tgt as $u ) echo "  - $u\n";
}

// 主要 field 値検査
echo "\n=== 主要 field 値検査 (CPT post_meta) ===\n";
$fields = array(
	'video_channels' => 'name',
	'trademarks'     => 'name',
	'media_mentions' => 'publisher',
	'speeches'       => 'organizer',
);
foreach ( $fields as $meta_field => $check_key ) {
	$items = KSPPM_Meta::decode_repeater( get_post_meta( $target_id, '_ksppm_' . $meta_field, true ) );
	if ( empty( $items ) ) { echo "  {$meta_field}: 0 items (skip)\n"; continue; }
	$empty = 0;
	foreach ( $items as $it ) {
		if ( empty( $it[ $check_key ] ) ) $empty++;
	}
	$status = $empty === 0 ? '✓' : '⚠';
	echo "  {$status} {$meta_field}: " . count( $items ) . " items, {$check_key} 空 = {$empty}\n";
	if ( $detail && $empty > 0 ) {
		foreach ( $items as $i => $it ) {
			if ( empty( $it[ $check_key ] ) ) {
				$label = $it['name'] ?? ( $it['title'] ?? $it['url'] ?? '?' );
				echo "    - [$i] {$label}\n";
			}
		}
	}
}

echo "\nDone.\n";
