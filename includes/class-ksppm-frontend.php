<?php
/**
 * フロント表示 (FRO-13 single template, FRO-17 JSON-LD 注入は class-ksppm-schema)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KSPPM_Frontend {

	public static function init() {
		add_filter( 'single_template', array( __CLASS__, 'single_template' ) );
		// F04: priority 9 だと wpautop (priority 10) より前に走り、render() が出力する
		// <section>/<table>/<dl>/<header>/<article> 等のブロック要素にも <p>/<br> が挿入され
		// 不正な HTML を生成するリスクがあった。priority 11 で wpautop 後に置換することで、
		// inject_content の return がそのまま最終 the_content となり、wpautop の干渉を完全に避ける。
		add_filter( 'the_content', array( __CLASS__, 'inject_content' ), 11 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	public static function single_template( $template ) {
		if ( is_singular( KSPPM_CPT ) ) {
			$theme_template = locate_template( array( 'kashiwazaki-profile/single-' . KSPPM_CPT . '.php', 'single-' . KSPPM_CPT . '.php' ) );
			if ( $theme_template ) return $theme_template;
		}
		return $template;
	}

	/**
	 * CPT 本体の post_content を render 結果で置換し、テーマの single.php / page.php を経由させる
	 * これによりサイドバー / フィードバックウィジェット / 編集リンク / two-columns レイアウト等のテーマ機能を維持
	 */
	public static function inject_content( $content ) {
		if ( ! in_the_loop() || ! is_main_query() ) return $content;
		if ( ! is_singular( KSPPM_CPT ) ) return $content;
		$post_id = get_the_ID();
		if ( ! $post_id ) return $content;
		// (v0.0.26-beta + post_password fix): パスワード保護付き投稿は WordPress 標準のパスワード入力フォームを返す。
		// schema.php 側には既に post_password_required() ガードがあるが、フロント本文側には無かった。
		// これが無いと post_password を設定しても本文/meta が未認証閲覧者に表示されてしまう。
		if ( post_password_required( $post_id ) ) return $content;
		return self::render( $post_id, array( 'section' => 'all' ) );
	}

	public static function enqueue() {
		if ( is_singular( KSPPM_CPT ) || self::is_shortcoded() ) {
			// v0.0.25-beta: SNS アイコン (dashicons) を有効化
			wp_enqueue_style( 'dashicons' );
			wp_enqueue_style(
				'ksppm-front',
				KSPPM_PLUGIN_URL . 'assets/css/front.css',
				array( 'dashicons' ),
				KSPPM_VERSION
			);
		}
	}

	/**
	 * v0.0.25-beta (Kashiwazaki SEO Author Schema Display 由来):
	 * URL のホスト名から dashicons CSS class を返す。SNS / 外部プロフィールのアイコン表示用。
	 */
	public static function sns_icon_class( $url ) {
		if ( empty( $url ) ) return 'dashicons-admin-site';
		$host = strtolower( wp_parse_url( $url, PHP_URL_HOST ) ?: '' );
		$host_without_www = preg_replace( '/^www\./', '', $host );

		$icon_map = array(
			'facebook.com' => 'dashicons-facebook-alt', 'fb.watch' => 'dashicons-facebook-alt', 'fb.me' => 'dashicons-facebook-alt',
			'x.com' => 'dashicons-twitter-alt', 'twitter.com' => 'dashicons-twitter-alt',
			'instagram.com' => 'dashicons-instagram', 'instagr.am' => 'dashicons-instagram',
			'linkedin.com' => 'dashicons-linkedin',
			'youtube.com' => 'dashicons-youtube', 'youtu.be' => 'dashicons-youtube',
			'pinterest.' => 'dashicons-pinterest', 'tumblr.com' => 'dashicons-tumblr', 'reddit.com' => 'dashicons-reddit',
			'tiktok.com' => 'dashicons-format-video', 'snapchat.com' => 'dashicons-camera-alt',
			'whatsapp.com' => 'dashicons-whatsapp', 'wa.me' => 'dashicons-whatsapp',
			't.me' => 'dashicons-telegram', 'telegram.org' => 'dashicons-telegram', 'telegram.me' => 'dashicons-telegram',
			'discord.com' => 'dashicons-format-chat', 'discord.gg' => 'dashicons-format-chat',
			'threads.net' => 'dashicons-format-status', 'behance.net' => 'dashicons-portfolio', 'dribbble.com' => 'dashicons-admin-customizer',
			'github.com' => 'dashicons-editor-code', 'gist.github.com' => 'dashicons-editor-code',
			'gitlab.com' => 'dashicons-editor-code', 'bitbucket.org' => 'dashicons-editor-code',
			'stackoverflow.com' => 'dashicons-editor-help', 'stackexchange.com' => 'dashicons-editor-help',
			'medium.com' => 'dashicons-edit', 'slideshare.net' => 'dashicons-slides',
			'flickr.com' => 'dashicons-camera', 'vimeo.com' => 'dashicons-vimeo',
			'soundcloud.com' => 'dashicons-format-audio', 'spotify.com' => 'dashicons-spotify', 'spotify.link' => 'dashicons-spotify',
			'last.fm' => 'dashicons-format-audio', 'bandcamp.com' => 'dashicons-format-audio',
			'deviantart.com' => 'dashicons-art',
			'researchgate.net' => 'dashicons-welcome-learn-more', 'academia.edu' => 'dashicons-welcome-learn-more',
			'kaggle.com' => 'dashicons-chart-bar', 'goodreads.com' => 'dashicons-book-alt',
			'letterboxd.com' => 'dashicons-format-video',
			'note.com' => 'dashicons-welcome-write-blog', 'note.mu' => 'dashicons-welcome-write-blog',
			'qiita.com' => 'dashicons-lightbulb', 'zenn.dev' => 'dashicons-lightbulb',
			'hatenablog.com' => 'dashicons-edit', 'hatenablog.jp' => 'dashicons-edit', 'b.hatena.ne.jp' => 'dashicons-admin-links',
			'line.me' => 'dashicons-format-chat', 'line.blog' => 'dashicons-format-chat', 'line.naver.jp' => 'dashicons-format-chat',
			'mixi.jp' => 'dashicons-groups',
			'weibo.com' => 'dashicons-share', 'weibo.cn' => 'dashicons-share',
			'qq.com' => 'dashicons-format-chat', 'qzone.qq.com' => 'dashicons-format-gallery',
			'wechat.com' => 'dashicons-format-chat',
			'douyin.com' => 'dashicons-format-video', 'kuaishou.com' => 'dashicons-format-video',
			'mastodon.' => 'dashicons-share', 'misskey.' => 'dashicons-share-alt2',
			'gettr.com' => 'dashicons-format-status', 'truthsocial.com' => 'dashicons-format-status',
			'gab.com' => 'dashicons-format-status', 'mewe.com' => 'dashicons-groups',
			'parler.com' => 'dashicons-format-status', 'minds.com' => 'dashicons-lightbulb',
			'twitch.tv' => 'dashicons-video-alt',
			'viber.com' => 'dashicons-format-chat', 'skype.com' => 'dashicons-format-chat',
			'teams.microsoft.com' => 'dashicons-groups', 'zoom.us' => 'dashicons-video-alt3',
			'clubhouse.com' => 'dashicons-microphone', 'signal.org' => 'dashicons-lock',
			'patreon.com' => 'dashicons-money-alt', 'pixiv.net' => 'dashicons-admin-customizer',
			'etsy.com' => 'dashicons-cart', 'amazon.' => 'dashicons-cart',
			'imdb.com' => 'dashicons-format-video',
			'wikipedia.org' => 'dashicons-book', 'ja.wikipedia.org' => 'dashicons-book',
			'wordpress.org' => 'dashicons-wordpress', 'wordpress.com' => 'dashicons-wordpress', 'profiles.wordpress.org' => 'dashicons-wordpress',
			'scholar.google.com' => 'dashicons-welcome-learn-more', 'scholar.google.co.jp' => 'dashicons-welcome-learn-more',
			'soundcloud.app.goo.gl' => 'dashicons-format-audio',
			'bit.ly' => 'dashicons-admin-links', 'is.gd' => 'dashicons-admin-links', 'tinyurl.com' => 'dashicons-admin-links',
			'ow.ly' => 'dashicons-admin-links', 'buff.ly' => 'dashicons-admin-links',
			'feedly.com' => 'dashicons-rss', 'bloglovin.com' => 'dashicons-heart',
			'lit.link' => 'dashicons-admin-links', 'linktr.ee' => 'dashicons-admin-links', 'newspicks.com' => 'dashicons-megaphone',
		);

		if ( isset( $icon_map[ $host ] ) ) return $icon_map[ $host ];
		if ( isset( $icon_map[ $host_without_www ] ) ) return $icon_map[ $host_without_www ];
		foreach ( $icon_map as $domain_start => $icon_class ) {
			if ( str_ends_with( $domain_start, '.' ) ) {
				$check = rtrim( $domain_start, '.' );
				if ( $host === $check || strpos( $host, $domain_start ) === 0 ) return $icon_class;
				if ( $host_without_www === $check || strpos( $host_without_www, $domain_start ) === 0 ) return $icon_class;
			}
		}
		if ( strpos( $url, 'mailto:' ) === 0 ) return 'dashicons-email-alt';
		return 'dashicons-admin-site';
	}

	protected static function is_shortcoded() {
		global $post;
		if ( ! is_a( $post, 'WP_Post' ) ) return false;
		// (v0.0.18-beta): ksppm_profile alias の CSS enqueue 漏れ修正
		return has_shortcode( $post->post_content, 'kashiwazaki_profilepage' )
			|| has_shortcode( $post->post_content, 'kashiwazaki_profile' )
			|| has_shortcode( $post->post_content, 'ksppm_profile' );
	}

	/**
	 * 不正な HTML fragment (閉じタグ欠落・table 未閉鎖等) を well-formed に正規化
	 * 背景: 古い tinyMCE エディタで作られた meta 値が `<table style="..."><tr><th>...` で閉じタグ欠落していると、
	 *       ブラウザの auto-close が後続要素 (テーマのサイドバー等) を巻き込んで構造破壊する。
	 *       DOMDocument の loadHTML は SGML パーサで auto-close を補完するため、これで wrap して正規化する。
	 *
	 * @param string $html 入力 HTML fragment
	 * @return string 正規化された HTML fragment (DOMDocument 不在環境では原文返却)
	 */
	public static function normalize_html_fragment( $html ) {
		if ( empty( $html ) || ! is_string( $html ) || ! class_exists( 'DOMDocument' ) ) {
			return $html;
		}
		/*
		 * (v0.0.13-beta): PHP 8.1+ で deprecated な xml prolog prepend 方式を
		 * mb_encode_numericentity で multi-byte 文字を numeric entity に変換する方式に置換。
		 * mb_encode_numericentity は PHP 5.0+ で安定、deprecated 予定なし。
		 * 旧 mb_convert_encoding HTML-ENTITIES は PHP 8.2+ で deprecated のため不採用。
		 */
		if ( function_exists( 'mb_encode_numericentity' ) ) {
			$html_encoded = mb_encode_numericentity( $html, array( 0x80, 0x10FFFF, 0, 0xFFFFFF ), 'UTF-8' );
		} else {
			// mb_string 拡張不在環境への fallback (極稀、ほぼ全 WP 環境で利用可能)
			$html_encoded = $html;
		}
		$previous_use = libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$wrapped = '<div id="ksppm-norm-wrap">' . $html_encoded . '</div>';
		$dom->loadHTML( $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous_use );
		// F05: LIBXML_HTML_NODEFDTD で DTD がロードされないため、libxml バージョンによっては
		// getElementById() が NULL を返す。XPath で id="ksppm-norm-wrap" を直接探す fallback を実装し、
		// それでも見つからなければ DOM root の childNodes (= wrapper div) を取って中身を取り出す。
		$wrap = $dom->getElementById( 'ksppm-norm-wrap' );
		if ( ! $wrap ) {
			$xpath = new DOMXPath( $dom );
			$nodes = $xpath->query( '//*[@id="ksppm-norm-wrap"]' );
			if ( $nodes && $nodes->length > 0 ) {
				$wrap = $nodes->item( 0 );
			}
		}
		if ( ! $wrap ) {
			// 最終 fallback: NOIMPLIED で wrapper div が DOM root の直下にあるはずなので、それを取得
			foreach ( $dom->childNodes as $child ) {
				if ( $child instanceof DOMElement && $child->getAttribute( 'id' ) === 'ksppm-norm-wrap' ) {
					$wrap = $child;
					break;
				}
			}
		}
		if ( ! $wrap ) {
			return $html;
		}
		$out = '';
		foreach ( $wrap->childNodes as $node ) {
			$out .= $dom->saveHTML( $node );
		}
		return $out;
	}

	/**
	 * プレーンテキストを出力用に整える。全体を HTML エスケープしたうえで、
	 * 「[表示名](https://example.com/)」の書き方だけをリンクに変える。
	 *
	 * - HTML タグは使えない (すべてエスケープされて文字として表示される)。
	 * - リンク先は http / https のみ。それ以外 (javascript: 等) はリンクにせず文字のまま残す。
	 * - 戻り値はエスケープ済みの HTML。呼び出し側でさらにエスケープしない。
	 *
	 * @param string $text 入力テキスト (保存値そのまま)
	 * @return string エスケープ済み HTML
	 */
	public static function format_text_with_links( $text ) {
		$escaped = esc_html( (string) $text );
		if ( strpos( $escaped, '](' ) === false ) {
			return $escaped;
		}
		$converted = preg_replace_callback(
			'/\[([^\[\]\r\n]+)\]\((https?:\/\/[^\s()]+)\)/u',
			function ( $m ) {
				// $m はエスケープ後の文字列から取っているので、URL だけ実体参照を戻してから esc_url にかける。
				$url = esc_url( html_entity_decode( $m[2], ENT_QUOTES, 'UTF-8' ), array( 'http', 'https' ) );
				if ( '' === $url ) {
					return $m[0];
				}
				return '<a href="' . $url . '" target="_blank" rel="noopener">' . $m[1] . '</a>';
			},
			$escaped
		);
		// 不正な UTF-8 などで preg が失敗したら、リンク化せずエスケープ済みの文字列を返す。
		return is_string( $converted ) ? $converted : $escaped;
	}

	/**
	 * 在任期間の表示文字列を作る (「開始日 ～ 終了日」)。
	 *
	 * 現職 ($is_current = true) で終了日が空のときは、終了日の位置に「現在」を出す。
	 * 開始日も終了日も空なら空文字を返す (全行が空なら列ごと出さない判定に使う)。
	 * 戻り値はエスケープしていないので、出力側で esc_html() にかけること。
	 *
	 * @param string $start      開始日 (保存値そのまま)
	 * @param string $end        終了日 (保存値そのまま)
	 * @param bool   $is_current 現職の行なら true
	 * @return string 表示文字列 (未エスケープ)
	 */
	public static function format_period( $start, $end, $is_current = false ) {
		$start = trim( (string) $start );
		$end   = trim( (string) $end );
		if ( '' === $end && $is_current && '' !== $start ) {
			$end = __( '現在', 'kashiwazaki-seo-profilepage-maker' );
		}
		if ( '' === $start && '' === $end ) {
			return '';
		}
		/* translators: 1: start date, 2: end date (or the word for "present") */
		return trim( sprintf( __( '%1$s ～ %2$s', 'kashiwazaki-seo-profilepage-maker' ), $start, $end ) );
	}

	/**
	 * Render the profile (used by both single template and shortcode)
	 *
	 * 認可: post_type が KSPPM_CPT 以外 / draft/private を read 権限なしで表示しないよう保護
	 */
	public static function render( $post_id, $args = array() ) {
		$post_id = absint( $post_id );
		if ( ! $post_id ) return '';
		$post = get_post( $post_id );
		if ( ! $post || $post->post_type !== KSPPM_CPT ) return '';
		if ( $post->post_status !== 'publish' ) {
			// draft/private/pending/future 等は read_post 権限を持つユーザーのみ表示
			if ( ! current_user_can( 'read_post', $post_id ) ) return '';
		}
		// F4: パスワード保護プロフィールの PII 漏洩防止。inject_content には既にガードがあるが、
		// render() (= ショートコード [kashiwazaki_profilepage]/[ksppm_profile] や直接呼出) 経路には
		// 無かったため、任意ページ埋め込みで保護プロフィールの内容が露出していた。
		if ( post_password_required( $post_id ) ) return '';

		$args = wp_parse_args( $args, array(
			'section'   => 'all', // all|basic|career|expertise|works|tools|media|contact
			'layout'    => 'default',
			'show_toc'  => true,
			// F16: shortcode embed 時は h1→h2 シフトで a11y h1 重複を回避
			'heading_offset' => is_singular( KSPPM_CPT ) ? 0 : 1,
		) );
		$data = KSPPM_Meta::get_all( $post_id );

		// (v0.0.21-beta): display_order_preset でテンプレ切替
		// page7_like: 自己紹介→動画→現職→経歴→趣味→ツール→商標→論文→書籍→取材→講演 (page 7 順)
		// default: 既存テンプレ (経歴→学歴→専門性→書籍→論文→特許→商標→取材→講演→ツール→動画→趣味)
		$preset = get_option( KSPPM_OPTION_KEY, array() )['display_order_preset'] ?? 'default';
		$template = ( $preset === 'page7_like' && file_exists( KSPPM_PLUGIN_DIR . 'templates/profile-content-page7-like.php' ) )
			? 'templates/profile-content-page7-like.php'
			: 'templates/profile-content.php';

		ob_start();
		include KSPPM_PLUGIN_DIR . $template;
		return ob_get_clean();
	}
}
