<?php
/**
 * T5/T6/T7/T9/T10/(v0.0.21-beta): page 7 (固定 /profile/) の
 * オリジナル HTML から各セクションのデータを抽出し、CPT 24246 の post_meta に注入する
 * 1 回限り CLI スクリプト (柏崎さん個人サイト固有、配布版にはバンドルしない)。
 *
 * 🛑 page 7 は read-only。HTML を読むのみで書き込み禁止。
 *
 * Usage:
 *   php bin/migrate-page7-to-cpt.php --source=7 --target=24246 [--dry-run] [--force-overwrite] [--field=video_channels,trademarks,...]
 *
 * Default: 空欄補完のみ (--force-overwrite で上書き)。
 *
 * 抽出対象:
 *   - video_channels.name           (h3 動画配信 の <li><a>名前</a>(plat)</li>)
 *   - trademarks.name + classes + reg_no (h3 商標登録 の <p>区分</p><ul><li>「<strong>name®</strong>」（第reg_no号 ...）</li>)
 *   - media_mentions.publisher       (h3 取材... の <p>メディア名</p><ul><li><a>title</a></li>)
 *   - speeches.organizer             (h3 講演... の <p>主催</p><ul><li><a>イベント名</a></li>)
 *   - patents 非特許項目を free_tools/partner_tools へ振り分け (T9)、date は page 7 に無いため未取得 (warning)
 *   - speeches.date / location も page 7 に無いため未取得 (warning)
 */

if ( php_sapi_name() !== 'cli' ) {
	fwrite( STDERR, "CLI only\n" ); exit( 1 );
}

// WordPress 環境を読み込み
$wp_load_candidates = array(
	__DIR__ . '/../../../../wp-load.php',  // plugin/bin → wp-content/plugins → wp-content → root
	'/var/www/html/wp-load.php',
);
$wp_loaded = false;
foreach ( $wp_load_candidates as $p ) {
	if ( file_exists( $p ) ) { require_once $p; $wp_loaded = true; break; }
}
if ( ! $wp_loaded ) { fwrite( STDERR, "wp-load.php not found\n" ); exit( 1 ); }

// 引数解析
$opts = getopt( '', array( 'source::', 'target::', 'dry-run', 'force-overwrite', 'field::', 'help' ) );
if ( isset( $opts['help'] ) ) {
	echo "Usage: php bin/migrate-page7-to-cpt.php --source=7 --target=24246 [--dry-run] [--force-overwrite] [--field=...]\n";
	exit( 0 );
}
$source_id  = isset( $opts['source'] ) ? absint( $opts['source'] ) : 0;
$target_id  = isset( $opts['target'] ) ? absint( $opts['target'] ) : 0;
$dry_run    = isset( $opts['dry-run'] );
$force      = isset( $opts['force-overwrite'] );
$only_fields = isset( $opts['field'] ) ? array_map( 'trim', explode( ',', (string) $opts['field'] ) ) : null;

if ( ! $source_id || ! $target_id ) { fwrite( STDERR, "--source and --target required\n" ); exit( 1 ); }
$src = get_post( $source_id );
$tgt = get_post( $target_id );
if ( ! $src || ! $tgt ) { fwrite( STDERR, "Posts not found\n" ); exit( 1 ); }
if ( $tgt->post_type !== KSPPM_CPT ) { fwrite( STDERR, "Target must be ksppm_profile CPT\n" ); exit( 1 ); }

echo "Source: #{$src->ID} ({$src->post_type}) — {$src->post_title}\n";
echo "Target: #{$tgt->ID} ({$tgt->post_type}) — {$tgt->post_title}\n";
echo "Mode: " . ( $dry_run ? "DRY-RUN" : "WRITE" ) . " / merge: " . ( $force ? "OVERWRITE" : "FILL-EMPTY-ONLY" ) . "\n\n";

$html = $src->post_content;

// セクション切り出し (h3 単位)
function ksppm_extract_section( $html, $heading_text ) {
	$pattern = '/<h3[^>]*>.*?' . preg_quote( $heading_text, '/' ) . '.*?<\/h3>(.*?)(?=<h[23][^>]*>|$)/s';
	if ( preg_match( $pattern, $html, $m ) ) return $m[1];
	return '';
}

$extracted = array();

// T1/T11: video_channels.name 抽出 — <li><a href="URL">name</a>(platform)</li>
if ( $only_fields === null || in_array( 'video_channels', $only_fields, true ) ) {
	$section = ksppm_extract_section( $html, '動画配信' );
	$videos = array();
	if ( preg_match_all( '/<li[^>]*>\s*<a\s+href="([^"]+)"[^>]*>([^<]+)<\/a>\s*\(([^)]+)\)\s*<\/li>/', $section, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $row ) {
			$videos[] = array( 'name' => trim( $row[2] ), 'platform' => trim( $row[3] ), 'url' => trim( $row[1] ) );
		}
	}
	$extracted['video_channels'] = $videos;
}

// T10: trademarks.name + classes + reg_no 抽出
if ( $only_fields === null || in_array( 'trademarks', $only_fields, true ) ) {
	$section = ksppm_extract_section( $html, '商標登録' );
	$trademarks = array();
	// classes: <p>「(区分の長文)」で独占的に使用...</p>
	$classes_text = '';
	if ( preg_match( '/<p[^>]*>「([^」]+)」/u', $section, $cm ) ) $classes_text = trim( $cm[1] );
	// name + reg_no: <li>「<strong>name®</strong>」（第(\d+)号 ...）
	if ( preg_match_all( '/<li[^>]*>\s*「\s*<strong>([^<]+)<\/strong>\s*」（第(\d+)号/u', $section, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $row ) {
			$tm = array(
				'name'    => trim( $row[1] ),
				'reg_no'  => trim( $row[2] ),
				'classes' => $classes_text,
				'date'    => '',
				'url'     => '',
			);
			// URL は同じ <li> 内の最初の <a> から
			$li_start = strpos( $section, $row[0] );
			$li_block = substr( $section, $li_start, 800 );
			if ( preg_match( '/<a\s+href="([^"]+)"/', $li_block, $um ) ) $tm['url'] = trim( $um[1] );
			$trademarks[] = $tm;
		}
	}
	$extracted['trademarks'] = $trademarks;
}

// T11: media_mentions.publisher 抽出 — <p>メディア名</p><ul><li><a>title</a></li>
if ( $only_fields === null || in_array( 'media_mentions', $only_fields, true ) ) {
	$section = ksppm_extract_section( $html, '取材・執筆・監修' );
	$media = array();
	// <p>...</p> の後 <ul>...</ul> をペアで取る
	if ( preg_match_all( '/<p[^>]*>([^<]+)<\/p>\s*<ul[^>]*>(.*?)<\/ul>/s', $section, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $pair ) {
			$publisher = trim( strip_tags( $pair[1] ) );
			if ( preg_match_all( '/<li[^>]*>.*?<a\s+href="([^"]+)"[^>]*>([^<]+)<\/a>/s', $pair[2], $items, PREG_SET_ORDER ) ) {
				foreach ( $items as $it ) {
					$media[] = array(
						'title' => trim( html_entity_decode( $it[2], ENT_QUOTES, 'UTF-8' ) ),
						'publisher' => $publisher,
						'url' => trim( $it[1] ),
						'type' => '取材・執筆・監修',
						'date' => '',
					);
				}
			}
		}
	}
	$extracted['media_mentions'] = $media;
}

// T11: speeches.organizer 抽出 — <p>主催</p><ul><li><a>イベント名</a></li>
if ( $only_fields === null || in_array( 'speeches', $only_fields, true ) ) {
	$section = ksppm_extract_section( $html, '講演・セミナー' );
	$speeches = array();
	if ( preg_match_all( '/<p[^>]*>([^<]+)<\/p>\s*<ul[^>]*>(.*?)<\/ul>/s', $section, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $pair ) {
			$organizer = trim( strip_tags( $pair[1] ) );
			if ( preg_match_all( '/<li[^>]*>.*?<a\s+href="([^"]+)"[^>]*>([^<]+)<\/a>/s', $pair[2], $items, PREG_SET_ORDER ) ) {
				foreach ( $items as $it ) {
					$speeches[] = array(
						'name' => trim( html_entity_decode( $it[2], ENT_QUOTES, 'UTF-8' ) ),
						'organizer' => $organizer,
						'date' => '',
						'location' => '',
						'url' => trim( $it[1] ),
					);
				}
			}
		}
	}
	$extracted['speeches'] = $speeches;
}

// T9: patents から非特許 (patent_no が JP\d+ パターンに合わない + URL が patents.google.com を含まない) を除去
if ( $only_fields === null || in_array( 'patents', $only_fields, true ) ) {
	$patents_existing = json_decode( (string) get_post_meta( $target_id, '_ksppm_patents', true ), true );
	if ( ! is_array( $patents_existing ) ) $patents_existing = get_post_meta( $target_id, '_ksppm_patents', true );
	if ( is_array( $patents_existing ) ) {
		$cleaned = array();
		$rejected = array();
		foreach ( $patents_existing as $p ) {
			$is_patent = ( ! empty( $p['patent_no'] ) && preg_match( '/^JP\d+/', $p['patent_no'] ) )
				|| ( ! empty( $p['url'] ) && strpos( $p['url'], 'patents.google.com' ) !== false );
			if ( $is_patent ) $cleaned[] = $p;
			else $rejected[] = $p;
		}
		$extracted['_patents_cleaned'] = $cleaned;
		$extracted['_patents_rejected'] = $rejected;
	}
}

// 既存 post_meta との merge (空欄補完 only or 上書き)
function ksppm_merge_repeater( $existing, $extracted, $key_field, $force ) {
	if ( empty( $existing ) || ! is_array( $existing ) ) return $extracted;
	$result = $existing;
	$by_key = array();
	foreach ( $existing as $i => $row ) {
		if ( isset( $row[ $key_field ] ) ) $by_key[ $row[ $key_field ] ] = $i;
	}
	$added = 0;
	foreach ( $extracted as $new ) {
		$k = $new[ $key_field ] ?? null;
		if ( $k !== null && isset( $by_key[ $k ] ) ) {
			// 既存行と merge
			$i = $by_key[ $k ];
			foreach ( $new as $field => $val ) {
				if ( $val === '' ) continue;
				if ( $force || empty( $result[ $i ][ $field ] ) ) {
					$result[ $i ][ $field ] = $val;
				}
			}
		} else {
			$result[] = $new;
			$added++;
		}
	}
	return $result;
}

// post_meta が JSON 文字列で保存されているケースを array に正規化
function ksppm_normalize_meta( $raw ) {
	if ( is_array( $raw ) ) return $raw;
	if ( is_string( $raw ) && $raw !== '' ) {
		$decoded = json_decode( $raw, true );
		if ( is_array( $decoded ) ) return $decoded;
	}
	return array();
}

// レポート + 注入
foreach ( $extracted as $field => $new_data ) {
	if ( strpos( $field, '_' ) === 0 ) continue; // 内部用 (_patents_cleaned 等)
	$meta_key = '_ksppm_' . $field;
	$existing = ksppm_normalize_meta( get_post_meta( $target_id, $meta_key, true ) );
	$count_new = is_array( $new_data ) ? count( $new_data ) : 0;
	$count_old = is_array( $existing ) ? count( $existing ) : 0;
	echo "▶ {$field}: extracted={$count_new}, existing={$count_old}\n";
	if ( $count_new === 0 ) { echo "  (skip: nothing extracted)\n"; continue; }

	// 'name' を key とした merge (video_channels: url、その他は name か title)
	$key_field = match ( $field ) {
		'video_channels' => 'url',
		'media_mentions' => 'url',
		'speeches'       => 'url',
		'trademarks'     => 'reg_no',
		default          => 'name',
	};
	$merged = ksppm_merge_repeater( $existing ?: array(), $new_data, $key_field, $force );
	echo "  merged: " . count( $merged ) . " items (key_field={$key_field})\n";

	// 詳細 diff (新規追加 + 補完 field を 1 行ずつ)
	foreach ( $new_data as $row ) {
		$k = $row[ $key_field ] ?? '';
		$preview = isset( $row['name'] ) ? $row['name'] : ( $row['title'] ?? $k );
		echo "    + {$preview} ({$key_field}={$k})\n";
	}

	if ( ! $dry_run ) {
		// KSPPM_Meta: decode_repeater は JSON 文字列を期待 (admin save 経路と一致させる)
		update_post_meta( $target_id, $meta_key, wp_slash( wp_json_encode( $merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );
		echo "  ✓ WROTE post_meta {$meta_key} (" . count( $merged ) . " items, JSON-encoded)\n";
	} else {
		echo "  (dry-run: skipped write)\n";
	}
}

// patents cleanup レポート
if ( isset( $extracted['_patents_cleaned'] ) ) {
	$cleaned = $extracted['_patents_cleaned'];
	$rejected = $extracted['_patents_rejected'];
	echo "\n▶ patents cleanup (T9): patent_no=^JP\\d+ または URL に patents.google.com を含むもののみ残す\n";
	echo "  kept: " . count( $cleaned ) . "\n";
	foreach ( $cleaned as $p ) echo "    + " . ( $p['name'] ?? '?' ) . " (no=" . ( $p['patent_no'] ?? '' ) . ")\n";
	echo "  rejected (非特許判定): " . count( $rejected ) . "\n";
	foreach ( $rejected as $p ) echo "    - " . ( $p['name'] ?? '?' ) . " — URL=" . ( $p['url'] ?? '' ) . "\n";
	if ( ! $dry_run && count( $rejected ) > 0 ) {
		update_post_meta( $target_id, '_ksppm_patents', wp_slash( wp_json_encode( $cleaned, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );
		echo "  ✓ WROTE patents (cleaned) post_meta — " . count( $cleaned ) . " items\n";
	} elseif ( $dry_run ) {
		echo "  (dry-run: skipped write)\n";
	}
}

// warning: page 7 に無いデータ
echo "\n⚠ WARNINGS (page 7 にデータが無いため未抽出):\n";
echo "  - patents[*].date — Google Patents 等から手動補完を推奨\n";
echo "  - speeches[*].date / location — connpass / 各イベントページから手動補完を推奨\n";

echo "\nDone. " . ( $dry_run ? "(DRY-RUN; 実反映には --dry-run を外して再実行)" : "" ) . "\n";
