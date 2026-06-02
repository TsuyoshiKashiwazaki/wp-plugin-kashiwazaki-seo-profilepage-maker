<?php
/**
 * CPT 'ksppm_profile' 登録 (FRO-13, DES-01)
 *
 * post_type_behavior 設定の 3 モード (post / custom / page) は本ファイル内の以下に依存:
 *
 *   1. fallback_to_cpt() の早期 return 条件拡張 (v1.0.10):
 *      query_vars[KSPPM_CPT] が rewrite で立たない経路 (page 型 / custom 型) でも、
 *      query_vars[pagename] から CPT 検索を実行できる。
 *      この拡張なしでは page 型 / custom 型は parse_request で pagename → page クエリにフォールバックし
 *      404 → 他プラグインの 404 redirect ループに陥る。
 *
 *   2. fallback_to_cpt() 内の CPT 検索: $wpdb 直クエリ (publish 限定):
 *      WP の WP_Query 経由ではなく $wpdb->prepare で直接 SQL 検索することで、
 *      capability_type=array (custom) や page (hierarchical) の認可フィルタ干渉を完全 bypass。
 *      この直クエリなしでは guest ユーザーの認可で post 取得が失敗する。
 *
 *   3. force_inject_cpt_post() フィルタ (posts_pre_query 経由):
 *      main query (WP_Query) が CPT post を取得できなかった場合に、
 *      $wpdb 直クエリで取得した WP_Post を強制注入 + is_singular/is_404 を補正。
 *      これにより rewrite で立った post_type=CPT が WP コア認可で空配列にされても 200 で動作。
 *
 * 上記 1+2+3 のうち 1 つでも欠けると post 型以外は動作しなくなる。
 * 3 モード全ての動作はこの 3 機構の協調に依存しているため、変更時は 3 モード全てで実機 HTTP 200 を再検証すること。
 * (page 型サポート格上げ時の code review、副作用 5 件 verified)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KSPPM_CPT {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_cpt' ) );
		// (multiprefix V6): 旧単一 url_prefix → 新 url_prefixes レジストリ + 投稿メタへの 1 回限り移行
		add_action( 'init', array( __CLASS__, 'maybe_migrate_multiprefix' ), 9 );
		// CPT を page と同じ階層 (/{post_name}/) で公開するため:
		// 1) 各プロフィールの permalink を書き換え
		add_filter( 'post_type_link', array( __CLASS__, 'filter_permalink' ), 10, 2 );
		// 2) /{post_name}/ を CPT にマッチさせる rewrite rule を追加 (bottom priority で page 優先)
		add_action( 'init', array( __CLASS__, 'add_root_rewrite' ), 11 );
		// 3) canonical redirect ループ回避: query_var が CPT の場合のみ redirect を完全 short-circuit
		add_action( 'parse_request', array( __CLASS__, 'disable_canonical_for_cpt' ), 999 );
		// 4) CPT slug が無い時は page にフォールバック
		add_action( 'parse_request', array( __CLASS__, 'fallback_to_cpt' ), 5 );
		// 4b) main query の認可干渉対策: WP_Query が CPT 取得できない場合に直クエリで強制注入
		// (v0.0.13-beta): posts_pre_query 早期 bypass を the_posts (空結果時のみ) に変更。
		// posts_pre_query は WP_Query 認可・多言語 (Polylang/WPML)・AMP 等のフィルタを bypass するため、
		// the_posts で空結果のときだけ注入する互換モードに切り替え。
		add_filter( 'the_posts', array( __CLASS__, 'force_inject_cpt_post' ), 10, 2 );
		// 4b2) (F3): root_rewrite OFF のとき bare /{slug}/ が WP コアの 404 推測 redirect
		// (redirect_guess_404_permalink) で CPT 正規 URL へ 301 されるのを抑止し、ルート公開 opt-out を厳守。
		add_filter( 'do_redirect_guess_404_permalink', array( __CLASS__, 'maybe_block_root_404_guess' ) );
		// 4c) (multiprefix S3-0): canonical / legacy / query 形式の 301 を単一ハンドラに統合。
		// 旧 legacy_prefix_redirect (priority 1) + canonical_query_redirect (priority 2) を統合し、
		// 二重 301・リダイレクトループを構造的に防ぐ (現プレフィックス一致時は redirect しない)。
		add_action( 'template_redirect', array( __CLASS__, 'canonical_redirect' ), 1 );
		// 5) redirect_canonical の self-redirect (ループ) を block
		//    実機検証で filter 方式は他プラグイン (KSTB/Redirection 等) の干渉で機能しなかったため、
		//    Page フォールバック経路に限定して remove_action でブロック (fallback_to_cpt 内で実行)
		//    trailing slash 補正は .htaccess で別途実施済 (canonical_redirect 不要)
	}

	/**
	 * canonical redirect 完全無効化 (CPT クエリ時、parse_request で早期判定)
	 */
	public static function disable_canonical_for_cpt( $wp ) {
		if ( ! empty( $wp->query_vars[ KSPPM_CPT ] ) ) {
			remove_action( 'template_redirect', 'redirect_canonical' );
			remove_action( 'template_redirect', 'wp_old_slug_redirect' );
		}
	}

	/**
	 * permalink を /{post_name}/ または /{prefix}/{post_name}/ に書き換え
	 * (v0.0.20-beta): build_permalink helper に集約
	 */
	public static function filter_permalink( $url, $post ) {
		if ( $post->post_type !== KSPPM_CPT ) return $url;
		if ( ! $post->post_name ) return $url;
		// (multiprefix V1): 投稿 ID を渡し、投稿固有のプレフィックス (_ksppm_url_prefix) で
		// permalink を生成する。これを渡さないと全投稿がサイト共通の旧 url_prefix になり、
		// per-profile 選択が permalink に反映されない (canonical も崩れる)。
		return self::build_permalink( $post->post_name, (int) $post->ID );
	}

	/**
	 * /{post_name}/ で CPT にアクセスできる rewrite rule を追加
	 *
	 * (v0.0.15-beta): 配布前汎用化のため root rewrite を opt-in 化。
	 * 背景: top priority で `^([^/]+)/?$` 全シングルセグメント URL を CPT に取り込む設計は、配布先サイトで他プラグインや
	 *      通常投稿 URL と衝突するリスクが高い。default を OFF にし、ksppm_settings の `root_rewrite_enabled` で明示 opt-in。
	 *      OFF 時は WP コア default の `/?ksppm_profile={slug}/` または `/{prefix}/{slug}/` (rewrite_slug option) が使われる。
	 */
	public static function add_root_rewrite() {
		$settings = get_option( KSPPM_OPTION_KEY, array() );

		// (multiprefix): 登録済みプレフィックス全てに rewrite rule を追加。
		// V2 longest-match: 多段 (team/about) と単段 (profile) が両方登録され得るため、
		// 長いプレフィックスを先に add_rewrite_rule する (WP は登録順で先勝ち評価のため、
		// /team/about/john が profile より先に team/about にマッチする)。
		$prefixes = self::get_url_prefixes();
		usort( $prefixes, function ( $a, $b ) {
			return strlen( $b ) - strlen( $a ); // 長い順
		} );

		$has_prefix_rule = false;
		foreach ( $prefixes as $prefix ) {
			if ( $prefix === '' ) {
				continue;
			}
			add_rewrite_rule(
				'^' . preg_quote( $prefix, '#' ) . '/([^/]+)/?$',
				'index.php?' . KSPPM_CPT . '=$matches[1]',
				'top'
			);
			$has_prefix_rule = true;
		}

		// (V3 / M1 後方互換): root rewrite (prefix 無しルート公開 /{slug}/) の扱い。
		// マルチプレフィックスでは全投稿が profile 等の配下に出るため root は新機能としては不要 (案X)。
		// ただし root_rewrite_enabled=1 だった既存サイト (移行前は /{slug}/ で公開) の旧 URL を
		// 移行後 404 にしない後方互換のため、enabled なら root rule を 'bottom' priority で張り続ける。
		// 'bottom' なので prefix rule (top) と通常 page/post が優先され、fallback_to_cpt の
		// post/page 優先解決と協調する。/{slug}/ が CPT に解決された場合は canonical_redirect が
		// 投稿の現プレフィックス URL (/profile/{slug}/) へ 301 し、旧 URL の SEO 資産を保全する。
		$enabled = isset( $settings['root_rewrite_enabled'] ) ? (int) $settings['root_rewrite_enabled'] : 0;
		if ( ! $enabled ) {
			return;
		}
		$root_priority = $has_prefix_rule ? 'bottom' : 'top';
		add_rewrite_rule(
			'^([^/]+)/?$',
			'index.php?' . KSPPM_CPT . '=$matches[1]',
			$root_priority
		);
	}

	/**
	 * (v0.0.20-beta): url_prefix の正規化されたパスを取得
	 * バリデーション: 英数 / - / _ / / のみ、前後スラッシュ除去、予約 slug 排除
	 * 空文字なら従来動作 (root rewrite または pagename fallback)
	 */
	public static function get_url_prefix() {
		$settings = get_option( KSPPM_OPTION_KEY, array() );
		$raw = isset( $settings['url_prefix'] ) ? (string) $settings['url_prefix'] : '';
		return self::normalize_url_prefix( $raw );
	}

	public static function normalize_url_prefix( $raw ) {
		if ( ! is_string( $raw ) ) return '';
		$prefix = trim( $raw, " \t\n\r\0\x0B/" );
		if ( $prefix === '' ) return '';
		// rewrite/URL は通常 case-sensitive。'People' で登録すると /people/john にマッチしないため強制小文字化
		$prefix = strtolower( $prefix );
		// 英数 + - + _ + / のみ許可
		if ( ! preg_match( '#^[a-z0-9\-_/]+$#', $prefix ) ) return '';
		// 連続スラッシュ折りたたみ
		$prefix = preg_replace( '#/+#', '/', $prefix );
		// 予約 slug 排除 (各セグメントごと)
		$reserved = self::reserved_prefix_segments();
		foreach ( explode( '/', $prefix ) as $seg ) {
			if ( in_array( $seg, $reserved, true ) ) return '';
		}
		return $prefix;
	}

	/**
	 * (multiprefix V4): WP 予約 slug セグメント一覧 (prefix 登録時に弾く)
	 * normalize_url_prefix と register UI の両方から参照する単一ソース。
	 */
	public static function reserved_prefix_segments() {
		return array( 'feed', 'rdf', 'rss', 'rss2', 'atom', 'trackback', 'embed', 'page', 'comments', 'wp-sitemap.xml', 'wp-json', 'wp-login.php', 'wp-admin', 'robots.txt', 'sitemap.xml', 'favicon.ico', 'xmlrpc.php', 'wp-cron.php', 'wp-content', 'wp-includes', 'oembed' );
	}

	/**
	 * (multiprefix): プレフィックス・レジストリ取得
	 * option ksppm_settings['url_prefixes'] (string[]) を正規化・一意化して返す。
	 * 既定で 'profile' を必ず含み先頭に置く (V: profile デフォルト・削除不可)。
	 * 後方互換: 旧単一 url_prefix しか無い (未移行) 場合もここで吸収して配列化する。
	 *
	 * @return string[] 正規化済みプレフィックスの配列 (先頭 'profile')
	 */
	public static function get_url_prefixes() {
		$settings = get_option( KSPPM_OPTION_KEY, array() );
		$list = array();

		if ( isset( $settings['url_prefixes'] ) && is_array( $settings['url_prefixes'] ) ) {
			foreach ( $settings['url_prefixes'] as $raw ) {
				$n = self::normalize_url_prefix( (string) $raw );
				if ( $n !== '' ) {
					$list[] = $n;
				}
			}
		}

		// 後方互換: 旧単一 url_prefix が非空なら取り込む (未移行サイトでも動くように)。
		// ただし移行済み (multiprefix_migrated) なら旧値は既に url_prefixes に畳み込まれているため
		// ここで再注入しない。再注入するとレジストリから削除した旧 prefix が毎回復活して削除不能になる。
		if ( empty( $settings['multiprefix_migrated'] ) && ! empty( $settings['url_prefix'] ) ) {
			$legacy = self::normalize_url_prefix( (string) $settings['url_prefix'] );
			if ( $legacy !== '' ) {
				$list[] = $legacy;
			}
		}

		$default = self::default_url_prefix();
		array_unshift( $list, $default ); // profile を先頭に
		$list = array_values( array_unique( $list ) );

		// 上限 (rewrite rule 暴発防止)
		$max = defined( 'KSPPM_MAX_PREFIXES' ) ? (int) KSPPM_MAX_PREFIXES : 20;
		if ( count( $list ) > $max ) {
			$list = array_slice( $list, 0, $max );
		}
		return $list;
	}

	/**
	 * (multiprefix): デフォルトプレフィックス ('profile')。削除不可・常に先頭。
	 */
	public static function default_url_prefix() {
		$def = defined( 'KSPPM_DEFAULT_PREFIX' ) ? (string) KSPPM_DEFAULT_PREFIX : 'profile';
		$n = self::normalize_url_prefix( $def );
		return $n !== '' ? $n : 'profile';
	}

	/**
	 * (multiprefix): 投稿が使用するプレフィックスを取得
	 * 投稿メタ _ksppm_url_prefix を読み、レジストリに存在する値ならそれを、
	 * 未設定/無効/レジストリ外なら default ('profile') にフォールバック (V: 堅牢性)。
	 *
	 * @param int $post_id プロフィール投稿 ID
	 * @return string 正規化済みプレフィックス (必ずレジストリ内の値)
	 */
	public static function get_post_url_prefix( $post_id ) {
		$post_id = (int) $post_id;
		$registry = self::get_url_prefixes();
		$default  = self::default_url_prefix();
		if ( $post_id <= 0 ) {
			return $default;
		}
		$raw = get_post_meta( $post_id, KSPPM_META_PREFIX . 'url_prefix', true );
		$n   = self::normalize_url_prefix( (string) $raw );
		if ( $n !== '' && in_array( $n, $registry, true ) ) {
			return $n;
		}
		return $default;
	}

	/**
	 * (v0.0.20-beta): permalink 生成 helper を集約
	 */
	public static function build_permalink( $post_name, $post_id = 0 ) {
		if ( empty( $post_name ) ) return '';
		$settings = get_option( KSPPM_OPTION_KEY, array() );
		$enabled  = isset( $settings['root_rewrite_enabled'] ) ? (int) $settings['root_rewrite_enabled'] : 0;

		// (multiprefix): 投稿 ID が渡されたら投稿固有プレフィックスを使う。
		// post_id=0 (旧呼び出し) は後方互換で従来の単一 get_url_prefix() を使う。
		if ( $post_id > 0 ) {
			$prefix = self::get_post_url_prefix( $post_id );
		} else {
			$prefix = self::get_url_prefix();
		}

		if ( $prefix !== '' ) {
			$base = '/' . $prefix . '/' . $post_name . '/';
			$url  = home_url( $base );
		} elseif ( $enabled ) {
			$base = '/' . $post_name . '/';
			$url  = home_url( $base );
		} else {
			// root_rewrite_enabled=OFF かつ prefix 空: ルート公開を opt-out して query URL に切替
			$url = home_url( '/?' . KSPPM_CPT . '=' . rawurlencode( $post_name ) );
		}
		// Polylang/WPML 等の多言語プラグインが
		// 言語別 URL に書き換え可能な filter hook を提供。default は home_url() そのまま。
		return apply_filters( 'ksppm_profile_permalink', $url, $post_name, $prefix );
	}

	/**
	 * (multiprefix S3-0): canonical / legacy / query 形式 301 を統合した単一ハンドラ。
	 *
	 * 旧 canonical_query_redirect + legacy_prefix_redirect を 1 つに統合し、二重 301・
	 * リダイレクトループを構造的に防ぐ。リダイレクト先は常に「投稿の現プレフィックスの正規 URL」で一意。
	 *
	 * 評価順:
	 *   (A) 現在 singular CPT を表示中 → queried_object から post_id を取得。
	 *       これは以下を一括でカバーする:
	 *         - query 形式 /?ksppm_profile=slug (rewrite で singular CPT に解決される)
	 *         - 誤プレフィックス /people/{slug} だが投稿は /team/ を使用 (両方登録済なら CPT に解決) → canonical 301
	 *         - 登録済みの旧プレフィックス経由アクセス (legacy)
	 *         - root_rewrite 互換の /{slug}/ アクセス
	 *   (B) singular CPT で解決していない (= 登録レジストリから外れた旧プレフィックス等で 404) →
	 *       パス末尾セグメントを slug として CPT を直接解決し、先頭セグメントが
	 *       その投稿の _ksppm_url_prefix_history に含まれるなら現プレフィックスへ 301 (S3-1/S3-2)。
	 *
	 * ループ防止: 現在の URL が正規 URL と一致する場合は redirect しない ((A) の現プレフィックス一致時)。
	 */
	public static function canonical_redirect() {
		if ( is_admin() ) return;
		if ( ! function_exists( 'wp_safe_redirect' ) ) return;
		if ( empty( $_SERVER['REQUEST_URI'] ) ) return;

		// (M2): 派生エンドポイント・プレビューは正規 URL へ 301 しない。
		// is_singular(KSPPM_CPT) は feed/embed/trackback/comment-feed や下書きプレビュー
		// (?preview=true、is_preview()=true) でも併存し得るため、これらを 301 すると
		// フィード・埋め込み・プレビュー編集を壊す。先にガードする。
		if ( is_preview() || is_feed() || is_embed() || is_trackback() || is_comment_feed() ) return;

		$request_uri  = (string) wp_unslash( $_SERVER['REQUEST_URI'] );
		$request_path = wp_parse_url( $request_uri, PHP_URL_PATH );
		if ( ! is_string( $request_path ) ) $request_path = '';
		$request_query = wp_parse_url( $request_uri, PHP_URL_QUERY );

		// (m1): サブディレクトリ設置 (home_url が https://example.com/blog/ 等) に対応。
		// request_path / canonical_path を home 相対化し、(A) の path 比較と (B) の history 照合が
		// サブディレクトリ由来でズレないようにする (両方を同じ helper で strip して一貫性を保つ)。
		$home_path  = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		$strip_home = function ( $p ) use ( $home_path ) {
			$p = (string) $p;
			if ( $home_path === '' ) return $p;
			$t = ltrim( $p, '/' );
			if ( $t === $home_path ) return '/';
			if ( strpos( $t, $home_path . '/' ) === 0 ) return '/' . substr( $t, strlen( $home_path ) + 1 );
			return $p;
		};
		$request_path = $strip_home( $request_path );

		$post_id = 0;

		// (A) 通常解決: 現在 singular CPT を表示しているなら queried_object から post_id 取得。
		// (M2): post_status='publish' のみ対象 (下書き/pending を 301 しない、二重防御)。
		if ( is_singular( KSPPM_CPT ) ) {
			$obj = get_queried_object();
			if ( $obj instanceof WP_Post && $obj->post_type === KSPPM_CPT && $obj->post_status === 'publish' ) {
				$post_id = (int) $obj->ID;
			}
		}

		// (B) 履歴経路: singular CPT に解決していない (登録外の旧 prefix 等で 404)。
		// is_404() に限定し、200 で解決した通常 page/post への余計な DB クエリ・誤転送を避ける。
		if ( ! $post_id && is_404() ) {
			$path = trim( (string) $request_path, '/' );
			if ( $path !== '' && strpos( $path, '/' ) !== false ) {
				$segs       = explode( '/', $path );
				$cand_slug  = array_pop( $segs );
				$cand_prefix = implode( '/', $segs );
				if ( $cand_slug !== '' && preg_match( '/^[a-z0-9\-_]+$/i', $cand_slug ) ) {
					global $wpdb;
					$cid = (int) $wpdb->get_var( $wpdb->prepare(
						"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_name = %s AND post_status = 'publish' LIMIT 1",
						KSPPM_CPT,
						$cand_slug
					) );
					if ( $cid ) {
						$hist = get_post_meta( $cid, KSPPM_META_PREFIX . 'url_prefix_history', true );
						if ( is_array( $hist ) && in_array( $cand_prefix, $hist, true ) ) {
							$post_id = $cid;
						}
					}
				}
			}
		}

		if ( ! $post_id ) return;

		$post_name = get_post_field( 'post_name', $post_id );
		if ( empty( $post_name ) ) return;

		$canonical_url = self::build_permalink( $post_name, $post_id );
		if ( empty( $canonical_url ) ) return;

		// 正規 URL 自体が query 形式 (prefix 空 + root_rewrite OFF) の特殊ケースは
		// path 比較が成立しないため redirect しない (誤転送・ループ防止)
		$canonical_query = wp_parse_url( $canonical_url, PHP_URL_QUERY );
		if ( ! empty( $canonical_query ) && strpos( (string) $canonical_query, KSPPM_CPT . '=' ) !== false ) return;

		// (m1): canonical_path も home 相対化し、request_path と同じ基準で比較する。
		$canonical_path = $strip_home( wp_parse_url( $canonical_url, PHP_URL_PATH ) );

		// ループ防止 + (A2) query 温存: 既に正規 path でアクセス中の場合、
		// query 形式 (?ksppm_profile=) を正規化する目的の redirect のみ行い、
		// それ以外の query (?replytocom / ?amp / ?utm_* / 他プラグインの表示切替等) は温存する
		// (これらを 301 で全削除するとコメント返信・AMP・解析・連携機能を壊すため)。
		$req_norm = '/' . trim( (string) $request_path, '/' ) . '/';
		$can_norm = '/' . trim( (string) $canonical_path, '/' ) . '/';
		if ( $req_norm === $can_norm ) {
			// path は既に正規。query に ksppm_profile= が無ければ redirect しない (query 温存)。
			if ( empty( $request_query ) || strpos( (string) $request_query, KSPPM_CPT . '=' ) === false ) {
				return;
			}
		}

		wp_safe_redirect( $canonical_url, 301 );
		exit;
	}

	/**
	 * (multiprefix V6 / S1-3): 旧単一 url_prefix → 新 url_prefixes レジストリ + 投稿メタへの
	 * 1 回限りの移行。multiprefix_migrated フラグで冪等性を担保する。
	 *
	 * 手順:
	 *   1) url_prefixes が未設定なら ['profile'(+旧url_prefix)] を構築。
	 *   2) 全プロフィール投稿 (trash/auto-draft 除く) に _ksppm_url_prefix 未設定なら付与:
	 *      - 旧 url_prefix が非空 → その値 (既存 URL /{旧prefix}/{slug}/ を維持)。
	 *      - 旧 url_prefix が空    → profile (案X: 全投稿が最低 profile 配下に出る)。
	 *   3) multiprefix_migrated=1、flush_rewrite_rules。
	 * 旧 url_prefix / url_prefix_history キーはロールバック保険として残す。
	 */
	public static function maybe_migrate_multiprefix() {
		$settings = get_option( KSPPM_OPTION_KEY, array() );
		if ( ! is_array( $settings ) ) $settings = array();
		if ( ! empty( $settings['multiprefix_migrated'] ) ) return;

		$default = self::default_url_prefix();
		$legacy  = ! empty( $settings['url_prefix'] ) ? self::normalize_url_prefix( (string) $settings['url_prefix'] ) : '';

		// 1) レジストリ構築
		$prefixes = array( $default );
		if ( $legacy !== '' && ! in_array( $legacy, $prefixes, true ) ) {
			$prefixes[] = $legacy;
		}
		$settings['url_prefixes'] = array_values( array_unique( $prefixes ) );

		// 2) 投稿メタ backfill
		$assign = $legacy !== '' ? $legacy : $default;

		// (A1): 移行前の単一 url_prefix の変更履歴 (site-wide url_prefix_history) を
		// 投稿単位の履歴 _ksppm_url_prefix_history に引き継ぐ。これが無いと、移行前に
		// url_prefix を変更したことのあるサイトの旧 URL (/{旧prefix}/{slug}/) が移行後 404 になる
		// (旧 legacy_prefix_redirect は撤去済、新 canonical_redirect (B) は per-post 履歴のみ参照)。
		// 全投稿は移行前に同一の url_prefix を共有していたため、site-wide 履歴を各投稿の履歴に展開する。
		$site_hist = array();
		if ( ! empty( $settings['url_prefix_history'] ) && is_array( $settings['url_prefix_history'] ) ) {
			foreach ( $settings['url_prefix_history'] as $h ) {
				$hn = self::normalize_url_prefix( (string) $h );
				if ( $hn !== '' && $hn !== $assign && ! in_array( $hn, $site_hist, true ) ) {
					$site_hist[] = $hn;
				}
			}
			$site_hist = array_slice( $site_hist, 0, 5 );
		}

		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status NOT IN ('trash','auto-draft')",
			KSPPM_CPT
		) );
		if ( is_array( $ids ) ) {
			foreach ( $ids as $id ) {
				$id = (int) $id;
				$existing = self::normalize_url_prefix( (string) get_post_meta( $id, KSPPM_META_PREFIX . 'url_prefix', true ) );
				if ( $existing === '' ) {
					update_post_meta( $id, KSPPM_META_PREFIX . 'url_prefix', $assign );
					// 履歴未設定の投稿にのみ site-wide 履歴を seed (既存の per-post 履歴は尊重)
					if ( ! empty( $site_hist ) ) {
						$ph = get_post_meta( $id, KSPPM_META_PREFIX . 'url_prefix_history', true );
						if ( ! is_array( $ph ) || empty( $ph ) ) {
							update_post_meta( $id, KSPPM_META_PREFIX . 'url_prefix_history', $site_hist );
						}
					}
				}
			}
		}

		// 3) フラグ + flush
		$settings['multiprefix_migrated'] = 1;
		update_option( KSPPM_OPTION_KEY, $settings );

		add_action( 'shutdown', function () {
			if ( function_exists( 'flush_rewrite_rules' ) ) {
				flush_rewrite_rules( false );
			}
		} );
	}

	/**
	 * (F3) root_rewrite OFF のとき、bare 単一セグメント /{slug}/ が公開 CPT プロフィール slug に
	 * 一致する場合は WP コアの 404 推測 redirect (redirect_guess_404_permalink) を抑止する。
	 * これにより root 公開 opt-out を厳守 (bare URL を CPT 正規 URL へ 301 させない)。
	 * 通常 post/page の slug は対象外 (= 通常の 404 推測は維持)。
	 *
	 * @param bool $do guess を行うか
	 * @return bool
	 */
	public static function maybe_block_root_404_guess( $do ) {
		if ( ! $do ) return $do;
		$settings = get_option( KSPPM_OPTION_KEY, array() );
		if ( ! empty( $settings['root_rewrite_enabled'] ) ) return $do; // root ON: 通常通り
		if ( empty( $_SERVER['REQUEST_URI'] ) ) return $do;
		$path = wp_parse_url( (string) wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH );
		$path = trim( (string) $path, '/' );
		// サブディレクトリ設置の home パスを除去
		$home_path = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		if ( $home_path !== '' && strpos( $path, $home_path . '/' ) === 0 ) {
			$path = substr( $path, strlen( $home_path ) + 1 );
		}
		// 単一セグメントのみ対象 (prefix 付きは fallback_to_cpt が正規処理)
		if ( $path === '' || strpos( $path, '/' ) !== false ) return $do;
		if ( ! preg_match( '/^[a-z0-9\-_]+$/i', $path ) ) return $do;
		global $wpdb;
		$found = $wpdb->get_var( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_name = %s AND post_status = 'publish' LIMIT 1",
			KSPPM_CPT,
			$path
		) );
		return $found ? false : $do; // CPT プロフィール slug → guess 抑止 (404)、それ以外は通常 guess
	}

	/**
	 * (multiprefix V2): パス文字列の先頭が登録プレフィックスのいずれかに一致するか判定。
	 * longest-match: 多段 (team/about) と単段 (profile) が両方登録され得るため、長い順に照合。
	 * 一致したら ['prefix' => 一致prefix, 'slug' => prefixを剥がした単一slug] を返す。
	 * 剥がした後が単一セグメント (スラッシュ無し・非空) でなければ不一致扱い。
	 * 一致しなければ null。
	 *
	 * @param string $path 先頭スラッシュ無しのパス (例 "team/about/john")
	 * @return array{prefix:string,slug:string}|null
	 */
	public static function match_request_prefix( $path ) {
		$path = (string) $path;
		if ( $path === '' ) {
			return null;
		}
		$prefixes = self::get_url_prefixes();
		usort( $prefixes, function ( $a, $b ) {
			return strlen( $b ) - strlen( $a ); // 長い順 (longest-match)
		} );
		foreach ( $prefixes as $prefix ) {
			if ( $prefix === '' ) {
				continue;
			}
			if ( strpos( $path, $prefix . '/' ) === 0 ) {
				$slug = substr( $path, strlen( $prefix ) + 1 );
				$slug = trim( $slug, '/' );
				if ( $slug !== '' && strpos( $slug, '/' ) === false ) {
					return array( 'prefix' => $prefix, 'slug' => $slug );
				}
			}
		}
		return null;
	}

	/**
	 * Page と CPT slug が衝突した時の解決ロジック
	 * 1) WP 予約 slug は即座に CPT 解除 (feed/wp-sitemap.xml/robots.txt 等)
	 * 2) Page が存在すれば Page 優先 (pagename クエリに切替)
	 * 3) CPT post も Page も無ければ pagename クエリに切替 (404 は WP 標準処理)
	 */
	public static function fallback_to_cpt( $request ) {
		// CPT slug は (1) query_vars[KSPPM_CPT] (rewrite rule 経由) または (2) pagename (rewrite 不発時) から取得
		// custom capability_type で WP の自動 rewrite が想定通り動かないケースの保険として pagename も検査
		$settings        = get_option( KSPPM_OPTION_KEY, array() );
		$root_enabled    = isset( $settings['root_rewrite_enabled'] ) ? (int) $settings['root_rewrite_enabled'] : 0;
		$prefix_for_gate = self::default_url_prefix(); // (multiprefix) 常に profile があるので非空 = gate は通す
		// array attack 防御: ksppm_profile / pagename が array で来ても WP_Query で fatal を起こさない
		if ( isset( $request->query_vars[ KSPPM_CPT ] ) && ! is_scalar( $request->query_vars[ KSPPM_CPT ] ) ) {
			unset(
				$request->query_vars[ KSPPM_CPT ],
				$request->query_vars['post_type'],
				$request->query_vars['name']
			);
			return;
		}
		if ( isset( $request->query_vars['pagename'] ) && ! is_scalar( $request->query_vars['pagename'] ) ) {
			unset( $request->query_vars['pagename'] );
			return;
		}
		// F02: prefix 経由 (CPT 名前空間) で解決された slug かどうかを記録する。
		//   prefix は CPT 専用の名前空間 (/{prefix}/{slug}/) であり build_permalink() も
		//   この形を canonical URL として生成する。したがって prefix 配下では通常 post/page を
		//   先に解決すると、同名の publish post/page が存在する場合に profile の canonical URL が
		//   別投稿に奪われ到達不能になる。prefix 経由のときは post/page 優先をスキップして CPT を優先する。
		//   ※ prefix が付かない通常パスは従来どおり post/page 優先を維持 (既存 post/page のハイジャック防止)。
		$prefix       = ''; // (multiprefix) マッチした prefix を後で格納
		$prefixed_cpt = false;

		$slug = '';
		if ( ! empty( $request->query_vars[ KSPPM_CPT ] ) ) {
			// rewrite rule (add_root_rewrite) 経由。各 prefix の rewrite `^{prefix}/([^/]+)/?$` が
			// $matches[1] = prefix を剥がした単一 slug を query_var にセットする。
			// (multiprefix) 実際にマッチした prefix は REQUEST_URI から longest-match で特定する。
			$slug = $request->query_vars[ KSPPM_CPT ];
			$req_path = isset( $_SERVER['REQUEST_URI'] )
				? trim( (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ), '/' )
				: '';
			// (m1 同種 / subdir): サブディレクトリ設置 (home_url が /blog/ 等) では REQUEST_URI に
			// home パスが含まれ 'blog/profile/john' となり match_request_prefix が prefix を認識できず
			// $prefixed_cpt=false → 同名 post/page に canonical を奪われる。home パスを先頭 strip する。
			$home_path = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
			if ( $home_path !== '' ) {
				if ( $req_path === $home_path ) {
					// (F3 subdir): サブディレクトリ設置の明示クエリ形式 /blog/?ksppm_profile=slug は
					// path が home dir のみ ('blog')。home 相対化して '' にし、bare slug の opt-out 判定
					// ($req_path !== '') に誤ってヒットさせない (canonical 301 を維持)。
					$req_path = '';
				} elseif ( strpos( $req_path, $home_path . '/' ) === 0 ) {
					$req_path = substr( $req_path, strlen( $home_path ) + 1 );
				}
			}
			$m = self::match_request_prefix( $req_path );
			if ( $m ) {
				$prefix       = $m['prefix'];
				$prefixed_cpt = true;
			} elseif ( ! $root_enabled && $req_path !== '' ) {
				// (F3): query_var[KSPPM_CPT] が立っているのに登録プレフィックスに該当せず、
				// かつ REQUEST_URI のパスが非空 (= path 由来 = stale な root rewrite rule
				// ^([^/]+)/?$ 経由の bare /{slug}/) の場合のみ opt-out 適用。
				// $req_path==='' は明示クエリ形式 /?ksppm_profile=slug であり、これは canonical_redirect
				// で /profile/{slug}/ へ 301 すべきなので対象外 (誤って 404 化しない)。
				// root_rewrite OFF ならルート公開 opt-out を厳守して CPT 解決しない (CPT query_var を unset)。
				unset(
					$request->query_vars[ KSPPM_CPT ],
					$request->query_vars['post_type'],
					$request->query_vars['name']
				);
				return;
			}
		} elseif ( ! empty( $request->query_vars['pagename'] ) && empty( $request->query_vars['post_type'] ) ) {
			// rewrite 不発時の保険経路 (pagename に prefix 付きフルパスが来る)。
			$slug = (string) $request->query_vars['pagename'];
			// (M4 / V2 longest-match): pagename 経路 (custom/page mode で rewrite 不発時) でも
			// 登録プレフィックスを longest-match で特定する。これが無いと多段プレフィックス
			// (team/about/john) が strip されず CPT 検索に失敗し 404 になる。
			// $prefix を立てておけば下の strip ブロックが pagename から prefix を剥がす。
			$pm = self::match_request_prefix( trim( $slug, '/' ) );
			if ( $pm ) {
				$prefix = $pm['prefix'];
			} elseif ( ! $root_enabled ) {
				// (F3) ルート公開 opt-out 厳守: root_rewrite_enabled=OFF かつ登録プレフィックスに
				// 該当しない bare slug (/{slug}/) は CPT 解決しない。マルチプレフィックスで
				// default 'profile' が常に存在するため旧 $prefix_for_gate gate は死んでいた。
				// これにより root OFF 設定でも /{slug}/ が CPT に解決・301 されてしまう opt-out 破れを是正。
				return;
			}
		}
		$slug = (string) $slug;
		if ( $slug === '' ) {
			return;
		}

		// (v0.0.20-beta): url_prefix が設定されていれば pagename から prefix を strip
		// 例: pagename="people/john" + prefix="people" → slug="john"
		// 例: pagename="people/about/john" + prefix="people/about" → slug="john"
		// (pagename 経由で prefix 付きフルパスが来た場合。これも prefix 配下 = CPT 名前空間)
		if ( $prefix !== '' && strpos( $slug, $prefix . '/' ) === 0 ) {
			$candidate = substr( $slug, strlen( $prefix ) + 1 );
			// strip 後が単一セグメント (スラッシュなし、空でない) のときだけ採用
			if ( $candidate !== '' && strpos( $candidate, '/' ) === false ) {
				$slug         = $candidate;
				$prefixed_cpt = true;
			}
		}

		// (1) WP 予約 slug whitelist (CPT として処理せず WP 標準に委譲)
		// exact match: WP 標準パス
		$reserved = array(
			'feed', 'rdf', 'rss', 'rss2', 'atom', 'trackback', 'embed',
			'page', 'comments', 'comment-page-1',
			'wp-sitemap.xml', 'wp-json', 'wp-login.php', 'wp-admin',
			'robots.txt', 'sitemap.xml', 'sitemap_index.xml', 'favicon.ico',
			'xmlrpc.php', 'wp-cron.php', 'wp-content', 'wp-includes',
			'oembed',
		);
		if ( in_array( $slug, $reserved, true ) ) {
			// WP コアが自動セットする post_type/name も同時に unset (CPT 解決の残留防止)
			unset(
				$request->query_vars[ KSPPM_CPT ],
				$request->query_vars['post_type'],
				$request->query_vars['name']
			);
			return;
		}
		// prefix match: WP 5.5+ サブサイトマップ (wp-sitemap-posts-post-1.xml / wp-sitemap-users-1.xml 等)
		if ( preg_match( '/^wp-sitemap[\w\-]*\.xml$/', $slug ) ) {
			unset(
				$request->query_vars[ KSPPM_CPT ],
				$request->query_vars['post_type'],
				$request->query_vars['name']
			);
			return;
		}

		global $wpdb;
		// F02: prefix 経由でない (通常パス) 場合のみ post/page を優先解決する。
		//   prefix 配下 (/{prefix}/{slug}/) は CPT 名前空間なので post/page 優先をスキップし、
		//   下の (3) CPT 検索に直行する。これにより同名 post/page との衝突で profile の
		//   canonical URL が奪われる事故を防ぐ。
		if ( ! $prefixed_cpt ) {
			// (2a) 通常投稿 (post) 優先 (v0.0.14-beta)
			// 背景: permalink_structure が /%postname%/ の場合、通常投稿 URL も rewrite で CPT クエリに取られる。
			// 通常投稿が見つかれば CPT を解除して name クエリで通常投稿表示に委譲。
			// get_page_by_path は post_status を限定しないため draft/private/trash も hit する。
			// $wpdb 直クエリで post_type='post' AND post_status='publish' に絞り、attachment 等も排除。
			$normal_post_id = $wpdb->get_var( $wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post' AND post_name = %s AND post_status = 'publish' LIMIT 1",
				$slug
			) );
			if ( $normal_post_id ) {
				unset(
					$request->query_vars[ KSPPM_CPT ],
					$request->query_vars['post_type'],
					$request->query_vars['name']
				);
				$request->query_vars['name']      = $slug;
				$request->query_vars['post_type'] = 'post';
				return;
			}

			// (2) Page 優先: 同 slug の publish page があれば pagename に切替
			// (v0.0.18-beta): get_page_by_path は post_status を限定しないため
			// draft/private/trash の page も hit する → $wpdb 直クエリで post_status='publish' に絞る
			// (T1 で post 検索を $wpdb 直クエリにしたのと同じパターン、一貫性確保)
			$page_id = $wpdb->get_var( $wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_name = %s AND post_status = 'publish' LIMIT 1",
				$slug
			) );
			if ( $page_id ) {
				unset(
					$request->query_vars[ KSPPM_CPT ],
					$request->query_vars['post_type'],
					$request->query_vars['name']
				);
				$request->query_vars['pagename'] = $slug;
				return;
			}
		}

		// (3) CPT 検索 — $wpdb 直クエリで WP の認可/フィルタ層を完全 bypass
		// behavior=custom 時に WP_Query 認可で 0 件返却される事故への対応
		// post_status は publish のみに限定 (draft/private 漏洩防止)、編集権認可は Frontend: render で別途実施
		// ($wpdb は上の post/page 検索ブロックで既に global 宣言済)
		$found = $wpdb->get_var( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_name = %s AND post_status = 'publish' LIMIT 1",
			KSPPM_CPT,
			$slug
		) );
		if ( $found ) {
			// CPT 投稿が見つかった → query_vars を CPT に正規化 (pagename 経路で来た場合の補正)
			$request->query_vars[ KSPPM_CPT ] = $slug;
			$request->query_vars['post_type'] = KSPPM_CPT;
			$request->query_vars['name']      = $slug;
			unset( $request->query_vars['pagename'] );
		} else {
			// CPT 該当なし
			unset(
				$request->query_vars[ KSPPM_CPT ],
				$request->query_vars['post_type'],
				$request->query_vars['name']
			);
			// F02: prefix 経由で来たのに CPT が無い場合は、prefix を剥がした単一 slug を
			//   pagename にすると prefix 外の同名 page を誤表示する恐れがある。
			//   prefix は CPT 専用名前空間なので、本来の prefix 付きパスのまま 404 に委譲する
			//   (page へのフォールバックはしない)。通常パスは従来どおり pagename へ委譲。
			if ( $prefixed_cpt ) {
				$request->query_vars['pagename'] = trailingslashit( $prefix ) . $slug;
			} else {
				$request->query_vars['pagename'] = $slug;
			}
		}
	}

	/**
	 * main query で WP_Query が CPT 投稿を取得できない (capability/filter 干渉) 場合の保険
	 * is_singular(KSPPM_CPT) かつ posts 配列が空のとき、$wpdb 直クエリで WP_Post を強制注入
	 *
	 * (v0.0.13-beta): posts_pre_query から the_posts (空結果時のみ) に変更。
	 * これにより WP_Query の認可フィルタ・多言語 (Polylang/WPML)・AMP 等のフィルタ chain を通過する。
	 */
	public static function force_inject_cpt_post( $posts, $query ) {
		if ( ! ( $query instanceof WP_Query ) ) return $posts;
		if ( ! $query->is_main_query() ) return $posts;
		if ( empty( $query->query_vars['post_type'] ) || $query->query_vars['post_type'] !== KSPPM_CPT ) return $posts;
		// the_posts hook では posts が空配列のときだけ注入 (取得済なら何もしない)
		if ( ! empty( $posts ) ) return $posts;
		$slug = $query->query_vars['name'] ?? ( $query->query_vars[ KSPPM_CPT ] ?? '' );
		if ( empty( $slug ) ) return $posts;

		global $wpdb;
		$post_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_name = %s AND post_status = 'publish' LIMIT 1",
			KSPPM_CPT,
			$slug
		) );
		if ( empty( $post_id ) ) return $posts;

		$post = get_post( (int) $post_id );
		if ( ! $post || $post->post_status !== 'publish' ) return $posts;

		// 注入 + WP_Query の状態を整合
		$query->found_posts   = 1;
		$query->max_num_pages = 1;
		$query->is_singular   = true;
		$query->is_single     = false;
		$query->is_page       = false;
		$query->is_404        = false;
		$query->queried_object    = $post;
		$query->queried_object_id = (int) $post->ID;

		return array( $post );
	}

	public static function register_cpt() {
		$settings = get_option( KSPPM_OPTION_KEY, array() );
		$behavior = isset( $settings['post_type_behavior'] ) ? $settings['post_type_behavior'] : 'custom';
		if ( ! in_array( $behavior, array( 'custom', 'post', 'page' ), true ) ) {
			$behavior = 'custom';
		}

		$supports = array( 'title', 'thumbnail', 'revisions', 'author', 'editor', 'custom-fields' );

		// custom: 完全プラグイン独自 CPT (推奨)。WP 組み込みの post / page 権限と独立
		// post:   投稿型挙動 (時系列、テーマの single 系継承)
		// page:   固定ページ型挙動 (階層 post_parent / menu_order あり、テーマの page 系継承、本ファイル冒頭 docblock の D 案 3 機構で rewrite/canonical 衝突を解消済)
		if ( $behavior === 'custom' ) {
			$capability_type = array( 'ksppm_profile', 'ksppm_profiles' );
			$hierarchical    = false;
		} elseif ( $behavior === 'page' ) {
			$capability_type = 'page';
			// (v0.0.13-beta): page モードでも hierarchical=false 強制。
			// 親子階層のユースケースが想定し難く、rewrite が単一セグメントのみ対応のため不整合になる。
			// page-attributes support も削除して親子 UI を非表示。
			$hierarchical    = false;
		} else { // post
			$capability_type = 'post';
			$hierarchical    = false;
		}

		register_post_type(
			KSPPM_CPT,
			array(
				'labels'              => array(
					'name'                  => __( 'プロフィール', 'kashiwazaki-seo-profilepage-maker' ),
					'singular_name'         => __( 'プロフィール', 'kashiwazaki-seo-profilepage-maker' ),
					'menu_name'             => __( 'プロフィール', 'kashiwazaki-seo-profilepage-maker' ),
					'add_new'               => __( '新規追加', 'kashiwazaki-seo-profilepage-maker' ),
					'add_new_item'          => __( '新規プロフィール', 'kashiwazaki-seo-profilepage-maker' ),
					'edit_item'             => __( 'プロフィールを編集', 'kashiwazaki-seo-profilepage-maker' ),
					'view_item'             => __( 'プロフィールを表示', 'kashiwazaki-seo-profilepage-maker' ),
					'all_items'             => __( 'プロフィール一覧', 'kashiwazaki-seo-profilepage-maker' ),
				),
				'public'              => true,
				'show_ui'             => false, // 専用 admin page で完結、WP 標準 post.php UI を非表示
				'show_in_menu'        => false,
				'show_in_admin_bar'   => false,
				'show_in_rest'        => true,
				'rest_base'           => 'ksppm-profile',
				'menu_icon'           => 'dashicons-id-alt',
				'menu_position'       => apply_filters( 'ksppm_menu_position', 81 ),
				'supports'            => $supports,
				'has_archive'         => false,
				// 各プロフィールは個別 slug (post_name) で公開: /{slug}/
				// WP のデフォルト rewrite は無効化、自前で rewrite + permalink フィルタで制御
				'rewrite'             => false,
				'query_var'           => true,
				'capability_type'     => $capability_type,
				'hierarchical'        => $hierarchical,
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * カスタム capability を管理者・編集者ロールに付与 (behavior=custom 時必須)
	 * activation_hook + post_type_behavior 変更時に呼ばれる
	 */
	public static function grant_custom_capabilities() {
		$caps = array(
			'edit_ksppm_profile',
			'read_ksppm_profile',
			'delete_ksppm_profile',
			'edit_ksppm_profiles',
			'edit_others_ksppm_profiles',
			'publish_ksppm_profiles',
			'read_private_ksppm_profiles',
			'delete_ksppm_profiles',
			'delete_private_ksppm_profiles',
			'delete_published_ksppm_profiles',
			'delete_others_ksppm_profiles',
			'edit_private_ksppm_profiles',
			'edit_published_ksppm_profiles',
		);
		foreach ( array( 'administrator', 'editor' ) as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) continue;
			foreach ( $caps as $cap ) {
				$role->add_cap( $cap );
			}
		}
	}

	/**
	 * カスタム capability を削除 (behavior 変更時のクリーンアップ用、現状は呼ばない)
	 */
	public static function revoke_custom_capabilities() {
		$caps = array(
			'edit_ksppm_profile', 'read_ksppm_profile', 'delete_ksppm_profile',
			'edit_ksppm_profiles', 'edit_others_ksppm_profiles', 'publish_ksppm_profiles',
			'read_private_ksppm_profiles', 'delete_ksppm_profiles',
			'delete_private_ksppm_profiles', 'delete_published_ksppm_profiles',
			'delete_others_ksppm_profiles', 'edit_private_ksppm_profiles',
			'edit_published_ksppm_profiles',
		);
		foreach ( array( 'administrator', 'editor' ) as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) continue;
			foreach ( $caps as $cap ) {
				$role->remove_cap( $cap );
			}
		}
	}

}
