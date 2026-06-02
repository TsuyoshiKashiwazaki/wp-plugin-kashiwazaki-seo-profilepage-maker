<?php
/**
 * (v0.0.21-beta): page 7 (柏崎さん固定ページ) と同等のセクション順序を再現するテンプレ
 *
 * 実装方針: default テンプレ (profile-content.php) を include して出力を取得し、
 *   .ksppm-profile 直下の <section id="ksppm-*"> 要素を並び替えてから echo する。
 *   anchor id (#ksppm-bio 等) は維持される (T14 合意)。
 *
 * F06: 旧 regex (negative lookahead) は biography 内の wp_kses_post 出力に <section> が
 *   混入すると外側 section の matching が破綻し、対象 section が抽出できず default 順序で
 *   出力される silent failure があった。DOMDocument で .ksppm-profile 直下の section のみを
 *   取得することで nested section も安全に処理できる。
 */
if ( ! defined( 'ABSPATH' ) ) exit;

ob_start();
include KSPPM_PLUGIN_DIR . 'templates/profile-content.php';
$html = ob_get_clean();

// page 7 順序
$ksppm_p7_order = array(
	'ksppm-bio',
	'ksppm-info',
	'ksppm-sns',
	'ksppm-videos',
	'ksppm-career',         // works_for (現職・所属)
	'ksppm-past',           // past_career (以前の経歴)
	'ksppm-hobbies',
	'ksppm-tools',          // 無料システムツール
	'ksppm-partner-tools',  // 監修・共同開発ツール
	'ksppm-trademarks',
	'ksppm-articles',       // 学術論文
	'ksppm-books',
	'ksppm-media',          // 取材・執筆・監修
	'ksppm-speeches',       // 講演・セミナー
	'ksppm-alumni',         // 学歴
	'ksppm-cred',           // 資格・認定
	'ksppm-awards',         // 受賞・表彰
	'ksppm-expertise',      // 専門性 (知識/言語/スキル)
	'ksppm-patents',        // 特許 (最後 — page 7 では中盤だが、誤抽出ツールが残る可能性で末尾安全)
	'ksppm-contact',
);

/**
 * F06: DOMDocument で .ksppm-profile 直下の section を並び替える。
 * フォールバック: DOMDocument 不在 / parse 失敗時は original html をそのまま echo (silent fallback、互換性維持)。
 */
$ksppm_reordered = null;
if ( $html !== '' && class_exists( 'DOMDocument' ) ) {
	// UTF-8 マルチバイト保護: numeric entity 化 (mb_encode_numericentity は PHP 5.0+ で安定)
	if ( function_exists( 'mb_encode_numericentity' ) ) {
		$html_encoded = mb_encode_numericentity( $html, array( 0x80, 0x10FFFF, 0, 0xFFFFFF ), 'UTF-8' );
	} else {
		$html_encoded = $html;
	}
	$ksppm_prev_use = libxml_use_internal_errors( true );
	$ksppm_dom      = new DOMDocument();
	// wrapper でルート要素を明示 (NOIMPLIED で <html>/<body> 暗黙挿入を抑制)
	$ksppm_wrapped = '<div id="ksppm-p7-wrap">' . $html_encoded . '</div>';
	if ( $ksppm_dom->loadHTML( $ksppm_wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD ) ) {
		libxml_clear_errors();
		$ksppm_xpath = new DOMXPath( $ksppm_dom );
		// .ksppm-profile (default テンプレ root) 配下の **直下** section のみ対象
		// nested section (biography 等) は対象外、id 属性が ksppm-* のもののみ
		// (D4b) footer.ksppm-meta-footer 直下の section (#ksppm-hobbies) も対象に含める。
			$ksppm_top_sections = $ksppm_xpath->query(
			'//*[contains(concat(" ", normalize-space(@class), " "), " ksppm-profile ")]'
			. '/section[starts-with(@id, "ksppm-")]'
			. ' | //*[contains(concat(" ", normalize-space(@class), " "), " ksppm-profile ")]'
			. '/footer[contains(concat(" ", normalize-space(@class), " "), " ksppm-meta-footer ")]'
			. '/section[starts-with(@id, "ksppm-")]'
		);
		if ( $ksppm_top_sections && $ksppm_top_sections->length > 0 ) {
			// id → section node のマップを作成
			$ksppm_section_nodes = array();
			$ksppm_first_section = null;
			foreach ( $ksppm_top_sections as $node ) {
				if ( ! ( $node instanceof DOMElement ) ) continue;
				$id = $node->getAttribute( 'id' );
				if ( $id === '' ) continue;
				$ksppm_section_nodes[ $id ] = $node;
				if ( $ksppm_first_section === null ) {
					$ksppm_first_section = $node;
				}
			}
			if ( $ksppm_section_nodes && $ksppm_first_section instanceof DOMElement ) {
				// (D4b) 再挿入先は .ksppm-profile 要素を明示解決 (footer 内 hobbies も主フローへ移すため)。
				$ksppm_profile_q = $ksppm_xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " ksppm-profile ")]' );
				$ksppm_parent = ( $ksppm_profile_q && $ksppm_profile_q->length > 0 && $ksppm_profile_q->item( 0 ) instanceof DOMNode )
					? $ksppm_profile_q->item( 0 )
					: $ksppm_first_section->parentNode;
				if ( $ksppm_parent instanceof DOMNode ) {
					// 並び順: page 7 order に従い、未登録 id は末尾に追加 (未来の追加 section 安全網)
					$ksppm_used    = array();
					$ksppm_ordered = array();
					foreach ( $ksppm_p7_order as $id ) {
						if ( isset( $ksppm_section_nodes[ $id ] ) ) {
							$ksppm_ordered[]    = $ksppm_section_nodes[ $id ];
							$ksppm_used[ $id ]  = true;
						}
					}
					foreach ( $ksppm_section_nodes as $id => $node ) {
						if ( empty( $ksppm_used[ $id ] ) ) {
							$ksppm_ordered[] = $node;
						}
					}
					// 既存 section を各自の親から取り外し (D4b: footer 内 hobbies は親が footer)、
					// .ksppm-profile 配下の footer.ksppm-meta-footer 直前 (最終更新行より上) へ順番通り再挿入。footer 無しは末尾へ。
					foreach ( $ksppm_section_nodes as $node ) {
						if ( $node->parentNode instanceof DOMNode ) {
							$node->parentNode->removeChild( $node );
						}
					}
					$ksppm_footer = null;
					foreach ( $ksppm_parent->childNodes as $ksppm_child ) {
						if ( $ksppm_child instanceof DOMElement && strtolower( $ksppm_child->tagName ) === 'footer'
							&& strpos( ' ' . preg_replace( '/\s+/', ' ', $ksppm_child->getAttribute( 'class' ) ) . ' ', ' ksppm-meta-footer ' ) !== false ) {
							$ksppm_footer = $ksppm_child;
							break;
						}
					}
					foreach ( $ksppm_ordered as $node ) {
						if ( $ksppm_footer instanceof DOMNode ) {
							$ksppm_parent->insertBefore( $node, $ksppm_footer );
						} else {
							$ksppm_parent->appendChild( $node );
						}
					}
					// 出力: wrapper の中身のみ取り出す
					$ksppm_wrap_node = $ksppm_dom->getElementById( 'ksppm-p7-wrap' );
					if ( ! $ksppm_wrap_node ) {
						// XPath fallback (LIBXML_HTML_NODEFDTD で getElementById が NULL を返す libxml 対策)
						$ksppm_wrap_query = $ksppm_xpath->query( '//*[@id="ksppm-p7-wrap"]' );
						if ( $ksppm_wrap_query && $ksppm_wrap_query->length > 0 ) {
							$ksppm_wrap_node = $ksppm_wrap_query->item( 0 );
						}
					}
					if ( $ksppm_wrap_node instanceof DOMNode ) {
						$ksppm_out = '';
						foreach ( $ksppm_wrap_node->childNodes as $child ) {
							$ksppm_out .= $ksppm_dom->saveHTML( $child );
						}
						$ksppm_reordered = $ksppm_out;
					}
				}
			}
		}
	} else {
		libxml_clear_errors();
	}
	libxml_use_internal_errors( $ksppm_prev_use );
}

if ( $ksppm_reordered !== null ) {
	echo $ksppm_reordered; // 元 HTML は profile-content.php で esc 済み、DOMDocument 経由でも構造は維持される
} else {
	echo $html; // フォールバック: 並び替え失敗時は default 順序のまま (silent fallback、機能等価)
}
