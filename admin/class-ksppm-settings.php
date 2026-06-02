<?php
/**
 * プラグイン専用 管理 UI (一覧 / 新規 / 編集 / 保存 / 削除 を 1 つの admin page で完結)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KSPPM_Settings {

	const PAGE_SLUG = 'kashiwazaki-seo-profilepage-maker';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ), 9 );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_flush_rewrite' ) );
		// 設定変更時に JSON-LD transient 全 flush
		add_action( 'update_option_' . KSPPM_OPTION_KEY, array( 'KSPPM_Schema', 'flush_all_cache' ) );
		add_action( 'add_option_' . KSPPM_OPTION_KEY, array( 'KSPPM_Schema', 'flush_all_cache' ) );
		// admin-post.php 経由でフォーム保存を受ける
		add_action( 'admin_post_ksppm_save', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_ksppm_delete', array( __CLASS__, 'handle_delete' ) );
	}

	public static function maybe_flush_rewrite() {
		if ( get_option( 'ksppm_needs_flush' ) ) {
			flush_rewrite_rules();
			delete_option( 'ksppm_needs_flush' );
		}
	}

	public static function add_menu() {
		// F07: capability 設計を整理。
		// 親メニュー + 一覧 + 新規 = CPT の edit_posts 相当 (editor 等の編集者も到達可能)。
		// 設定のみ manage_options で明示ガード (admin only)。
		// CPT capability_type が custom の場合は post_type_object->cap->edit_posts (= edit_ksppm_profiles 等) を優先使用。
		$post_type_object = get_post_type_object( KSPPM_CPT );
		$cap_edit_posts   = $post_type_object && isset( $post_type_object->cap->edit_posts )
			? $post_type_object->cap->edit_posts
			: 'edit_posts';
		add_menu_page(
			'Kashiwazaki SEO ProfilePage Maker',
			'Kashiwazaki SEO ProfilePage Maker',
			$cap_edit_posts,
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' ),
			'dashicons-id-alt',
			81
		);
		add_submenu_page(
			self::PAGE_SLUG,
			__( 'プロフィール一覧', 'kashiwazaki-seo-profilepage-maker' ),
			__( 'プロフィール一覧', 'kashiwazaki-seo-profilepage-maker' ),
			$cap_edit_posts,
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
		add_submenu_page(
			self::PAGE_SLUG,
			__( '新規プロフィール', 'kashiwazaki-seo-profilepage-maker' ),
			__( '新規プロフィール', 'kashiwazaki-seo-profilepage-maker' ),
			$cap_edit_posts,
			self::PAGE_SLUG . '&action=new',
			array( __CLASS__, 'render_page' )
		);
		add_submenu_page(
			self::PAGE_SLUG,
			__( '設定', 'kashiwazaki-seo-profilepage-maker' ),
			__( '設定', 'kashiwazaki-seo-profilepage-maker' ),
			'manage_options',
			self::PAGE_SLUG . '&action=settings',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function register_settings() {
		register_setting( 'ksppm_settings_group', KSPPM_OPTION_KEY, array(
			'type'              => 'array',
			'sanitize_callback' => array( __CLASS__, 'sanitize' ),
		) );
	}

	/**
	 * (multiprefix V5): 指定プレフィックスを使用中のプロフィール投稿数を数える。
	 * trash / auto-draft を除く全 status (publish/draft/private/pending) が対象。
	 *
	 * @param string $prefix 正規化済みプレフィックス
	 * @return int 使用中の投稿数
	 */
	public static function count_profiles_using_prefix( $prefix ) {
		global $wpdb;
		$prefix = (string) $prefix;
		if ( $prefix === '' ) {
			return 0;
		}
		$meta_key = KSPPM_META_PREFIX . 'url_prefix';
		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
			 WHERE p.post_type = %s
			   AND p.post_status NOT IN ('trash','auto-draft')
			   AND m.meta_key = %s
			   AND m.meta_value = %s",
			KSPPM_CPT,
			$meta_key,
			$prefix
		) );
		return (int) $count;
	}

	/**
	 * (multiprefix V4): プレフィックスが既存の publish 固定ページと同名 (同パス) かどうか。
	 * 例: 固定ページ /team/about/ が存在する状態で prefix "team/about" を登録しようとすると衝突。
	 * get_page_by_path は多段パスにも対応する。予約 slug 衝突は normalize_url_prefix が別途排除済み。
	 *
	 * @param string $prefix 正規化済みプレフィックス
	 * @return bool 衝突するなら true
	 */
	public static function prefix_collides_with_page( $prefix ) {
		$prefix = (string) $prefix;
		if ( $prefix === '' ) {
			return false;
		}
		$page = get_page_by_path( $prefix, OBJECT, 'page' );
		return ( $page instanceof WP_Post && $page->post_status === 'publish' );
	}

	public static function sanitize( $input ) {
		// sanitize 冒頭で $prev を取得しないと url_prefix 履歴と flush_rewrite_rules 判定が破綻する
		$prev = get_option( KSPPM_OPTION_KEY, array() );

		$out = array();
		$out['enable_jsonld']      = isset( $input['enable_jsonld'] ) ? 1 : 0;
		$out['enable_breadcrumb']  = isset( $input['enable_breadcrumb'] ) ? 1 : 0;
		$out['enable_speakable']   = isset( $input['enable_speakable'] ) ? 1 : 0;
		$out['main_profile_post']  = isset( $input['main_profile_post'] ) ? absint( $input['main_profile_post'] ) : 0;
		$out['jsonld_position']    = isset( $input['jsonld_position'] ) && in_array( $input['jsonld_position'], array( 'head', 'footer' ), true ) ? $input['jsonld_position'] : 'head';
		$out['post_type_behavior'] = isset( $input['post_type_behavior'] ) && in_array( $input['post_type_behavior'], array( 'custom', 'post', 'page' ), true ) ? $input['post_type_behavior'] : 'custom';
		// (v0.0.18-beta): root_rewrite_enabled をホワイトリストに追加
		// (未追加だと sanitize で wipe され永久 OFF 化される regression があった、code review)
		$out['root_rewrite_enabled'] = isset( $input['root_rewrite_enabled'] ) ? 1 : 0;
		// (F3): root_rewrite_enabled の変更時は rewrite を flush する。これが無いと OFF にしても
		// 旧 root rewrite rule (^([^/]+)/?$) が rewrite_rules option に残り、bare /{slug}/ が
		// query_var 経由で CPT 解決され opt-out が効かない (fallback_to_cpt の F3 ガードは二重防御)。
		$prev_root = isset( $prev['root_rewrite_enabled'] ) ? (int) $prev['root_rewrite_enabled'] : 0;
		if ( $prev_root !== (int) $out['root_rewrite_enabled'] ) {
			static $flush_registered_root = false;
			if ( ! $flush_registered_root ) {
				$flush_registered_root = true;
				add_action( 'shutdown', function () {
					if ( function_exists( 'flush_rewrite_rules' ) ) {
						flush_rewrite_rules( false );
					}
				} );
			}
		}

		// (v0.0.20-beta): url_prefix サブディレクトリ
		// バリデーション + 履歴保存 (301 redirect 用、最大 5 件) + 変更時 flush_rewrite_rules
		// prev も normalize 経由 (旧 DB 値が直書きで invalid な可能性に対する自己防御)
		$prev_prefix_raw = isset( $prev['url_prefix'] ) ? (string) $prev['url_prefix'] : '';
		$prev_prefix = KSPPM_CPT::normalize_url_prefix( $prev_prefix_raw );
		// (multiprefix): 単一 url_prefix の入力欄は設定 UI から撤去 (レジストリに移行)。
		// 入力が来ない場合は旧値を維持し、レガシー履歴ロジックを破壊しない (ロールバック保険)。
		$raw_prefix  = isset( $input['url_prefix'] ) ? (string) $input['url_prefix'] : $prev_prefix_raw;
		$new_prefix  = KSPPM_CPT::normalize_url_prefix( $raw_prefix );
		$out['url_prefix'] = $new_prefix;

		// 排他チェック (T6): url_prefix が非空の場合 root_rewrite_enabled を強制 OFF
		if ( $new_prefix !== '' ) {
			$out['root_rewrite_enabled'] = 0;
		}

		// T9: prefix 履歴 (301 redirect 用、最大 5 件、現 prefix は除く、空 prefix は履歴対象外)
		$history = isset( $prev['url_prefix_history'] ) && is_array( $prev['url_prefix_history'] ) ? $prev['url_prefix_history'] : array();
		if ( $prev_prefix !== '' && $prev_prefix !== $new_prefix ) {
			$history = array_values( array_diff( $history, array( $prev_prefix, $new_prefix ) ) );
			array_unshift( $history, $prev_prefix );
			$history = array_slice( $history, 0, 5 );
		} else {
			$history = array_values( array_diff( $history, array( $new_prefix ) ) );
		}
		$out['url_prefix_history'] = $history;

		// (multiprefix S4-1/S4-2/V4/V5): url_prefixes レジストリの sanitize
		// 入力 (url_prefixes[]) を正規化・重複/予約/既存ページ衝突排除・上限適用し、
		// profile を必ず先頭に固定。使用中プレフィックスの削除は案 A で拒否 (復活 + 警告)。
		// M3 補強: array_filter のコールバック無しは正規化済みの有効 prefix "0" (文字列) も
		// falsy 扱いで落とすため、空文字のみを除外する厳密比較コールバックを使う。
		$prev_registry = ( isset( $prev['url_prefixes'] ) && is_array( $prev['url_prefixes'] ) )
			? array_values( array_unique( array_filter(
				array_map( array( 'KSPPM_CPT', 'normalize_url_prefix' ), $prev['url_prefixes'] ),
				function ( $p ) { return $p !== ''; }
			) ) )
			: array();
		$default_prefix = KSPPM_CPT::default_url_prefix();
		$max_prefixes   = defined( 'KSPPM_MAX_PREFIXES' ) ? (int) KSPPM_MAX_PREFIXES : 20;

		if ( isset( $input['url_prefixes'] ) && is_array( $input['url_prefixes'] ) ) {
			$clean    = array( $default_prefix );
			$rejected = array();
			foreach ( $input['url_prefixes'] as $raw ) {
				if ( ! is_scalar( $raw ) ) continue;
				$n = KSPPM_CPT::normalize_url_prefix( (string) $raw );
				if ( $n === '' ) continue;                 // 空行 / 不正文字 / 予約 slug は黙ってスキップ
				if ( $n === $default_prefix ) continue;     // profile は既に先頭
				if ( in_array( $n, $clean, true ) ) continue; // 重複禁止
				if ( self::prefix_collides_with_page( $n ) ) { // V4: 既存固定ページと同名は弾く
					$rejected[] = $n;
					continue;
				}
				$clean[] = $n;
				if ( count( $clean ) >= $max_prefixes ) break;
			}

			// V5 案 A: prev にあって clean から消えた = 削除対象。使用中なら復活 + 警告。
			// M3: 使用中 prefix の復活は上限に関わらず必ず行う (データ整合 > 人工的上限)。
			// 上限を超えた場合の切り詰めでも使用中 prefix を落とさないよう $forced_in_use に記録する。
			$forced_in_use = array();
			$removed = array_diff( $prev_registry, $clean );
			foreach ( $removed as $rp ) {
				if ( $rp === $default_prefix ) continue;
				$used = self::count_profiles_using_prefix( $rp );
				if ( $used > 0 ) {
					if ( ! in_array( $rp, $clean, true ) ) {
						$clean[] = $rp;
					}
					$forced_in_use[] = $rp;
					add_settings_error(
						'ksppm_settings',
						'ksppm_prefix_in_use',
						sprintf(
							/* translators: 1: prefix, 2: count */
							__( 'プレフィックス「%1$s」は %2$d 件のプロフィールが使用中のため削除できません。先に各プロフィールのプレフィックスを変更してください。', 'kashiwazaki-seo-profilepage-maker' ),
							$rp,
							$used
						),
						'error'
					);
				}
			}

			if ( ! empty( $rejected ) ) {
				add_settings_error(
					'ksppm_settings',
					'ksppm_prefix_page_collision',
					sprintf(
						/* translators: %s: prefix list */
						__( 'プレフィックス「%s」は既存の固定ページと同名のため登録できません。', 'kashiwazaki-seo-profilepage-maker' ),
						implode( ', ', $rejected )
					),
					'error'
				);
			}

			$clean = array_values( array_unique( $clean ) );
			if ( count( $clean ) > $max_prefixes ) {
				// M3: 上限超過時も default + 使用中 prefix は必ず保持し、それ以外を上限まで詰める。
				// 使用中 prefix を array_slice で黙って落とすと該当投稿の URL が変わるため。
				$must_keep = array_values( array_unique( array_merge( array( $default_prefix ), $forced_in_use ) ) );
				$others    = array_values( array_diff( $clean, $must_keep ) );
				$room      = max( 0, $max_prefixes - count( $must_keep ) );
				$clean     = array_values( array_unique( array_merge( $must_keep, array_slice( $others, 0, $room ) ) ) );
			}
			$out['url_prefixes'] = $clean;
		} else {
			// 入力なし (レジストリ UI を含まない保存経路) は既存を維持
			$out['url_prefixes'] = ! empty( $prev_registry ) ? $prev_registry : array( $default_prefix );
		}

		// 移行フラグは保持 (移行は init の maybe_migrate_multiprefix が担当)
		$out['multiprefix_migrated'] = ! empty( $prev['multiprefix_migrated'] ) ? 1 : 0;

		// レジストリ変更時も flush_rewrite_rules (rewrite rule 数が変わるため)
		if ( $prev_registry !== ( isset( $out['url_prefixes'] ) ? $out['url_prefixes'] : array() ) ) {
			static $flush_registered_registry = false;
			if ( ! $flush_registered_registry ) {
				$flush_registered_registry = true;
				add_action( 'shutdown', function () {
					if ( function_exists( 'flush_rewrite_rules' ) ) {
						flush_rewrite_rules( false );
					}
				} );
			}
		}

		// prefix 変更時に flush_rewrite_rules を自動実行
		// (sanitize_callback は WP 内部で複数回呼ばれることがあるため static flag で 1 回のみ登録)
		if ( $prev_prefix !== $new_prefix ) {
			static $flush_registered_prefix = false;
			if ( ! $flush_registered_prefix ) {
				$flush_registered_prefix = true;
				add_action( 'shutdown', function () {
					if ( function_exists( 'flush_rewrite_rules' ) ) {
						flush_rewrite_rules( false );
					}
				} );
			}
		}
		// F06: show_nationality_default は UI も読み出しも存在しない dead store だったため削除。
		//   per-profile の show_nationality は KSPPM_Fields 定義で default=1 となり独立して機能する。

		// (v0.0.21-beta): 表示差分修正用の opt-in 設定
		$out['integrate_skills_into_hobbies'] = isset( $input['integrate_skills_into_hobbies'] ) ? 1 : 0;
		$valid_preset = array( 'default', 'page7_like' );
		$out['display_order_preset'] = isset( $input['display_order_preset'] ) && in_array( $input['display_order_preset'], $valid_preset, true ) ? $input['display_order_preset'] : 'default';
		// v0.0.25-beta: SNS リンクで dashicon の隣に label を表示するか (default OFF = アイコンのみ)
		$out['sns_show_label'] = isset( $input['sns_show_label'] ) ? 1 : 0;

		// post_type_behavior 変更時は CPT 再登録 + rewrite 再生成、custom 時は capability 付与
		// ($prev は sanitize() 冒頭で取得済み、重複削除)
		$prev_behavior = isset( $prev['post_type_behavior'] ) ? $prev['post_type_behavior'] : 'custom';
		if ( $prev_behavior !== $out['post_type_behavior'] ) {
			if ( $out['post_type_behavior'] === 'custom' ) {
				KSPPM_CPT::grant_custom_capabilities();
			} else {
				// (v0.0.18-beta): custom→post/page 切替時に custom capabilities を revoke
				// (未呼出だと role に dead capability が残留)
				if ( method_exists( 'KSPPM_CPT', 'revoke_custom_capabilities' ) ) {
					KSPPM_CPT::revoke_custom_capabilities();
				}
			}
			static $flush_registered_behavior = false;
			if ( ! $flush_registered_behavior ) {
				$flush_registered_behavior = true;
				add_action( 'shutdown', function () {
					if ( function_exists( 'flush_rewrite_rules' ) ) {
						flush_rewrite_rules( false );
					}
				} );
			}
		}

		return $out;
	}

	/**
	 * メインルーター: action パラメータで一覧 / 新規 / 編集 / 設定 を切替
	 */
	public static function render_page() {
		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list';
		switch ( $action ) {
			case 'new':
				self::render_form( 0 );
				break;
			case 'edit':
				$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
				self::render_form( $post_id );
				break;
			case 'settings':
				// F07: settings は manage_options 必須 (親メニューを edit_posts に下げたため、render_page 側で明示ガード)
				if ( ! current_user_can( 'manage_options' ) ) {
					wp_die( __( '設定を変更する権限がありません。', 'kashiwazaki-seo-profilepage-maker' ) );
				}
				self::render_settings();
				break;
			case 'usage':
				self::render_usage();
				break;
			case 'list':
			default:
				self::render_list();
				break;
		}
	}

	/**
	 * 使い方画面
	 */
	protected static function render_usage() {
		self::render_header( 'usage' );
		$home = home_url( '/' );
		// F19: 実装に合わせて URL 例を動的生成 (url_prefix / root_rewrite_enabled の現設定値を反映)
		$usage_settings   = get_option( KSPPM_OPTION_KEY, array() );
		$usage_prefix     = KSPPM_CPT::get_url_prefix();
		$usage_root_on    = ! empty( $usage_settings['root_rewrite_enabled'] );
		$build_example_url = function ( $slug ) use ( $home, $usage_prefix, $usage_root_on ) {
			if ( $usage_prefix !== '' ) {
				return $home . trailingslashit( $usage_prefix ) . $slug . '/';
			}
			if ( $usage_root_on ) {
				return $home . $slug . '/';
			}
			return $home . '?ksppm_profile=' . $slug;
		};
		$toc = array(
			'getting-started' => array( 'icon' => 'welcome-learn-more', 'label' => __( '基本的な使い方', 'kashiwazaki-seo-profilepage-maker' ) ),
			'url-structure'   => array( 'icon' => 'admin-links',        'label' => __( 'URL の仕組み', 'kashiwazaki-seo-profilepage-maker' ) ),
			'shortcodes'      => array( 'icon' => 'shortcode',          'label' => __( 'ショートコード', 'kashiwazaki-seo-profilepage-maker' ) ),
			'json-ld'         => array( 'icon' => 'embed-generic',      'label' => __( '構造化データ (JSON-LD)', 'kashiwazaki-seo-profilepage-maker' ) ),
			'status'          => array( 'icon' => 'visibility',         'label' => __( '公開状態', 'kashiwazaki-seo-profilepage-maker' ) ),
			'privacy'         => array( 'icon' => 'lock',               'label' => __( '個人情報の表示制御', 'kashiwazaki-seo-profilepage-maker' ) ),
			'faq'             => array( 'icon' => 'editor-help',        'label' => __( 'よくある質問', 'kashiwazaki-seo-profilepage-maker' ) ),
		);
		?>
		<div class="ksppm-usage ksppm-usage--enhanced">

			<nav class="ksppm-usage-toc" aria-label="<?php esc_attr_e( '使い方タブの目次', 'kashiwazaki-seo-profilepage-maker' ); ?>">
				<strong class="ksppm-usage-toc__heading"><?php esc_html_e( '目次', 'kashiwazaki-seo-profilepage-maker' ); ?></strong>
				<ul>
					<?php foreach ( $toc as $id => $item ) : ?>
						<li><a href="#ksppm-usage-<?php echo esc_attr( $id ); ?>"><span class="dashicons dashicons-<?php echo esc_attr( $item['icon'] ); ?>" aria-hidden="true"></span><?php echo esc_html( $item['label'] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</nav>

			<section class="ksppm-usage-section" id="ksppm-usage-getting-started">
				<h2><span class="dashicons dashicons-welcome-learn-more" aria-hidden="true"></span><?php esc_html_e( '基本的な使い方', 'kashiwazaki-seo-profilepage-maker' ); ?></h2>
				<ol class="ksppm-usage-steps">
					<li><?php esc_html_e( '「新規プロフィール」タブで新しいプロフィールを作成します。', 'kashiwazaki-seo-profilepage-maker' ); ?></li>
					<li><?php esc_html_e( '氏名 (タイトル) と URL スラッグを入力します。例: your-name', 'kashiwazaki-seo-profilepage-maker' ); ?></li>
					<li><?php esc_html_e( '各タブ (基本情報・経歴・実績・メディア等) にプロフィール情報を入力します。', 'kashiwazaki-seo-profilepage-maker' ); ?></li>
					<?php
					// F19: 現設定での URL 例 ({スラッグ} はそのままプレースホルダ表示)
					$example_slug_placeholder = $build_example_url( '{スラッグ}' );
					?>
					<li><?php esc_html_e( '「作成して公開」を押すと、', 'kashiwazaki-seo-profilepage-maker' ); ?>
						<code class="ksppm-copyable" data-copy="<?php echo esc_attr( $example_slug_placeholder ); ?>"><?php echo esc_html( $example_slug_placeholder ); ?></code>
						<?php esc_html_e( 'の URL で公開されます。', 'kashiwazaki-seo-profilepage-maker' ); ?>
					</li>
					<li><?php esc_html_e( '公開ページには ProfilePage + Person の JSON-LD 構造化データが自動出力されます。', 'kashiwazaki-seo-profilepage-maker' ); ?></li>
				</ol>
			</section>

			<section class="ksppm-usage-section" id="ksppm-usage-url-structure">
				<h2><span class="dashicons dashicons-admin-links" aria-hidden="true"></span><?php esc_html_e( 'URL の仕組み', 'kashiwazaki-seo-profilepage-maker' ); ?></h2>
				<?php
				// F19: 現設定値に応じて説明文と URL 例を切り替え
				$example_url_a = $build_example_url( 'your-name' );
				$example_url_b = $build_example_url( 'another-person' );
				if ( $usage_prefix !== '' ) {
					$url_intro = sprintf(
						/* translators: %s: URL prefix (e.g. profile) */
						esc_html__( '現在の設定では、プロフィールは URL プレフィックス「/%s/」配下で公開されます。', 'kashiwazaki-seo-profilepage-maker' ),
						esc_html( $usage_prefix )
					);
				} elseif ( $usage_root_on ) {
					$url_intro = esc_html__( '現在の設定では、プロフィールはルート直下の個別 URL で公開されます (固定ページと同じ階層)。', 'kashiwazaki-seo-profilepage-maker' );
				} else {
					$url_intro = esc_html__( '現在の設定では、プロフィールはクエリ URL (?ksppm_profile={slug}) で公開されます。設定画面で「URL プレフィックス」または「ルート URL 公開」を有効化するとクリーン URL になります。', 'kashiwazaki-seo-profilepage-maker' );
				}
				?>
				<p><?php echo $url_intro; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — esc_html__ で個別エスケープ済 ?></p>
				<ul class="ksppm-usage-list">
					<li><code class="ksppm-copyable" data-copy="<?php echo esc_attr( $example_url_a ); ?>"><?php echo esc_html( $example_url_a ); ?></code> <?php esc_html_e( '— プロフィールページ', 'kashiwazaki-seo-profilepage-maker' ); ?></li>
					<li><code class="ksppm-copyable" data-copy="<?php echo esc_attr( $example_url_b ); ?>"><?php echo esc_html( $example_url_b ); ?></code> <?php esc_html_e( '— 別の人物のプロフィール', 'kashiwazaki-seo-profilepage-maker' ); ?></li>
				</ul>
				<p class="description"><?php esc_html_e( '※ WordPress の予約 slug (feed, wp-json, wp-sitemap.xml 等) や既存の固定ページ slug と重複する場合は、固定ページが優先されます。', 'kashiwazaki-seo-profilepage-maker' ); ?></p>
			</section>

			<section class="ksppm-usage-section" id="ksppm-usage-shortcodes">
				<h2><span class="dashicons dashicons-shortcode" aria-hidden="true"></span><?php esc_html_e( 'ショートコードでの埋め込み', 'kashiwazaki-seo-profilepage-maker' ); ?></h2>
				<p><?php esc_html_e( '固定ページや投稿の本文に貼り付けると、プロフィールがその場に表示されます。各行のショートコードはクリックでコピーできます。', 'kashiwazaki-seo-profilepage-maker' ); ?></p>
				<table class="widefat striped ksppm-usage-table">
					<thead><tr><th><?php esc_html_e( 'ショートコード', 'kashiwazaki-seo-profilepage-maker' ); ?></th><th><?php esc_html_e( '動作', 'kashiwazaki-seo-profilepage-maker' ); ?></th></tr></thead>
					<tbody>
						<tr><td><code class="ksppm-copyable" data-copy="[kashiwazaki_profilepage]">[kashiwazaki_profilepage]</code></td><td><?php esc_html_e( 'メインプロフィール (設定で指定したもの、未指定なら最新) を全文表示', 'kashiwazaki-seo-profilepage-maker' ); ?></td></tr>
						<tr><td><code class="ksppm-copyable" data-copy='[kashiwazaki_profilepage id="123"]'>[kashiwazaki_profilepage id="123"]</code></td><td><?php esc_html_e( 'ID 指定のプロフィールを表示', 'kashiwazaki-seo-profilepage-maker' ); ?></td></tr>
						<tr><td><code class="ksppm-copyable" data-copy='[kashiwazaki_profilepage section="basic"]'>[kashiwazaki_profilepage section="basic"]</code></td><td><?php esc_html_e( '基本情報セクションのみ表示 (basic / career / expertise / works / tools / media / contact)', 'kashiwazaki-seo-profilepage-maker' ); ?></td></tr>
						<tr><td><code class="ksppm-copyable" data-copy="[kashiwazaki_profile]">[kashiwazaki_profile]</code></td><td><?php esc_html_e( '同上 (短縮名エイリアス)', 'kashiwazaki-seo-profilepage-maker' ); ?></td></tr>
					<tr><td><code class="ksppm-copyable" data-copy="[ksppm_profile]">[ksppm_profile]</code></td><td><?php esc_html_e( '同上 (汎用名エイリアス、v0.0.15-beta 以降)', 'kashiwazaki-seo-profilepage-maker' ); ?></td></tr>
					</tbody>
				</table>
			</section>

			<section class="ksppm-usage-section" id="ksppm-usage-json-ld">
				<h2><span class="dashicons dashicons-embed-generic" aria-hidden="true"></span><?php esc_html_e( '構造化データ (JSON-LD)', 'kashiwazaki-seo-profilepage-maker' ); ?></h2>
				<p><?php esc_html_e( 'プロフィールページに以下の構造化データが自動出力されます (schema.org 準拠)。', 'kashiwazaki-seo-profilepage-maker' ); ?></p>
				<ul class="ksppm-usage-list">
					<li><strong>ProfilePage</strong> <?php esc_html_e( '— ページ自体の種別', 'kashiwazaki-seo-profilepage-maker' ); ?></li>
					<li><strong>Person</strong> <?php esc_html_e( '— 人物情報 (氏名・職業・所属組織・SNS アカウント・受賞歴・出版書籍 等)', 'kashiwazaki-seo-profilepage-maker' ); ?></li>
					<li><strong>BreadcrumbList</strong> <?php esc_html_e( '— パンくず (設定で ON/OFF 切替可能)', 'kashiwazaki-seo-profilepage-maker' ); ?></li>
					<li><strong>speakable</strong> <?php esc_html_e( '— AI 音声読み上げ用 (設定で ON/OFF 切替可能)', 'kashiwazaki-seo-profilepage-maker' ); ?></li>
				</ul>
				<p class="ksppm-usage-cta">
					<a class="button button-secondary" href="https://search.google.com/test/rich-results" target="_blank" rel="noopener"><span class="dashicons dashicons-external" aria-hidden="true"></span><?php esc_html_e( 'Google リッチリザルトテストで確認', 'kashiwazaki-seo-profilepage-maker' ); ?></a>
				</p>
			</section>

			<section class="ksppm-usage-section" id="ksppm-usage-status">
				<h2><span class="dashicons dashicons-visibility" aria-hidden="true"></span><?php esc_html_e( 'プロフィールの公開状態', 'kashiwazaki-seo-profilepage-maker' ); ?></h2>
				<ul class="ksppm-usage-list">
					<li><strong><?php esc_html_e( '公開', 'kashiwazaki-seo-profilepage-maker' ); ?></strong> — <?php esc_html_e( '誰でも閲覧可能', 'kashiwazaki-seo-profilepage-maker' ); ?></li>
					<li><strong><?php esc_html_e( '下書き', 'kashiwazaki-seo-profilepage-maker' ); ?></strong> — <?php esc_html_e( '編集権限のあるユーザーのみプレビュー可能', 'kashiwazaki-seo-profilepage-maker' ); ?></li>
					<li><strong><?php esc_html_e( '非公開', 'kashiwazaki-seo-profilepage-maker' ); ?></strong> — <?php esc_html_e( '管理者と本人のみ閲覧可能', 'kashiwazaki-seo-profilepage-maker' ); ?></li>
				</ul>
			</section>

			<section class="ksppm-usage-section" id="ksppm-usage-privacy">
				<h2><span class="dashicons dashicons-lock" aria-hidden="true"></span><?php esc_html_e( '個人情報の表示制御', 'kashiwazaki-seo-profilepage-maker' ); ?></h2>
				<p><?php esc_html_e( '一部の項目は「公開する」チェックでフロントに出すかどうかを個別に制御できます。', 'kashiwazaki-seo-profilepage-maker' ); ?></p>
				<ul class="ksppm-usage-list ksppm-usage-list--privacy">
					<li><span class="ksppm-sensitive-badge"><span class="dashicons dashicons-hidden" aria-hidden="true"></span><?php esc_html_e( '非公開制御', 'kashiwazaki-seo-profilepage-maker' ); ?></span> <?php esc_html_e( '生年月日・出生地 → 「生年月日を公開する」で制御', 'kashiwazaki-seo-profilepage-maker' ); ?></li>
					<li><span class="ksppm-sensitive-badge"><span class="dashicons dashicons-hidden" aria-hidden="true"></span><?php esc_html_e( '非公開制御', 'kashiwazaki-seo-profilepage-maker' ); ?></span> <?php esc_html_e( '身長・体重 → 「身長・体重を公開する」で制御', 'kashiwazaki-seo-profilepage-maker' ); ?></li>
					<li><span class="ksppm-sensitive-badge"><span class="dashicons dashicons-hidden" aria-hidden="true"></span><?php esc_html_e( '非公開制御', 'kashiwazaki-seo-profilepage-maker' ); ?></span> <?php esc_html_e( '性別 → 「性別を公開する」で制御', 'kashiwazaki-seo-profilepage-maker' ); ?></li>
					<li><span class="ksppm-sensitive-badge"><span class="dashicons dashicons-hidden" aria-hidden="true"></span><?php esc_html_e( '非公開制御', 'kashiwazaki-seo-profilepage-maker' ); ?></span> <?php esc_html_e( 'メール・電話 → 「メールを公開する」「電話を公開する」で制御', 'kashiwazaki-seo-profilepage-maker' ); ?></li>
				</ul>
			</section>

			<section class="ksppm-usage-section ksppm-usage-faq" id="ksppm-usage-faq">
				<h2><span class="dashicons dashicons-editor-help" aria-hidden="true"></span><?php esc_html_e( 'よくある質問', 'kashiwazaki-seo-profilepage-maker' ); ?></h2>
				<?php $faqs = array(
					array( 'q' => __( 'プロフィールを複数作成できますか?', 'kashiwazaki-seo-profilepage-maker' ), 'a' => __( 'はい、何人分でも作成できます。それぞれが個別 URL で公開されます。', 'kashiwazaki-seo-profilepage-maker' ) ),
					array( 'q' => __( 'URL スラッグは後から変更できますか?', 'kashiwazaki-seo-profilepage-maker' ), 'a' => __( 'はい、編集画面の「URL スラッグ」欄でいつでも変更可能です。', 'kashiwazaki-seo-profilepage-maker' ) ),
					array( 'q' => __( '削除したプロフィールは復元できますか?', 'kashiwazaki-seo-profilepage-maker' ), 'a' => __( 'いいえ、削除は完全削除です。削除前に確認ダイアログが表示されます。', 'kashiwazaki-seo-profilepage-maker' ) ),
					array( 'q' => __( '構造化データを無効化できますか?', 'kashiwazaki-seo-profilepage-maker' ), 'a' => __( '「設定」タブで JSON-LD 出力を OFF にできます。', 'kashiwazaki-seo-profilepage-maker' ) ),
				);
				foreach ( $faqs as $i => $faq ) : ?>
					<details class="ksppm-usage-faq__item"<?php echo $i === 0 ? ' open' : ''; ?>>
						<summary><strong>Q.</strong> <?php echo esc_html( $faq['q'] ); ?></summary>
						<div class="ksppm-usage-faq__answer"><strong>A.</strong> <?php echo esc_html( $faq['a'] ); ?></div>
					</details>
				<?php endforeach; ?>
			</section>

		</div>
		<?php
		self::render_footer();
	}

	/**
	 * 共通ヘッダー (タイトル + タブナビ)
	 */
	protected static function render_header( $active_tab = 'list' ) {
		$tabs = array(
			'list'     => __( 'プロフィール一覧', 'kashiwazaki-seo-profilepage-maker' ),
			'new'      => __( '新規プロフィール', 'kashiwazaki-seo-profilepage-maker' ),
			'settings' => __( '設定', 'kashiwazaki-seo-profilepage-maker' ),
			'usage'    => __( '使い方', 'kashiwazaki-seo-profilepage-maker' ),
		);
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">Kashiwazaki SEO ProfilePage Maker</h1>
			<?php if ( $active_tab !== 'new' ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&action=new' ) ); ?>" class="page-title-action">+ <?php esc_html_e( '新規プロフィール', 'kashiwazaki-seo-profilepage-maker' ); ?></a>
			<?php endif; ?>
			<hr class="wp-header-end">

			<h2 class="nav-tab-wrapper">
				<?php foreach ( $tabs as $key => $label ) :
					$url = ( $key === 'list' )
						? admin_url( 'admin.php?page=' . self::PAGE_SLUG )
						: admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&action=' . $key );
					$class = ( $active_tab === $key || ( $active_tab === 'edit' && $key === 'list' ) ) ? ' nav-tab-active' : '';
					?>
					<a href="<?php echo esc_url( $url ); ?>" class="nav-tab<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</h2>
		<?php
	}

	protected static function render_footer() {
		?>
		</div>
		<?php
	}

	/**
	 * 一覧画面
	 */
	protected static function render_list() {
		// (v0.0.18-beta): posts_per_page=-1 → LIMIT 500 で防御的プログラミング
		// (大規模プロフィール時のメモリ問題予防、T14 で flush_all_cache も同様に対応済)
		$profiles = get_posts( array(
			'post_type'      => KSPPM_CPT,
			'posts_per_page' => 500,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		) );

		// (D2) per-post 認可フィルタ: 一覧は管理メニュー到達権限 (edit_posts 相当) で表示されるが、
		//   get_posts は author を問わず全プロフィールを返す。edit_others 権限を持たないユーザーへ
		//   他者の下書き等のタイトル/slug が漏れ、かつクリックで 403 になるデッドリンクを表示するのを防ぐ。
		//   編集権 (edit_post) を持つプロフィールのみ一覧表示する (render_list は常にログインユーザー文脈)。
		$profiles = array_values( array_filter( $profiles, function( $p ) {
			return current_user_can( 'edit_post', $p->ID );
		} ) );

		// 成功メッセージ
		$msg = isset( $_GET['msg'] ) ? sanitize_key( $_GET['msg'] ) : '';
		self::render_header( 'list' );
		?>
		<?php if ( $msg ) : ?>
			<?php $messages = array(
				'saved'   => __( 'プロフィールを保存しました。', 'kashiwazaki-seo-profilepage-maker' ),
				'deleted' => __( 'プロフィールを削除しました。', 'kashiwazaki-seo-profilepage-maker' ),
				'error'   => __( 'エラーが発生しました。', 'kashiwazaki-seo-profilepage-maker' ),
			); ?>
			<div class="notice notice-<?php echo $msg === 'error' ? 'error' : 'success'; ?> is-dismissible">
				<p><?php echo esc_html( $messages[ $msg ] ?? '' ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( empty( $profiles ) ) : ?>
			<div class="ksppm-empty-hero">
				<span class="dashicons dashicons-id-alt ksppm-empty-hero__icon"></span>
				<h2><?php esc_html_e( 'SEO 強化プロフィールページを作成', 'kashiwazaki-seo-profilepage-maker' ); ?></h2>
				<p><?php esc_html_e( '氏名・経歴・実績・SNS などを 1 つのページに集約し、Google が認識できる構造化データ (ProfilePage + Person) を自動で出力します。AI 検索 (AI Overviews / Perplexity 等) の引用元としても機能します。', 'kashiwazaki-seo-profilepage-maker' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&action=new' ) ); ?>" class="button button-primary button-hero">+ <?php esc_html_e( '最初のプロフィールを作成', 'kashiwazaki-seo-profilepage-maker' ); ?></a>
			</div>
		<?php else : ?>
			<?php
			// 検索 / フィルタ (T9)
			$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
			$filter_status = isset( $_GET['fs'] ) ? sanitize_key( $_GET['fs'] ) : 'all';
			$filtered = $profiles;
			if ( $search !== '' ) {
				$filtered = array_filter( $filtered, function( $p ) use ( $search ) {
					return ( stripos( $p->post_title, $search ) !== false ) || ( stripos( $p->post_name, $search ) !== false );
				} );
			}
			if ( $filter_status !== 'all' ) {
				$filtered = array_filter( $filtered, function( $p ) use ( $filter_status ) {
					return $p->post_status === $filter_status;
				} );
			}
			$base_url = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
			?>
			<div class="ksppm-list-toolbar">
				<form method="get" action="" class="ksppm-list-search-form">
					<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">
					<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'タイトル / スラッグで検索...', 'kashiwazaki-seo-profilepage-maker' ); ?>" class="ksppm-list-search">
					<button type="submit" class="button"><?php esc_html_e( '検索', 'kashiwazaki-seo-profilepage-maker' ); ?></button>
					<?php if ( $search ): ?>
						<a href="<?php echo esc_url( $base_url ); ?>" class="button-link"><?php esc_html_e( 'クリア', 'kashiwazaki-seo-profilepage-maker' ); ?></a>
					<?php endif; ?>
				</form>
				<nav class="ksppm-list-filter">
					<?php
					$status_counts = array(
						'all' => count( $profiles ),
						'publish' => 0, 'draft' => 0, 'pending' => 0, 'private' => 0,
					);
					foreach ( $profiles as $p ) {
						if ( isset( $status_counts[ $p->post_status ] ) ) $status_counts[ $p->post_status ]++;
					}
					$status_filters = array(
						'all'     => __( 'すべて', 'kashiwazaki-seo-profilepage-maker' ),
						'publish' => __( '公開', 'kashiwazaki-seo-profilepage-maker' ),
						'draft'   => __( '下書き', 'kashiwazaki-seo-profilepage-maker' ),
						'pending' => __( 'レビュー待ち', 'kashiwazaki-seo-profilepage-maker' ),
						'private' => __( '非公開', 'kashiwazaki-seo-profilepage-maker' ),
					);
					foreach ( $status_filters as $st => $st_label ) :
						if ( $st !== 'all' && $status_counts[ $st ] === 0 ) continue;
						$url = add_query_arg( array( 'fs' => $st, 's' => $search ), $base_url );
						$active = $filter_status === $st ? 'active' : '';
						?>
						<a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $active ); ?>"><?php echo esc_html( $st_label ); ?> <small>(<?php echo esc_html( $status_counts[ $st ] ); ?>)</small></a>
					<?php endforeach; ?>
				</nav>
				<span class="ksppm-list-count"><?php printf( esc_html__( '%d 件表示', 'kashiwazaki-seo-profilepage-maker' ), count( $filtered ) ); ?></span>
			</div>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'タイトル', 'kashiwazaki-seo-profilepage-maker' ); ?></th>
						<th><?php esc_html_e( 'URL (スラッグ)', 'kashiwazaki-seo-profilepage-maker' ); ?></th>
						<th class="ksppm-col-status"><?php esc_html_e( '状態', 'kashiwazaki-seo-profilepage-maker' ); ?></th>
						<th class="ksppm-col-actions"><?php esc_html_e( '操作', 'kashiwazaki-seo-profilepage-maker' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $filtered as $p ) :
					$permalink = get_permalink( $p );
					$edit_url = admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&action=edit&post_id=' . $p->ID );
					$delete_url = wp_nonce_url(
						admin_url( 'admin-post.php?action=ksppm_delete&post_id=' . $p->ID ),
						'ksppm_delete_' . $p->ID
					);
					$status_label = array(
						'publish' => __( '公開', 'kashiwazaki-seo-profilepage-maker' ),
						'draft'   => __( '下書き', 'kashiwazaki-seo-profilepage-maker' ),
						'pending' => __( 'レビュー待ち', 'kashiwazaki-seo-profilepage-maker' ),
						'private' => __( '非公開', 'kashiwazaki-seo-profilepage-maker' ),
					);
					?>
					<tr>
						<td><strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $p->post_title ); ?></a></strong></td>
						<td>
							<a href="<?php echo esc_url( $permalink ); ?>" target="_blank" rel="noopener"><?php echo esc_html( urldecode( $permalink ) ); ?></a>
							<br><code><?php echo esc_html( $p->post_name ); ?></code>
						</td>
						<td><span class="ksppm-status-badge ksppm-status-badge--<?php echo esc_attr( $p->post_status ); ?>"><?php echo esc_html( $status_label[ $p->post_status ] ?? $p->post_status ); ?></span></td>
						<td>
							<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small button-primary"><?php esc_html_e( '編集', 'kashiwazaki-seo-profilepage-maker' ); ?></a>
							<a href="<?php echo esc_url( $permalink ); ?>" target="_blank" class="button button-small"><?php esc_html_e( '表示', 'kashiwazaki-seo-profilepage-maker' ); ?></a>
							<?php if ( current_user_can( 'delete_post', $p->ID ) ) : // (D2) 削除権限を持つ場合のみリンク表示 (デッドリンク防止) ?>
							<a href="<?php echo esc_url( $delete_url ); ?>" class="button button-small ksppm-delete-link" onclick="return confirm('<?php echo esc_js( __( '本当に削除しますか?', 'kashiwazaki-seo-profilepage-maker' ) ); ?>');"><?php esc_html_e( '削除', 'kashiwazaki-seo-profilepage-maker' ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
		self::render_footer();
	}

	/**
	 * 新規 / 編集フォーム
	 */
	protected static function render_form( $post_id = 0 ) {
		$is_new = ( $post_id === 0 );
		$post = $is_new ? null : get_post( $post_id );
		if ( ! $is_new && ( ! $post || $post->post_type !== KSPPM_CPT ) ) {
			self::render_header( 'list' );
			echo '<div class="notice notice-error"><p>' . esc_html__( 'プロフィールが見つかりません。', 'kashiwazaki-seo-profilepage-maker' ) . '</p></div>';
			self::render_footer();
			return;
		}

		// F01: 編集フォーム表示時の認可チェック。
		//   親メニュー到達権限は edit_posts 相当のため、capability_type が post/page のモードでは
		//   投稿編集権を持つ低権限ユーザーが任意の post_id を指定して、非公開メタ (PII 等) を含む
		//   編集フォームを閲覧できてしまう。保存 (handle_save) 側には権限チェックがあるが、
		//   表示時の情報漏洩を防ぐため、既存プロフィールの編集表示でも edit_post を必須にする。
		//   (新規作成 $is_new は対象外。到達権限 = 作成権限とみなす)
		if ( ! $is_new && ! current_user_can( 'edit_post', $post_id ) ) {
			self::render_header( 'list' );
			echo '<div class="notice notice-error"><p>' . esc_html__( 'このプロフィールを編集する権限がありません。', 'kashiwazaki-seo-profilepage-maker' ) . '</p></div>';
			self::render_footer();
			return;
		}

		$title  = $is_new ? '' : $post->post_title;
		$slug   = $is_new ? '' : $post->post_name;
		$status = $is_new ? 'publish' : $post->post_status;

		// (custom blocks, spec E2): 編集フォーム表示時 (admin context) に未移行投稿を1回だけ永続移行。
		// resolve_blocks は移行済フラグで冪等。フロント GET では persist=false で書込しない。
		if ( ! $is_new && class_exists( 'KSPPM_Meta' ) && method_exists( 'KSPPM_Meta', 'resolve_blocks' ) ) {
			KSPPM_Meta::resolve_blocks( $post_id, true );
		}

		self::render_header( $is_new ? 'new' : 'edit' );

		// エラー / メッセージ表示
		$msg = isset( $_GET['msg'] ) ? sanitize_key( $_GET['msg'] ) : '';
		$error_detail = get_transient( 'ksppm_form_error_' . get_current_user_id() );
		if ( $error_detail ) delete_transient( 'ksppm_form_error_' . get_current_user_id() );
		$date_warn = get_transient( 'ksppm_form_datewarn_' . get_current_user_id() );
		if ( $date_warn ) delete_transient( 'ksppm_form_datewarn_' . get_current_user_id() );

		// 保存失敗時のフォームデータ復元
		$form_data = get_transient( 'ksppm_form_data_' . get_current_user_id() );
		if ( $form_data && in_array( $msg, array( 'title_empty', 'save_failed' ), true ) ) {
			delete_transient( 'ksppm_form_data_' . get_current_user_id() );
			// F03: フィールド型別 sanitize で復元 (再表示時 render_field 側でも esc される多層防御)。
			//   一律 sanitize_text_field だと wysiwyg (来歴) の HTML が plain text に潰れ、
			//   url repeater の & 等が壊れて未保存の入力内容が失われる。型に応じた最小 sanitize に分岐:
			//     wysiwyg → wp_kses_post (許可タグ保持) / textarea → sanitize_textarea_field (改行保持)
			//     url → esc_url_raw / email → sanitize_email / repeater (配列) → サブ値を許可タグのみ除去
			//     それ以外 → sanitize_text_field
			//   ※ $form_data は handle_save で set_transient( ..., wp_unslash($_POST) ) 済みのため、
			//     ここで再度 wp_unslash しない (二重 unslash 防止)。input_key は 'ksppm_' + field key。
			if ( is_array( $form_data ) ) {
				$field_types = array();
				foreach ( KSPPM_Fields::get_all_fields() as $fkey => $fdef ) {
					$field_types[ 'ksppm_' . $fkey ] = $fdef['type'];
				}
				$sanitize_scalar_by_type = function ( $v, $type ) {
					if ( ! is_string( $v ) ) {
						return $v;
					}
					switch ( $type ) {
						case 'wysiwyg':  return wp_kses_post( $v );
						case 'textarea': return sanitize_textarea_field( $v );
						case 'url':      return esc_url_raw( $v );
						case 'email':    return sanitize_email( $v );
						default:         return sanitize_text_field( $v );
					}
				};
				$restored = array();
				foreach ( $form_data as $k => $v ) {
					$type = isset( $field_types[ $k ] ) ? $field_types[ $k ] : 'text';
					if ( $k === 'ksppm_custom_blocks_json' ) {
						// (custom blocks, spec E3a): JSON 文字列は sanitize_text_field で改行/タグ除去すると
						// 壊れるため raw 保持。最終 sanitize は save_custom_blocks / Meta::get の json_decode+構造検証で実施。
						$restored[ $k ] = is_string( $v ) ? $v : '';
					} elseif ( $type === 'repeater' && is_array( $v ) ) {
						// repeater サブ値は最終出力 (profile-content) で esc_url/esc_html されるため、
						// ここでは破壊的変換を避け、許可タグのみ除去 (URL の & 等を保持)。
						$restored[ $k ] = map_deep( $v, function ( $iv ) {
							return is_string( $iv ) ? wp_kses_post( $iv ) : $iv;
						} );
					} else {
						$restored[ $k ] = is_string( $v )
							? $sanitize_scalar_by_type( $v, $type )
							: $v;
					}
				}
				$form_data = $restored;
			}
			// F03: $_POST mutation を廃止し、KSPPM_Meta 専用 store に注入する。
			// これにより同一 request 内で後続の他プラグイン/コア処理が $_POST を読んでも汚染されない。
			// KSPPM_Meta::get_restored_value() が self::$restored_form_data を参照する。
			KSPPM_Meta::set_restored_data( is_array( $form_data ) ? $form_data : null );
			if ( isset( $form_data['post_title'] ) ) $title = sanitize_text_field( $form_data['post_title'] );
			if ( isset( $form_data['post_name'] ) ) $slug = sanitize_title( $form_data['post_name'] );
			if ( isset( $form_data['post_status'] ) && in_array( $form_data['post_status'], array( 'publish', 'draft', 'private', 'pending' ), true ) ) {
				$status = $form_data['post_status'];
			}
		}
		?>
		<?php if ( $date_warn ) : ?>
			<div class="notice notice-warning is-dismissible">
				<p><strong><?php esc_html_e( '保存しました（警告）: 日付の前後関係が不正な項目があります。値は保持しています。', 'kashiwazaki-seo-profilepage-maker' ); ?></strong></p>
				<p class="description"><?php echo esc_html( $date_warn ); ?></p>
			</div>
		<?php endif; ?>
		<?php if ( $msg === 'title_empty' ) : ?>
			<div class="notice notice-error">
				<p><strong><?php esc_html_e( '保存できません: タイトル(氏名)を入力してください。', 'kashiwazaki-seo-profilepage-maker' ); ?></strong></p>
				<p class="description"><?php esc_html_e( '※ 入力内容は復元されています。タイトルを入力後、再度保存してください。', 'kashiwazaki-seo-profilepage-maker' ); ?></p>
			</div>
		<?php elseif ( $msg === 'save_failed' ) : ?>
			<div class="notice notice-error">
				<p><strong><?php esc_html_e( '保存に失敗しました。', 'kashiwazaki-seo-profilepage-maker' ); ?></strong></p>
				<?php if ( $error_detail && strpos( $error_detail, 'wp_error:' ) === 0 ) : ?>
					<p class="description"><?php echo esc_html( substr( $error_detail, 9 ) ); ?></p>
				<?php endif; ?>
			</div>
		<?php elseif ( $msg === 'saved' ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'プロフィールを保存しました。', 'kashiwazaki-seo-profilepage-maker' ); ?></p>
			</div>
		<?php endif; ?>
		<h2><?php echo $is_new ? esc_html__( '新規プロフィール作成', 'kashiwazaki-seo-profilepage-maker' ) : esc_html__( 'プロフィール編集', 'kashiwazaki-seo-profilepage-maker' ); ?>
			<?php if ( ! $is_new ) : ?>
				<small>(<a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" target="_blank"><?php esc_html_e( '公開ページを表示', 'kashiwazaki-seo-profilepage-maker' ); ?></a>)</small>
			<?php endif; ?>
		</h2>

		<?php if ( $is_new ) : ?>
			<!-- U7: 新規時オンボーディングヒント -->
			<div class="ksppm-onboarding-hint">
				<span class="dashicons dashicons-lightbulb ksppm-onboarding-hint__icon"></span>
				<div class="ksppm-onboarding-hint__text">
					<strong><?php esc_html_e( 'まずは最低限の 5 項目を入力してください', 'kashiwazaki-seo-profilepage-maker' ); ?></strong>
					<small><?php esc_html_e( 'タイトル(氏名) / URL スラッグ / 現職(肩書) / 自己紹介(短文) / アイキャッチ画像 → 推奨フィールドに ★ マークが付きます', 'kashiwazaki-seo-profilepage-maker' ); ?></small>
				</div>
			</div>
		<?php endif; ?>

		<?php
		// novalidate: タブUIで非表示中のフィールド (例: 相対 URL を持つ url 型リピーター項目
		// ksppm_free_tools[][url]) が HTML5 制約検証に引っかかると、ブラウザは非表示要素に
		// focus できず submit を黙って中断する ("invalid form control is not focusable")。
		// その結果「更新ボタンを押しても反応しない」状態になる。検証はサーバー側 (handle_save の
		// 必須チェック + esc_url_raw 等のサニタイズ) で完結しているため、クライアント検証を無効化する。
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="ksppm-edit-form" class="ksppm-editor" data-is-new="<?php echo $is_new ? '1' : '0'; ?>" novalidate>
			<input type="hidden" name="action" value="ksppm_save">
			<input type="hidden" name="post_id" value="<?php echo esc_attr( $post_id ); ?>">
			<?php wp_nonce_field( 'ksppm_save_' . $post_id, 'ksppm_meta_nonce' ); ?>

			<div id="poststuff">
				<div id="post-body" class="metabox-holder columns-2">
					<div id="post-body-content">

						<!-- タイトル -->
						<div class="ksppm-title-box">
							<label for="ksppm_title" class="screen-reader-text"><?php esc_html_e( 'プロフィールタイトル', 'kashiwazaki-seo-profilepage-maker' ); ?></label>
							<input type="text" name="post_title" id="ksppm_title" class="ksppm-form__title" value="<?php echo esc_attr( $title ); ?>" placeholder="<?php esc_attr_e( '氏名 (例: 柏崎剛)', 'kashiwazaki-seo-profilepage-maker' ); ?>" autocomplete="off" required>
						</div>

						<!-- URL スラッグ + ライブプレビュー (T15) -->
						<?php
						// (multiprefix S4-3): プロフィール固有の URL プレフィックスを選択。
						// slug preview にも選択中プレフィックスを反映する。
						$prefix_registry = class_exists( 'KSPPM_CPT' ) ? KSPPM_CPT::get_url_prefixes() : array( 'profile' );
						$current_prefix  = ( ! $is_new && class_exists( 'KSPPM_CPT' ) )
							? KSPPM_CPT::get_post_url_prefix( $post_id )
							: ( class_exists( 'KSPPM_CPT' ) ? KSPPM_CPT::default_url_prefix() : 'profile' );
						$prefix_segment = $current_prefix !== '' ? trailingslashit( $current_prefix ) : '';
						$home_base      = home_url( '/' ) . $prefix_segment;
						?>
						<div class="ksppm-slug-box">
							<label for="ksppm_url_prefix"><strong><?php esc_html_e( 'URL プレフィックス', 'kashiwazaki-seo-profilepage-maker' ); ?></strong></label>
							<div class="ksppm-slug-row">
								<select name="ksppm_url_prefix" id="ksppm_url_prefix">
									<?php foreach ( $prefix_registry as $rp ) : ?>
										<option value="<?php echo esc_attr( $rp ); ?>" <?php selected( $current_prefix, $rp ); ?>><?php echo esc_html( '/' . $rp . '/' ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<p class="description"><?php echo wp_kses( __( 'このプロフィールを公開する親ディレクトリ。選択肢は設定画面の「URL プレフィックス・レジストリ」で管理します。変更すると旧 URL は新 URL に 301 リダイレクトされます。', 'kashiwazaki-seo-profilepage-maker' ), array() ); ?></p>
							<label for="ksppm_slug"><strong><?php esc_html_e( 'URL スラッグ', 'kashiwazaki-seo-profilepage-maker' ); ?></strong></label>
							<div class="ksppm-slug-row">
								<code class="ksppm-slug-home"><?php echo esc_html( $home_base ); ?></code>
								<input type="text" name="post_name" id="ksppm_slug" value="<?php echo esc_attr( $slug ); ?>" placeholder="your-name">
								<code>/</code>
							</div>
							<div class="ksppm-slug-preview" data-home-url="<?php echo esc_attr( $home_base ); ?>" data-home-root="<?php echo esc_attr( home_url( '/' ) ); ?>">
								<?php esc_html_e( '公開 URL:', 'kashiwazaki-seo-profilepage-maker' ); ?>
								<strong class="ksppm-slug-preview-url"><?php echo esc_html( $home_base . ( $slug ?: '...' ) . '/' ); ?></strong>
								<?php if ( ! $is_new ) : ?>
									<button type="button" class="ksppm-copy-btn" data-copy-target=".ksppm-slug-preview-url"><?php esc_html_e( 'コピー', 'kashiwazaki-seo-profilepage-maker' ); ?></button>
								<?php endif; ?>
							</div>
							<p class="description"><?php esc_html_e( '半角英数とハイフン推奨。空欄ならタイトルから自動生成されます。', 'kashiwazaki-seo-profilepage-maker' ); ?></p>
						</div>

						<!-- 既存のタブ UI (admin.php のメタボックスを直接呼び出す) -->
						<?php
						$render_post = $is_new ? (object) array( 'ID' => 0 ) : $post;
						// メインタブ UI
						?>
						<div class="postbox">
							<div class="postbox-header"><h2><?php esc_html_e( 'プロフィール詳細', 'kashiwazaki-seo-profilepage-maker' ); ?></h2></div>
							<div class="inside">
								<?php KSPPM_Admin::render_tabs( $render_post ); ?>
							</div>
						</div>

					</div><!-- /post-body-content -->

					<div id="postbox-container-1" class="postbox-container">
						<!-- 公開ボックス (T14: 1 番目) -->
						<div class="postbox">
							<div class="postbox-header"><h2><?php esc_html_e( '公開', 'kashiwazaki-seo-profilepage-maker' ); ?></h2></div>
							<div class="inside">
								<p>
									<label for="ksppm_status"><?php esc_html_e( '状態', 'kashiwazaki-seo-profilepage-maker' ); ?>:</label>
									<select name="post_status" id="ksppm_status">
										<option value="publish" <?php selected( $status, 'publish' ); ?>><?php esc_html_e( '公開', 'kashiwazaki-seo-profilepage-maker' ); ?></option>
										<option value="draft" <?php selected( $status, 'draft' ); ?>><?php esc_html_e( '下書き', 'kashiwazaki-seo-profilepage-maker' ); ?></option>
										<option value="pending" <?php selected( $status, 'pending' ); ?>><?php esc_html_e( 'レビュー待ち', 'kashiwazaki-seo-profilepage-maker' ); ?></option>
										<option value="private" <?php selected( $status, 'private' ); ?>><?php esc_html_e( '非公開', 'kashiwazaki-seo-profilepage-maker' ); ?></option>
									</select>
								</p>
							</div>
						</div>

						<!-- アイキャッチ画像 (T14: 2 番目に昇格) -->
						<div class="postbox">
							<div class="postbox-header"><h2><?php esc_html_e( 'アイキャッチ画像', 'kashiwazaki-seo-profilepage-maker' ); ?></h2></div>
							<div class="inside">
								<?php
								$thumb_id = $is_new ? 0 : (int) get_post_thumbnail_id( $post_id );
								$thumb_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium' ) : '';
								$thumb_att = $thumb_id ? get_post( $thumb_id ) : null;
								?>
								<div class="ksppm-image-field" data-name="_thumbnail_id">
									<input type="hidden" name="_thumbnail_id" value="<?php echo esc_attr( $thumb_id ); ?>">
									<div class="ksppm-image-preview">
										<?php if ( $thumb_url ) : ?>
											<img src="<?php echo esc_url( $thumb_url ); ?>" alt="">
										<?php else : ?>
											<div class="ksppm-image-empty">
												<span><?php esc_html_e( 'クリックして画像を選択', 'kashiwazaki-seo-profilepage-maker' ); ?></span>
											</div>
										<?php endif; ?>
									</div>
									<?php if ( $thumb_att ) : ?>
										<div class="ksppm-image-meta"><?php echo esc_html( $thumb_att->post_title ); ?></div>
									<?php else : ?>
										<div class="ksppm-image-meta"></div>
									<?php endif; ?>
									<div class="ksppm-image-actions">
										<button type="button" class="button ksppm-select-image"><?php esc_html_e( '画像を選択', 'kashiwazaki-seo-profilepage-maker' ); ?></button>
										<button type="button" class="button ksppm-unset-image" <?php echo $thumb_id ? '' : 'hidden'; ?>><?php esc_html_e( '解除', 'kashiwazaki-seo-profilepage-maker' ); ?></button>
									</div>
								</div>
							</div>
						</div>

						<!-- E-E-A-T 完成度 (T14: 3 番目) -->
						<?php if ( ! $is_new ) : ?>
							<div class="postbox">
								<div class="postbox-header"><h2><?php esc_html_e( 'E-E-A-T 完成度', 'kashiwazaki-seo-profilepage-maker' ); ?></h2></div>
								<div class="inside"><?php KSPPM_Admin::render_completeness( $post ); ?></div>
							</div>
							<!-- JSON-LD プレビュー (T14: 4 番目、技術情報は最下段) -->
							<div class="postbox">
								<div class="postbox-header"><h2><?php esc_html_e( 'JSON-LD プレビュー', 'kashiwazaki-seo-profilepage-maker' ); ?></h2></div>
								<div class="inside"><?php KSPPM_Admin::render_jsonld_preview( $post ); ?></div>
							</div>
						<?php endif; ?>

					</div><!-- /postbox-container-1 -->
				</div><!-- /post-body -->
				<br class="clear">
			</div><!-- /poststuff -->

			<!-- T3 + U13: Sticky 保存バー、初期状態は正直に -->
			<div class="ksppm-sticky-bar">
				<?php if ( $is_new ) : ?>
					<span class="ksppm-sticky-bar__status ksppm-sticky-bar__status--unsaved"><?php esc_html_e( '未作成', 'kashiwazaki-seo-profilepage-maker' ); ?></span>
				<?php else : ?>
					<span class="ksppm-sticky-bar__status"><?php esc_html_e( '保存済み', 'kashiwazaki-seo-profilepage-maker' ); ?></span>
				<?php endif; ?>
				<span class="ksppm-sticky-bar__spacer"></span>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ); ?>" class="button"><?php esc_html_e( '一覧に戻る', 'kashiwazaki-seo-profilepage-maker' ); ?></a>
				<?php
				$btn_label = $is_new
					? __( '作成して公開', 'kashiwazaki-seo-profilepage-maker' )
					: ( $status === 'publish'
						? __( '更新して公開', 'kashiwazaki-seo-profilepage-maker' )
						: ( $status === 'draft'
							? __( '下書きとして保存', 'kashiwazaki-seo-profilepage-maker' )
							: __( '変更を保存', 'kashiwazaki-seo-profilepage-maker' ) ) );
				?>
				<button type="submit" class="button button-primary button-large"><?php echo esc_html( $btn_label ); ?></button>
			</div>
		</form>
		<?php
		self::render_footer();
	}

	/**
	 * (v0.0.13-beta): transient 保存時に sensitive=true フィールドを除外
	 * 背景: $_POST 全体を 5 分 transient に保存していたため、PII (生年月日 / 出生地 / 性別 / 体重 / メール / 電話) が
	 *      options テーブルに平文保存される (= バックアップ・ログに残る) リスクがあった。
	 * 対策: KSPPM_Fields: get_all_fields() の sensitive=true キーに対応する `ksppm_{key}` を transient データから除外。
	 */
	protected static function strip_sensitive_post( $post_data ) {
		if ( ! is_array( $post_data ) ) return $post_data;
		$out = $post_data;
		foreach ( KSPPM_Fields::get_all_fields() as $key => $field ) {
			if ( ! empty( $field['sensitive'] ) ) {
				unset( $out[ 'ksppm_' . $key ] );
			}
		}
		return $out;
	}

	/**
	 * フォーム保存処理 (admin_post_ksppm_save)
	 */
	public static function handle_save() {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! isset( $_POST['ksppm_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['ksppm_meta_nonce'] ), 'ksppm_save_' . $post_id ) ) {
			wp_die( __( 'セキュリティチェックに失敗しました。', 'kashiwazaki-seo-profilepage-maker' ) );
		}
		// (v0.0.13-beta): 新規/既存編集ともに CPT 固有の post_type_object->cap も確認
		// custom capability_type ('ksppm_profile') 時、'edit_posts' は CPT 専用 cap (edit_ksppm_profiles) に map される。
		// 既存編集は 'edit_post' で post_id を渡し、個別認可も併用。新規時は post_id=0 のため edit_posts のみ。
		$post_type_object = get_post_type_object( KSPPM_CPT );
		$cap_edit_posts   = $post_type_object && isset( $post_type_object->cap->edit_posts ) ? $post_type_object->cap->edit_posts : 'edit_posts';
		if ( $post_id ) {
			// F01: post_type ガードのみに集約 (page ID=7 含む全ての非 KSPPM_CPT 投稿を拒否)。
			// site-specific な ID 7 ハードコードを排除し、配布性を確保。
			// wp_update_post に post_type=KSPPM_CPT を渡して他 post type を CPT 変換する脆弱性も同時に防御。
			$existing_post = get_post( $post_id );
			if ( ! $existing_post || $existing_post->post_type !== KSPPM_CPT ) {
				wp_die( __( '指定された投稿はプロフィール (KSPPM) ではありません。', 'kashiwazaki-seo-profilepage-maker' ) );
			}
			if ( ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( $cap_edit_posts ) ) {
				wp_die( __( '権限がありません。', 'kashiwazaki-seo-profilepage-maker' ) );
			}
		} else {
			if ( ! current_user_can( $cap_edit_posts ) ) {
				wp_die( __( '権限がありません。', 'kashiwazaki-seo-profilepage-maker' ) );
			}
		}

		$title  = isset( $_POST['post_title'] ) ? sanitize_text_field( wp_unslash( $_POST['post_title'] ) ) : '';
		$slug   = isset( $_POST['post_name'] ) ? sanitize_title( wp_unslash( $_POST['post_name'] ) ) : '';
		// (D5) WP は $_POST にスラッシュを付加して渡すため、使用前に wp_unslash() で除去するのが公式必須イディオム。
		//   post_status は whitelist + strict 比較のため現状の注入実害は無いが、正当性のため unslash を通す。
		$status_raw = isset( $_POST['post_status'] ) ? sanitize_key( wp_unslash( $_POST['post_status'] ) ) : '';
		$status     = in_array( $status_raw, array( 'publish', 'draft', 'private', 'pending' ), true ) ? $status_raw : 'publish';

		// (v0.0.13-beta): publish/private 状態の post 作成・更新には publish_posts 相当の cap 必要
		// custom capability_type 時は post_type_object->cap->publish_posts (= publish_ksppm_profiles 等) を使用
		if ( in_array( $status, array( 'publish', 'private' ), true ) ) {
			$cap_publish = $post_type_object && isset( $post_type_object->cap->publish_posts ) ? $post_type_object->cap->publish_posts : 'publish_posts';
			if ( ! current_user_can( $cap_publish ) ) {
				// publish_posts 権限なしなら draft に強制 downgrade (WP 標準と同等挙動)
				$status = 'draft';
			}
		}

		if ( empty( $title ) ) {
			set_transient( 'ksppm_form_error_' . get_current_user_id(), 'title_empty', 60 );
			// T4: _POST 全体を transient に保存 → render_form で復元 (入力データ消失防止)
			set_transient( 'ksppm_form_data_' . get_current_user_id(), self::strip_sensitive_post( wp_unslash( $_POST ) ), 300 );
			if ( $post_id ) {
				$redirect = add_query_arg( array( 'action' => 'edit', 'post_id' => $post_id, 'msg' => 'title_empty' ), admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
			} else {
				$redirect = add_query_arg( array( 'action' => 'new', 'msg' => 'title_empty' ), admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
			}
			wp_safe_redirect( $redirect );
			exit;
		}

		$postdata = array(
			'post_title'  => $title,
			'post_name'   => $slug,
			'post_status' => $status,
			'post_type'   => KSPPM_CPT,
		);
		if ( $post_id ) {
			$postdata['ID'] = $post_id;
			$result = wp_update_post( $postdata, true );
		} else {
			$result = wp_insert_post( $postdata, true );
			if ( ! is_wp_error( $result ) ) {
				$post_id = $result;
			}
		}

		if ( is_wp_error( $result ) ) {
			set_transient( 'ksppm_form_error_' . get_current_user_id(), 'wp_error:' . $result->get_error_message(), 60 );
			// T4: _POST を transient に保存 (失敗時データ復元)
			set_transient( 'ksppm_form_data_' . get_current_user_id(), self::strip_sensitive_post( wp_unslash( $_POST ) ), 300 );
			if ( $post_id ) {
				$redirect = add_query_arg( array( 'action' => 'edit', 'post_id' => $post_id, 'msg' => 'save_failed' ), admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
			} else {
				$redirect = add_query_arg( array( 'action' => 'new', 'msg' => 'save_failed' ), admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
			}
			wp_safe_redirect( $redirect );
			exit;
		}

		// アイキャッチ
		if ( isset( $_POST['_thumbnail_id'] ) ) {
			$tid = KSPPM_Meta::sanitize_image_id( $_POST['_thumbnail_id'] ); // F2: 画像添付検証
			if ( $tid ) {
				set_post_thumbnail( $post_id, $tid );
			} else {
				delete_post_thumbnail( $post_id );
			}
		}

		// F15: meta フィールド保存ループ中の `updated_post_meta` hook 連鎖を一時停止し、
		// 末尾で 1 度だけ KSPPM_Schema::flush_cache を呼ぶ。フィールド数 50+ × hook 発火による
		// 重複 delete_transient (N+1 ストーム) を回避する。例外時も remove/add の対称性を保証。
		remove_action( 'updated_post_meta', array( 'KSPPM_Schema', 'maybe_flush_on_meta_update' ), 10 );
		remove_action( 'added_post_meta',   array( 'KSPPM_Schema', 'maybe_flush_on_meta_update' ), 10 );
		remove_action( 'deleted_post_meta', array( 'KSPPM_Schema', 'maybe_flush_on_meta_update' ), 10 );
		try {
		// meta フィールド保存 (KSPPM_Meta: save が wp_verify_nonce を見るが、ここでは直接呼ぶ)
		$fields = KSPPM_Fields::get_all_fields();
		foreach ( $fields as $key => $field ) {
				// (custom blocks, spec M1): blocks は POST key が ksppm_custom_blocks_json (特殊) で
				// 汎用ループ ('ksppm_'.$key) の対象外。ループ後に save_custom_blocks() で専用保存する。
				if ( $field['type'] === 'blocks' ) {
					continue;
				}
			$input_key = 'ksppm_' . $key;
			$meta_key  = KSPPM_META_PREFIX . $key;

			if ( ! isset( $_POST[ $input_key ] ) ) {
				if ( $field['type'] === 'checkbox' ) {
					update_post_meta( $post_id, $meta_key, 0 );
				}
				continue;
			}

			$raw = $_POST[ $input_key ];
			// F04: scalar フィールドに配列が来た場合の型ガード。
			//   ksppm_birth_date[]=x のような入力で $raw が array になると、
			//   PHP 8 で preg_match()/sanitize 系に array を渡して TypeError (fatal) になる。
			//   repeater のみ配列を期待し、それ以外の型は scalar 以外を空値に倒す。
			if ( $field['type'] !== 'repeater' && ! is_scalar( $raw ) ) {
				if ( $field['type'] === 'checkbox' ) {
					update_post_meta( $post_id, $meta_key, 0 );
				} else {
					update_post_meta( $post_id, $meta_key, '' );
				}
				continue;
			}
			switch ( $field['type'] ) {
				case 'email':    $value = sanitize_email( wp_unslash( $raw ) ); break;
				case 'url':      $value = esc_url_raw( wp_unslash( $raw ) ); break;
				case 'date':
					if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
						$value = $raw;
					} else { $value = ''; }
					break;
				case 'number':   $value = is_numeric( $raw ) ? floatval( $raw ) : ''; break;
				case 'checkbox': $value = $raw ? 1 : 0; break;
				case 'select':
					// F10: 他 case (email/url/textarea/wysiwyg/default) と同じく wp_unslash を経由させ、
					// アポストロフィ等を含む option key が magic quotes 差で isset() 不一致を起こすのを防ぐ。
					$opts      = isset( $field['options'] ) ? $field['options'] : array();
					$raw_value = wp_unslash( $raw );
					$value     = isset( $opts[ $raw_value ] ) ? sanitize_text_field( $raw_value ) : '';
					break;
				case 'textarea': $value = sanitize_textarea_field( wp_unslash( $raw ) ); break;
				case 'wysiwyg':  $value = wp_kses_post( wp_unslash( $raw ) ); break;
				case 'image':    $value = KSPPM_Meta::sanitize_image_id( $raw ); break; // F2: 画像添付検証
				case 'repeater':
					$value = self::sanitize_repeater( $raw, isset( $field['item_fields'] ) ? $field['item_fields'] : array() );
					// wp_slash: JSON 内の \ を WP の magic quotes 互換に保護 (KSPPM_Meta: save と統一)
					$value = wp_slash( wp_json_encode( $value, JSON_UNESCAPED_UNICODE ) );
					break;
				default:         $value = sanitize_text_field( wp_unslash( $raw ) );
			}
			update_post_meta( $post_id, $meta_key, $value );
		}
		// (custom blocks, spec S4): ブロックは単一 JSON フィールド ksppm_custom_blocks_json から保存。
		self::save_custom_blocks( $post_id );

		// (multiprefix S4-3/S3-1): per-profile URL プレフィックス保存 + 変更履歴 (旧→新 301 用)。
		// _ksppm_url_prefix は KSPPM_Fields の定義外 (ルーティング用メタ) のため fields ループとは別に処理。
		$registry       = KSPPM_CPT::get_url_prefixes();
		$default_prefix = KSPPM_CPT::default_url_prefix();
		$raw_prefix     = ( isset( $_POST['ksppm_url_prefix'] ) && is_scalar( $_POST['ksppm_url_prefix'] ) ) ? (string) wp_unslash( $_POST['ksppm_url_prefix'] ) : '';
		$new_prefix     = KSPPM_CPT::normalize_url_prefix( $raw_prefix );
		if ( $new_prefix === '' || ! in_array( $new_prefix, $registry, true ) ) {
			$new_prefix = $default_prefix; // レジストリ外・不正は profile にフォールバック
		}
		$old_prefix = KSPPM_CPT::normalize_url_prefix( (string) get_post_meta( $post_id, KSPPM_META_PREFIX . 'url_prefix', true ) );
		update_post_meta( $post_id, KSPPM_META_PREFIX . 'url_prefix', $new_prefix );
		if ( $old_prefix !== '' && $old_prefix !== $new_prefix ) {
			$hist = get_post_meta( $post_id, KSPPM_META_PREFIX . 'url_prefix_history', true );
			if ( ! is_array( $hist ) ) $hist = array();
			$hist = array_values( array_diff( $hist, array( $old_prefix, $new_prefix ) ) );
			array_unshift( $hist, $old_prefix );
			$hist = array_slice( $hist, 0, 5 ); // 最大 5 件
			update_post_meta( $post_id, KSPPM_META_PREFIX . 'url_prefix_history', $hist );
		}

		update_post_meta( $post_id, KSPPM_META_PREFIX . 'schema_version', 1 );

		// F1: 日付の時系列整合チェック (保存時、値は保持し警告のみ通知)。
		$date_warnings = array();
		foreach ( array(
			array( 'articles', 'date', 'date_modified', __( '学術論文', 'kashiwazaki-seo-profilepage-maker' ) ),
			array( 'media_mentions', 'date', 'date_modified', __( '取材・執筆・監修', 'kashiwazaki-seo-profilepage-maker' ) ),
			array( 'speeches', 'date', 'end_date', __( '講演・セミナー', 'kashiwazaki-seo-profilepage-maker' ) ),
		) as $cp ) {
			$rows = json_decode( (string) get_post_meta( $post_id, KSPPM_META_PREFIX . $cp[0], true ), true );
			if ( ! is_array( $rows ) ) continue;
			foreach ( $rows as $idx => $r ) {
				$ds = isset( $r[ $cp[1] ] ) ? (string) $r[ $cp[1] ] : '';
				$de = isset( $r[ $cp[2] ] ) ? (string) $r[ $cp[2] ] : '';
				// 日付は YYYY-MM-DD 検証済のため文字列比較で時系列順序を判定可能。
				if ( $ds !== '' && $de !== '' && $de < $ds ) {
					$date_warnings[] = sprintf( '%s #%d (%s < %s)', $cp[3], (int) $idx + 1, $de, $ds );
				}
			}
		}
		if ( $date_warnings ) {
			set_transient( 'ksppm_form_datewarn_' . get_current_user_id(), implode( ' / ', $date_warnings ), 60 );
		}
		} finally {
			// F15: hook を必ず復元 (exception 経路を含む) してから flush_cache を 1 回だけ呼ぶ
			add_action( 'updated_post_meta', array( 'KSPPM_Schema', 'maybe_flush_on_meta_update' ), 10, 4 );
			add_action( 'added_post_meta',   array( 'KSPPM_Schema', 'maybe_flush_on_meta_update' ), 10, 4 );
			add_action( 'deleted_post_meta', array( 'KSPPM_Schema', 'maybe_flush_on_meta_update' ), 10, 4 );
		}
		KSPPM_Schema::flush_cache( $post_id );

		wp_safe_redirect( add_query_arg(
			array( 'action' => 'edit', 'post_id' => $post_id, 'msg' => 'saved' ),
			admin_url( 'admin.php?page=' . self::PAGE_SLUG )
		) );
		exit;
	}

	/**
	 * 削除処理
	 */
	public static function handle_delete() {
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		if ( ! $post_id || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), 'ksppm_delete_' . $post_id ) ) {
			wp_die( __( 'セキュリティチェックに失敗しました。', 'kashiwazaki-seo-profilepage-maker' ) );
		}
		if ( ! current_user_can( 'delete_post', $post_id ) ) {
			wp_die( __( '権限がありません。', 'kashiwazaki-seo-profilepage-maker' ) );
		}
		// F02: handle_save と非対称だった post_type ガードを追加。
		// nonce + capability を通過しても、対象が KSPPM_CPT 以外なら force delete を拒否する (page 7 含む)。
		$existing_post = get_post( $post_id );
		if ( ! $existing_post || $existing_post->post_type !== KSPPM_CPT ) {
			wp_die( __( '指定された投稿はプロフィール (KSPPM) ではありません。', 'kashiwazaki-seo-profilepage-maker' ) );
		}
		// 完全削除 (show_ui=false でゴミ箱画面が無いため、復元手段なしの trash 状態を作らない)
		// 削除前に画面側で confirm() を取っているため、二重確認不要
		wp_delete_post( $post_id, true );
		wp_safe_redirect( add_query_arg( 'msg', 'deleted', admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) );
		exit;
	}

	/**
	 * (custom blocks, spec S4) 単一 JSON フィールド ksppm_custom_blocks_json を decode・構造検証・
	 * サニタイズして _ksppm_custom_blocks に保存する。
	 * E1: decode 失敗 / 256KB 超 / 未送信 は update せず既存保持 (空上書きによるデータ消失を防止)。
	 * 構造検証: whitelist キー (id/title/items + heading/description/url/image) のみ採用、型強制、上限適用。
	 */
	protected static function save_custom_blocks( $post_id ) {
		// E1: 未送信 (JS 無効等) は既存保持
		if ( ! isset( $_POST['ksppm_custom_blocks_json'] ) || ! is_scalar( $_POST['ksppm_custom_blocks_json'] ) ) {
			return;
		}
		$raw = (string) wp_unslash( $_POST['ksppm_custom_blocks_json'] );
		// E1: 巨大 POST は拒否 (既存保持)
		if ( strlen( $raw ) > 262144 ) {
			return;
		}
		$decoded = json_decode( $raw, true );
		// E1: decode 失敗 (切り詰め/破損) は update せず既存保持。意図的な全削除は '[]' が array decode された場合のみ反映。
		if ( ! is_array( $decoded ) ) {
			return;
		}

		$max_blocks = defined( 'KSPPM_MAX_BLOCKS' ) ? (int) KSPPM_MAX_BLOCKS : 20;
		$max_items  = defined( 'KSPPM_MAX_BLOCK_ITEMS' ) ? (int) KSPPM_MAX_BLOCK_ITEMS : 50;
		$clean      = array();
		$used_ids   = array();

		foreach ( $decoded as $blk ) {
			if ( count( $clean ) >= $max_blocks ) break;
			if ( ! is_array( $blk ) ) continue;

			$title = ( isset( $blk['title'] ) && is_scalar( $blk['title'] ) ) ? sanitize_text_field( (string) $blk['title'] ) : '';
			$title = self::ksppm_truncate( $title, 200 );

			$items     = array();
			$raw_items = ( isset( $blk['items'] ) && is_array( $blk['items'] ) ) ? $blk['items'] : array();
			foreach ( $raw_items as $it ) {
				if ( count( $items ) >= $max_items ) break;
				if ( ! is_array( $it ) ) continue;
				$heading = ( isset( $it['heading'] ) && is_scalar( $it['heading'] ) ) ? self::ksppm_truncate( sanitize_text_field( (string) $it['heading'] ), 200 ) : '';
				$desc    = ( isset( $it['description'] ) && is_scalar( $it['description'] ) ) ? self::ksppm_truncate( sanitize_textarea_field( (string) $it['description'] ), 5000 ) : '';
				$url     = ( isset( $it['url'] ) && is_scalar( $it['url'] ) ) ? esc_url_raw( (string) $it['url'] ) : '';
				$image   = isset( $it['image'] ) ? KSPPM_Meta::sanitize_image_id( $it['image'] ) : 0; // F2/D6
				// 全空項目はスキップ
				if ( $heading === '' && $desc === '' && $url === '' && $image === 0 ) continue;
				$items[] = array( 'heading' => $heading, 'description' => $desc, 'url' => $url, 'image' => $image );
			}

			// title 空 かつ items 全空 のブロックは除去 (spec D7)
			if ( $title === '' && empty( $items ) ) continue;

			// E4: anchor id 正規化 + 重複一意化
			$id = ( isset( $blk['id'] ) && is_scalar( $blk['id'] ) ) ? (string) $blk['id'] : '';
			$id = self::normalize_block_id( $id, $used_ids );
			$used_ids[] = $id;

			$clean[] = array( 'id' => $id, 'title' => $title, 'items' => $items );
		}

		update_post_meta( $post_id, KSPPM_META_PREFIX . 'custom_blocks', wp_slash( wp_json_encode( $clean, JSON_UNESCAPED_UNICODE ) ) );
		// 新 UI 経由で保存 = 移行確定 (旧 free_tools/partner_tools は以後 fallback されない)
		update_post_meta( $post_id, KSPPM_META_PREFIX . 'blocks_migrated', 1 );
	}

	/**
	 * (custom blocks, spec E4) ブロック anchor id を正規化し、$used 内で一意化する。
	 * `^[a-z0-9-]{1,60}$` に sanitize。空/不正は新規 uid 生成。重複は -2,-3 suffix。
	 */
	protected static function normalize_block_id( $id, $used ) {
		$id = strtolower( (string) $id );
		$id = preg_replace( '/[^a-z0-9\-]/', '-', $id );
		$id = preg_replace( '/-+/', '-', $id );
		$id = trim( $id, '-' );
		if ( $id === '' ) {
			$id = 'ksppm-block-' . substr( md5( uniqid( '', true ) ), 0, 8 );
		}
		if ( strlen( $id ) > 60 ) {
			$id = substr( $id, 0, 60 );
			$id = rtrim( $id, '-' );
		}
		if ( in_array( $id, $used, true ) ) {
			$base = substr( $id, 0, 55 );
			$base = rtrim( $base, '-' );
			$n    = 2;
			do {
				$cand = $base . '-' . $n;
				$n++;
			} while ( in_array( $cand, $used, true ) && $n < 1000 );
			$id = $cand;
		}
		return $id;
	}

	/** mbstring 安全な文字数トリミング */
	protected static function ksppm_truncate( $s, $len ) {
		if ( function_exists( 'mb_substr' ) ) return mb_substr( $s, 0, $len );
		return substr( $s, 0, $len );
	}

	/**
	 * repeater sanitize (KSPPM_Meta: sanitize_repeater のコピー)
	 */
	protected static function sanitize_repeater( $raw, $item_fields ) {
		if ( ! is_array( $raw ) ) return array();
		$out = array();
		foreach ( $raw as $item ) {
			if ( ! is_array( $item ) ) continue;
			$row = array();
			foreach ( $item_fields as $sub ) {
				$k = $sub['key'];
				$val = isset( $item[ $k ] ) ? $item[ $k ] : '';
				// F8: 配列攻撃 (ksppm_books[0][date][]=x) で preg_match/sanitize に配列→PHP8 TypeError(fatal) 防止。scalar以外は空値。
				if ( ! is_scalar( $val ) ) { $row[ $k ] = ( $sub['type'] === 'image' ) ? 0 : ''; continue; }
				switch ( $sub['type'] ) {
					case 'email': $row[ $k ] = sanitize_email( wp_unslash( $val ) ); break;
					case 'url':   $row[ $k ] = esc_url_raw( wp_unslash( $val ) ); break;
					case 'date':
						if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $val, $mm ) && checkdate( (int) $mm[2], (int) $mm[3], (int) $mm[1] ) ) {
							$row[ $k ] = $val;
						} else { $row[ $k ] = ''; }
						break;
					case 'number': $row[ $k ] = is_numeric( $val ) ? floatval( $val ) : ''; break;
					case 'textarea': $row[ $k ] = sanitize_textarea_field( wp_unslash( $val ) ); break;
					case 'image': $row[ $k ] = KSPPM_Meta::sanitize_image_id( $val ); break; // F2: 画像添付検証
					default:      $row[ $k ] = sanitize_text_field( wp_unslash( $val ) );
				}
			}
			$has_value = false;
			foreach ( $row as $v ) {
				if ( ! ( $v === '' || $v === 0 || $v === 0.0 || $v === '0' || $v === null ) ) { $has_value = true; break; }
			}
			if ( $has_value ) $out[] = $row;
		}
		return $out;
	}

	/**
	 * 設定画面
	 */
	protected static function render_settings() {
		$settings = get_option( KSPPM_OPTION_KEY, array() );
		// (v0.0.18-beta): posts_per_page=-1 → LIMIT 500 (render_settings の select 用、大規模時防御)
		$profiles = get_posts( array(
			'post_type'      => KSPPM_CPT, 'posts_per_page' => 500,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'no_found_rows'  => true,
		) );
		$main_post = (int) ( $settings['main_profile_post'] ?? 0 );
		$enable_jsonld = isset( $settings['enable_jsonld'] ) ? (int) $settings['enable_jsonld'] : 1;
		$enable_breadcrumb = isset( $settings['enable_breadcrumb'] ) ? (int) $settings['enable_breadcrumb'] : 1;
		$enable_speakable = isset( $settings['enable_speakable'] ) ? (int) $settings['enable_speakable'] : 1;
		$jsonld_position = $settings['jsonld_position'] ?? 'head';
		$post_type_behavior = $settings['post_type_behavior'] ?? 'custom';
		$root_rewrite_enabled = isset( $settings['root_rewrite_enabled'] ) ? (int) $settings['root_rewrite_enabled'] : 0;
		$url_prefix           = isset( $settings['url_prefix'] ) ? (string) $settings['url_prefix'] : '';
		// (multiprefix): プレフィックス・レジストリ (profile 先頭) と削除可否判定用の使用数
		$registry             = class_exists( 'KSPPM_CPT' ) ? KSPPM_CPT::get_url_prefixes() : array( 'profile' );
		$default_prefix       = class_exists( 'KSPPM_CPT' ) ? KSPPM_CPT::default_url_prefix() : 'profile';
		$max_prefixes         = defined( 'KSPPM_MAX_PREFIXES' ) ? (int) KSPPM_MAX_PREFIXES : 20;
		// integrate_skills_into_hobbies と display_order_preset の UI 入力欄を追加
		$integrate_skills_into_hobbies = isset( $settings['integrate_skills_into_hobbies'] ) ? (int) $settings['integrate_skills_into_hobbies'] : 0;
		$display_order_preset          = isset( $settings['display_order_preset'] ) && in_array( $settings['display_order_preset'], array( 'default', 'page7_like' ), true ) ? $settings['display_order_preset'] : 'default';
		$sns_show_label                = isset( $settings['sns_show_label'] ) ? (int) $settings['sns_show_label'] : 0;
		$opt = esc_attr( KSPPM_OPTION_KEY );
		$home = home_url( '/' );

		$preview_map = array();
		foreach ( $profiles as $p ) {
			$url = ( 'publish' === $p->post_status )
				? get_permalink( $p->ID )
				: get_preview_post_link( $p->ID );
			$preview_map[ (int) $p->ID ] = array(
				'url'    => $url,
				'status' => $p->post_status,
			);
		}

		self::render_header( 'settings' );
		settings_errors( 'ksppm_settings' );
		?>
		<form method="post" action="options.php" class="ksppm-settings-form ksppm-settings-form--enhanced" data-ksppm-dirty-form>
			<?php settings_fields( 'ksppm_settings_group' ); ?>

			<section class="ksppm-settings-card" aria-labelledby="ksppm-card-basic">
				<header class="ksppm-settings-card__header">
					<h2 class="ksppm-settings-card__title" id="ksppm-card-basic"><span class="dashicons dashicons-admin-users" aria-hidden="true"></span><?php esc_html_e( '基本設定', 'kashiwazaki-seo-profilepage-maker' ); ?></h2>
					<p class="ksppm-settings-card__desc"><?php esc_html_e( 'ショートコード省略時に表示される代表プロフィールを指定します。', 'kashiwazaki-seo-profilepage-maker' ); ?></p>
				</header>
				<div class="ksppm-settings-card__body">
					<div class="ksppm-field">
						<label class="ksppm-field__label" for="ksppm_main"><?php esc_html_e( 'メインプロフィール', 'kashiwazaki-seo-profilepage-maker' ); ?></label>
						<div class="ksppm-field__control">
							<select id="ksppm_main" name="<?php echo $opt; ?>[main_profile_post]" aria-describedby="ksppm_main_hint" data-ksppm-preview-select="<?php echo esc_attr( wp_json_encode( $preview_map ) ); ?>">
								<option value="0"><?php esc_html_e( '— 最新を自動選択 —', 'kashiwazaki-seo-profilepage-maker' ); ?></option>
								<?php foreach ( $profiles as $p ) : ?>
									<option value="<?php echo esc_attr( $p->ID ); ?>" <?php selected( $main_post, $p->ID ); ?>><?php echo esc_html( '#' . $p->ID . ' ' . $p->post_title ); ?></option>
								<?php endforeach; ?>
							</select>
							<span class="ksppm-field__preview" data-ksppm-preview-target hidden>
								<a href="#" target="_blank" rel="noopener" class="button button-secondary"><span class="dashicons dashicons-visibility" aria-hidden="true"></span><?php esc_html_e( 'プレビュー', 'kashiwazaki-seo-profilepage-maker' ); ?></a>
								<span class="ksppm-field__preview-status"></span>
							</span>
							<p class="description" id="ksppm_main_hint"><?php echo wp_kses( __( '<code>[kashiwazaki_profilepage]</code> ショートコード (id 省略時) で表示されるプロフィール。指定なしの場合は最新の公開プロフィールが使われます。', 'kashiwazaki-seo-profilepage-maker' ), array( 'code' => array() ) ); ?></p>
						</div>
					</div>
				</div>
			</section>

			<section class="ksppm-settings-card" aria-labelledby="ksppm-card-jsonld">
				<header class="ksppm-settings-card__header">
					<h2 class="ksppm-settings-card__title" id="ksppm-card-jsonld"><span class="dashicons dashicons-embed-generic" aria-hidden="true"></span><?php esc_html_e( '構造化データ (JSON-LD)', 'kashiwazaki-seo-profilepage-maker' ); ?></h2>
					<p class="ksppm-settings-card__desc"><?php esc_html_e( 'schema.org ProfilePage + Person の出力、speakable、パンくずを制御します。', 'kashiwazaki-seo-profilepage-maker' ); ?></p>
				</header>
				<div class="ksppm-settings-card__body">
					<div class="ksppm-field ksppm-field--checkbox">
						<label for="ksppm_enable_jsonld"><input type="checkbox" id="ksppm_enable_jsonld" name="<?php echo $opt; ?>[enable_jsonld]" value="1" <?php checked( $enable_jsonld, 1 ); ?> aria-describedby="ksppm_enable_jsonld_hint"> <span class="ksppm-field__label-text"><?php esc_html_e( 'JSON-LD を出力する', 'kashiwazaki-seo-profilepage-maker' ); ?></span></label>
						<p class="description" id="ksppm_enable_jsonld_hint"><?php esc_html_e( 'ProfilePage + Person 構造化データをフロントに自動出力します。', 'kashiwazaki-seo-profilepage-maker' ); ?></p>
					</div>
					<div class="ksppm-field">
						<label class="ksppm-field__label" for="ksppm_jsonld_position"><?php esc_html_e( '出力位置', 'kashiwazaki-seo-profilepage-maker' ); ?></label>
						<div class="ksppm-field__control">
							<select id="ksppm_jsonld_position" name="<?php echo $opt; ?>[jsonld_position]" aria-describedby="ksppm_jsonld_position_hint">
								<option value="head" <?php selected( $jsonld_position, 'head' ); ?>>wp_head (<?php esc_html_e( '推奨', 'kashiwazaki-seo-profilepage-maker' ); ?>)</option>
								<option value="footer" <?php selected( $jsonld_position, 'footer' ); ?>>wp_footer</option>
							</select>
							<p class="description" id="ksppm_jsonld_position_hint"><?php esc_html_e( '通常は wp_head 推奨。テーマやキャッシュ系プラグインと head 出力が衝突する場合のみ footer を選択。', 'kashiwazaki-seo-profilepage-maker' ); ?></p>
						</div>
					</div>
					<div class="ksppm-field ksppm-field--checkbox">
						<label for="ksppm_enable_speakable"><input type="checkbox" id="ksppm_enable_speakable" name="<?php echo $opt; ?>[enable_speakable]" value="1" <?php checked( $enable_speakable, 1 ); ?> aria-describedby="ksppm_enable_speakable_hint"> <span class="ksppm-field__label-text"><?php esc_html_e( 'speakable プロパティを出力する', 'kashiwazaki-seo-profilepage-maker' ); ?></span></label>
						<p class="description" id="ksppm_enable_speakable_hint"><?php esc_html_e( 'AI 音声読み上げ (LLMO) 向けの speakable 指定をプロフィール見出し・概要に付与します。', 'kashiwazaki-seo-profilepage-maker' ); ?></p>
					</div>
					<div class="ksppm-field ksppm-field--checkbox">
						<label for="ksppm_enable_breadcrumb"><input type="checkbox" id="ksppm_enable_breadcrumb" name="<?php echo $opt; ?>[enable_breadcrumb]" value="1" <?php checked( $enable_breadcrumb, 1 ); ?> aria-describedby="ksppm_enable_breadcrumb_hint"> <span class="ksppm-field__label-text"><?php esc_html_e( 'BreadcrumbList を自動出力する', 'kashiwazaki-seo-profilepage-maker' ); ?></span></label>
						<p class="description" id="ksppm_enable_breadcrumb_hint"><?php esc_html_e( 'パンくず構造化データ (ホーム → プロフィール → 個人名) を自動出力します。', 'kashiwazaki-seo-profilepage-maker' ); ?></p>
					</div>
				</div>
			</section>

			<section class="ksppm-settings-card" aria-labelledby="ksppm-card-behavior">
				<header class="ksppm-settings-card__header">
					<h2 class="ksppm-settings-card__title" id="ksppm-card-behavior"><span class="dashicons dashicons-admin-post" aria-hidden="true"></span><?php esc_html_e( '投稿タイプ挙動', 'kashiwazaki-seo-profilepage-maker' ); ?></h2>
					<p class="ksppm-settings-card__desc"><?php esc_html_e( 'プロフィール CPT が WordPress 内でどう振る舞うかを切り替えます。', 'kashiwazaki-seo-profilepage-maker' ); ?></p>
				</header>
				<div class="ksppm-settings-card__body">
					<div class="ksppm-field">
						<label class="ksppm-field__label"><?php esc_html_e( 'URL プレフィックス・レジストリ', 'kashiwazaki-seo-profilepage-maker' ); ?></label>
						<div class="ksppm-field__control">
							<p class="description" id="ksppm_url_prefixes_hint"><?php echo wp_kses( __( 'プロフィールページの親ディレクトリ (プレフィックス) を複数登録できます。各プロフィールの編集画面で、ここに登録したプレフィックスを 1 つ選択します。<code>profile</code> はデフォルトとして常に登録され、削除できません。英数 - _ / のみ、予約 slug (wp-admin / feed 等) と既存固定ページ名は自動拒否。多段 <code>team/about</code> も可。空欄の行は無視されます。登録を削除するには欄を空にして保存しますが、<strong>使用中のプレフィックスは削除できません</strong> (先に各プロフィールの選択を変更してください)。', 'kashiwazaki-seo-profilepage-maker' ), array( 'code' => array(), 'strong' => array() ) ); ?></p>
							<table class="ksppm-prefix-registry widefat" style="max-width:640px;margin-top:8px;">
								<thead>
									<tr>
										<th scope="col"><?php esc_html_e( 'プレフィックス', 'kashiwazaki-seo-profilepage-maker' ); ?></th>
										<th scope="col"><?php esc_html_e( '公開 URL 形', 'kashiwazaki-seo-profilepage-maker' ); ?></th>
										<th scope="col"><?php esc_html_e( '使用中', 'kashiwazaki-seo-profilepage-maker' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $registry as $rp ) :
										$is_default = ( $rp === $default_prefix );
										$used       = self::count_profiles_using_prefix( $rp );
									?>
									<tr>
										<td>
											<input type="text" name="<?php echo $opt; ?>[url_prefixes][]" value="<?php echo esc_attr( $rp ); ?>" class="regular-text"<?php echo $is_default ? ' readonly' : ''; ?>>
											<?php if ( $is_default ) : ?><span class="description"><?php esc_html_e( '(デフォルト・削除不可)', 'kashiwazaki-seo-profilepage-maker' ); ?></span><?php endif; ?>
										</td>
										<td><code><?php echo esc_html( trailingslashit( $home ) . trailingslashit( $rp ) . '{slug}/' ); ?></code></td>
										<td><?php echo esc_html( $used > 0 ? sprintf( /* translators: %d: count */ _n( '%d 件', '%d 件', $used, 'kashiwazaki-seo-profilepage-maker' ), $used ) : '—' ); ?></td>
									</tr>
									<?php endforeach; ?>
									<?php
									$slots = max( 0, min( 3, $max_prefixes - count( $registry ) ) );
									for ( $i = 0; $i < $slots; $i++ ) :
									?>
									<tr>
										<td><input type="text" name="<?php echo $opt; ?>[url_prefixes][]" value="" class="regular-text" placeholder="<?php esc_attr_e( '例: people / team/about', 'kashiwazaki-seo-profilepage-maker' ); ?>"></td>
										<td><span class="description"><?php esc_html_e( '(空欄=未登録)', 'kashiwazaki-seo-profilepage-maker' ); ?></span></td>
										<td>—</td>
									</tr>
									<?php endfor; ?>
								</tbody>
							</table>
							<p class="description"><?php echo esc_html( sprintf( /* translators: %d: max */ __( '最大 %d 件まで登録できます。', 'kashiwazaki-seo-profilepage-maker' ), $max_prefixes ) ); ?></p>
						</div>
					</div>
					<div class="ksppm-field ksppm-field--checkbox">
						<label for="ksppm_root_rewrite_enabled"><input type="checkbox" id="ksppm_root_rewrite_enabled" name="<?php echo $opt; ?>[root_rewrite_enabled]" value="1" <?php checked( $root_rewrite_enabled, 1 ); ?> aria-describedby="ksppm_root_rewrite_enabled_hint"> <span class="ksppm-field__label-text"><?php esc_html_e( 'ルート URL (/{スラッグ}/) でプロフィールを公開する (非推奨)', 'kashiwazaki-seo-profilepage-maker' ); ?></span></label>
						<p class="description" id="ksppm_root_rewrite_enabled_hint"><?php esc_html_e( '非推奨 (後方互換用): マルチプレフィックスでは全プロフィールが必ずいずれかのプレフィックス配下 (最低 profile) で公開されるため、この設定は通常不要です。既存設定値は後方互換のため残されますが、プレフィックスが 1 件でも登録されている場合 (= 常時) ルート公開は併用されません。', 'kashiwazaki-seo-profilepage-maker' ); ?></p>
					</div>
					<div class="ksppm-field">
						<label class="ksppm-field__label" for="ksppm_post_type_behavior"><?php esc_html_e( '挙動モード', 'kashiwazaki-seo-profilepage-maker' ); ?></label>
						<div class="ksppm-field__control">
							<select id="ksppm_post_type_behavior" name="<?php echo $opt; ?>[post_type_behavior]" aria-describedby="ksppm_post_type_behavior_hint">
								<option value="custom" <?php selected( $post_type_behavior, 'custom' ); ?>><?php esc_html_e( 'プラグイン独自 CPT (推奨)', 'kashiwazaki-seo-profilepage-maker' ); ?></option>
								<option value="post" <?php selected( $post_type_behavior, 'post' ); ?>><?php esc_html_e( '投稿型 (post)', 'kashiwazaki-seo-profilepage-maker' ); ?></option>
								<option value="page" <?php selected( $post_type_behavior, 'page' ); ?>><?php esc_html_e( '固定ページ型 (page)', 'kashiwazaki-seo-profilepage-maker' ); ?></option>
							</select>
							<p class="description ksppm-field__hint-short" id="ksppm_post_type_behavior_hint"><?php esc_html_e( '3 モード全て動作確認済 (HTTP 200)。データ破壊なしで切替可能。', 'kashiwazaki-seo-profilepage-maker' ); ?></p>
							<details class="ksppm-field__details">
								<summary><?php esc_html_e( '各モードの詳細を見る', 'kashiwazaki-seo-profilepage-maker' ); ?></summary>
								<dl class="ksppm-field__details-list">
									<dt><span class="dashicons dashicons-shield" aria-hidden="true"></span><?php esc_html_e( 'プラグイン独自 CPT (推奨)', 'kashiwazaki-seo-profilepage-maker' ); ?></dt>
									<dd><?php esc_html_e( 'administrator / editor ロールに専用 capability を付与し、WP 組み込み権限と独立。テーマ依存なし。', 'kashiwazaki-seo-profilepage-maker' ); ?></dd>
									<dt><span class="dashicons dashicons-admin-post" aria-hidden="true"></span><?php esc_html_e( '投稿型 (post)', 'kashiwazaki-seo-profilepage-maker' ); ?></dt>
									<dd><?php esc_html_e( 'WP 投稿の権限・テーマ single レイアウトを継承。既存ブログ運用に同居しやすい。', 'kashiwazaki-seo-profilepage-maker' ); ?></dd>
									<dt><span class="dashicons dashicons-admin-page" aria-hidden="true"></span><?php esc_html_e( '固定ページ型 (page)', 'kashiwazaki-seo-profilepage-maker' ); ?></dt>
									<dd><?php esc_html_e( 'WP 固定ページの権限・階層 (post_parent / menu_order) を継承、静的プロフィールに最適。', 'kashiwazaki-seo-profilepage-maker' ); ?></dd>
								</dl>
								<p class="ksppm-field__details-note"><?php esc_html_e( '変更時は CPT を再登録し rewrite を自動 flush します。', 'kashiwazaki-seo-profilepage-maker' ); ?></p>
							</details>
						</div>
					</div>
				</div>
			</section>

			<!-- v0.0.21-beta 表示オプション (skills 統合 + セクション順序 preset) -->
			<section class="ksppm-settings-card" aria-labelledby="ksppm-card-display">
				<header class="ksppm-settings-card__header">
					<h2 class="ksppm-settings-card__title" id="ksppm-card-display"><span class="dashicons dashicons-visibility" aria-hidden="true"></span><?php esc_html_e( '表示オプション', 'kashiwazaki-seo-profilepage-maker' ); ?></h2>
					<p class="ksppm-settings-card__desc"><?php esc_html_e( 'プロフィール表示時の構成 (趣味・特技セクションへのスキル統合、セクション順序プリセット) を設定します。', 'kashiwazaki-seo-profilepage-maker' ); ?></p>
				</header>
				<div class="ksppm-settings-card__body">
					<div class="ksppm-field ksppm-field--checkbox">
						<label for="ksppm_integrate_skills_into_hobbies"><input type="checkbox" id="ksppm_integrate_skills_into_hobbies" name="<?php echo $opt; ?>[integrate_skills_into_hobbies]" value="1" <?php checked( $integrate_skills_into_hobbies, 1 ); ?> aria-describedby="ksppm_integrate_skills_hint"> <span class="ksppm-field__label-text"><?php esc_html_e( '「趣味・特技」セクションに「スキル」を統合表示する', 'kashiwazaki-seo-profilepage-maker' ); ?></span></label>
						<p class="description" id="ksppm_integrate_skills_hint"><?php esc_html_e( 'OFF (初期値): スキルは「専門性・知識」セクションのみ表示。ON: 「趣味・特技」セクションにスキル一覧 + 趣味テキストを統合表示し、「専門性」側からはスキル重複表示を抑止します (page 7 等のオリジナル構成と一致)。', 'kashiwazaki-seo-profilepage-maker' ); ?></p>
					</div>
					<div class="ksppm-field ksppm-field--checkbox">
						<label for="ksppm_sns_show_label"><input type="checkbox" id="ksppm_sns_show_label" name="<?php echo $opt; ?>[sns_show_label]" value="1" <?php checked( $sns_show_label, 1 ); ?> aria-describedby="ksppm_sns_show_label_hint"> <span class="ksppm-field__label-text"><?php esc_html_e( 'SNS / 外部プロフィール リンクに文字ラベルを表示する', 'kashiwazaki-seo-profilepage-maker' ); ?></span></label>
						<p class="description" id="ksppm_sns_show_label_hint"><?php esc_html_e( 'OFF (初期値): dashicons アイコンのみ表示 (シンプル)。ON: アイコン + ラベルテキストを並べて表示します。', 'kashiwazaki-seo-profilepage-maker' ); ?></p>
					</div>
					<div class="ksppm-field">
						<label class="ksppm-field__label" for="ksppm_display_order_preset"><?php esc_html_e( 'セクション表示順序 プリセット', 'kashiwazaki-seo-profilepage-maker' ); ?></label>
						<div class="ksppm-field__control">
							<select id="ksppm_display_order_preset" name="<?php echo $opt; ?>[display_order_preset]" aria-describedby="ksppm_display_order_preset_hint">
								<option value="default" <?php selected( $display_order_preset, 'default' ); ?>><?php esc_html_e( '標準 (経歴 → 専門性 → 書籍 → 論文 → 特許 → 商標 → 取材 → 講演 → ツール → 動画 → 趣味)', 'kashiwazaki-seo-profilepage-maker' ); ?></option>
								<option value="page7_like" <?php selected( $display_order_preset, 'page7_like' ); ?>><?php esc_html_e( 'page7 風 (来歴 → 基本情報 → SNS → 動画 → 現職 → 経歴 → 趣味 → ツール → 商標 → 論文 → 書籍 → 取材 → 講演)', 'kashiwazaki-seo-profilepage-maker' ); ?></option>
							</select>
							<p class="description" id="ksppm_display_order_preset_hint"><?php esc_html_e( '配布先のオリジナル固定ページに近いセクション順序に切り替えるプリセット。anchor id (#ksppm-bio 等) は維持されるため既存リンク互換性あり。', 'kashiwazaki-seo-profilepage-maker' ); ?></p>
						</div>
					</div>
				</div>
			</section>

			<div class="ksppm-settings-actions" role="region" aria-label="<?php esc_attr_e( '設定の保存', 'kashiwazaki-seo-profilepage-maker' ); ?>">
				<span class="ksppm-settings-actions__dirty" data-ksppm-dirty-banner hidden><span class="dashicons dashicons-warning" aria-hidden="true"></span><?php esc_html_e( '未保存の変更があります', 'kashiwazaki-seo-profilepage-maker' ); ?></span>
				<?php submit_button( __( '設定を保存', 'kashiwazaki-seo-profilepage-maker' ), 'primary', 'submit', false ); ?>
			</div>
		</form>
		<?php
		self::render_footer();
	}
}
