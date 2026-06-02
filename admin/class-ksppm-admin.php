<?php
/**
 * 管理画面: タブ・リピーター UI (UI/UX 15 件改修版)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KSPPM_Admin {

	public static function init() {
		add_action( 'add_meta_boxes_' . KSPPM_CPT, array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	public static function enqueue( $hook ) {
		global $post;
		$is_ksppm = ( $hook === 'post.php' || $hook === 'post-new.php' ) && is_a( $post, 'WP_Post' ) && $post->post_type === KSPPM_CPT;
		$is_settings = strpos( (string) $hook, 'kashiwazaki-seo-profilepage-maker' ) !== false
			|| ( isset( $_GET['page'] ) && $_GET['page'] === 'kashiwazaki-seo-profilepage-maker' );
		if ( ! $is_ksppm && ! $is_settings ) return;

		wp_enqueue_media();
		wp_enqueue_style( 'ksppm-admin', KSPPM_PLUGIN_URL . 'assets/css/admin.css', array( 'dashicons' ), KSPPM_VERSION );
		wp_enqueue_script( 'ksppm-admin', KSPPM_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery', 'jquery-ui-sortable' ), KSPPM_VERSION, true );
		// (v0.0.15-beta): REST namespace は register_rest_route 未実装のため export を削除済。
		wp_localize_script( 'ksppm-admin', 'KSPPM', array(
			'homeUrl'  => esc_url_raw( home_url( '/' ) ),
			// (v0.0.15-beta): admin.js 内の日本語ハードコードを i18n 経由に統合
			'i18n'     => array(
				'addRow'             => __( '行を追加', 'kashiwazaki-seo-profilepage-maker' ),
				'remove'             => __( '削除', 'kashiwazaki-seo-profilepage-maker' ),
				'selectImg'          => __( '画像を選択', 'kashiwazaki-seo-profilepage-maker' ),
				'change'             => __( '変更', 'kashiwazaki-seo-profilepage-maker' ),
				'unset'              => __( '解除', 'kashiwazaki-seo-profilepage-maker' ),
				'imgEmpty'           => __( 'クリックして画像を選択', 'kashiwazaki-seo-profilepage-maker' ),
				'dirtyChanges'       => __( '未保存の変更があります', 'kashiwazaki-seo-profilepage-maker' ),
				'unsaved'            => __( '未作成', 'kashiwazaki-seo-profilepage-maker' ),
				'saved'              => __( '保存済み', 'kashiwazaki-seo-profilepage-maker' ),
				'leaveConfirm'       => __( '入力内容が保存されていません。ページを離れますか?', 'kashiwazaki-seo-profilepage-maker' ),
				'copied'             => __( 'コピー済', 'kashiwazaki-seo-profilepage-maker' ),
				'copy'               => __( 'コピー', 'kashiwazaki-seo-profilepage-maker' ),
				'filledMark'         => __( '入力済', 'kashiwazaki-seo-profilepage-maker' ),
				// (v0.0.16): 設定タブ status labels + copy 系 title
				'statusPublish'      => __( '公開', 'kashiwazaki-seo-profilepage-maker' ),
				'statusDraft'        => __( '下書き', 'kashiwazaki-seo-profilepage-maker' ),
				'statusPrivate'      => __( '非公開', 'kashiwazaki-seo-profilepage-maker' ),
				'statusPending'      => __( '保留中', 'kashiwazaki-seo-profilepage-maker' ),
				'clickToCopy'        => __( 'クリックでコピー', 'kashiwazaki-seo-profilepage-maker' ),
				// (custom blocks) ブロックUI
				'blockAdd'           => __( 'ブロックを追加', 'kashiwazaki-seo-profilepage-maker' ),
				'blockRemove'        => __( 'ブロックを削除', 'kashiwazaki-seo-profilepage-maker' ),
				'blockTitle'         => __( 'ブロック名', 'kashiwazaki-seo-profilepage-maker' ),
				'blockTitlePh'       => __( '例: 公開中のツール / 出演メディア', 'kashiwazaki-seo-profilepage-maker' ),
				'blockUntitled'      => __( '(無題のブロック)', 'kashiwazaki-seo-profilepage-maker' ),
				'itemAdd'            => __( '項目を追加', 'kashiwazaki-seo-profilepage-maker' ),
				'itemRemove'         => __( '項目を削除', 'kashiwazaki-seo-profilepage-maker' ),
				'fHeading'           => __( '見出し', 'kashiwazaki-seo-profilepage-maker' ),
				'fDescription'       => __( '説明', 'kashiwazaki-seo-profilepage-maker' ),
				'fUrl'               => 'URL',
				'fImage'             => __( '画像', 'kashiwazaki-seo-profilepage-maker' ),
				'blockMaxReached'    => __( 'ブロック数の上限に達しました。', 'kashiwazaki-seo-profilepage-maker' ),
				'itemMaxReached'     => __( '項目数の上限に達しました。', 'kashiwazaki-seo-profilepage-maker' ),
			),
		) );
	}

	public static function add_meta_boxes() {
		add_meta_box(
			'ksppm-tabs',
			__( 'プロフィール詳細 (Kashiwazaki SEO ProfilePage Maker)', 'kashiwazaki-seo-profilepage-maker' ),
			array( __CLASS__, 'render_tabs' ),
			KSPPM_CPT,
			'normal',
			'high'
		);
		add_meta_box(
			'ksppm-jsonld',
			__( 'JSON-LD プレビュー', 'kashiwazaki-seo-profilepage-maker' ),
			array( __CLASS__, 'render_jsonld_preview' ),
			KSPPM_CPT,
			'side',
			'default'
		);
		add_meta_box(
			'ksppm-completeness',
			__( '完成度ゲージ (E-E-A-T)', 'kashiwazaki-seo-profilepage-maker' ),
			array( __CLASS__, 'render_completeness' ),
			KSPPM_CPT,
			'side',
			'default'
		);
	}

	/**
	 * タブ UI (T7: バッジ、T8: フィールドグルーピング)
	 */
	public static function render_tabs( $post ) {
		$groups = KSPPM_Fields::get_groups();
		?>
		<div class="ksppm-tabs">
			<ul class="ksppm-tab-nav" role="tablist">
				<?php $first = true; foreach ( $groups as $key => $group ) :
					$counts = self::count_filled_fields( $post->ID, $group['fields'] );
					?>
					<li role="presentation">
						<a href="#ksppm-tab-<?php echo esc_attr( $key ); ?>"
						   class="<?php echo $first ? 'active' : ''; ?>"
						   role="tab"
						   aria-controls="ksppm-tab-<?php echo esc_attr( $key ); ?>"
						   aria-selected="<?php echo $first ? 'true' : 'false'; ?>">
							<span class="ksppm-tab-label"><?php echo esc_html( $group['label'] ); ?></span>
							<span class="ksppm-tab-badge ksppm-tab-badge--<?php echo esc_attr( $counts['class'] ); ?>"><?php echo esc_html( $counts['filled'] . '/' . $counts['total'] ); ?></span>
						</a>
					</li>
				<?php $first = false; endforeach; ?>
			</ul>
			<?php $first = true; foreach ( $groups as $key => $group ) : ?>
				<div class="ksppm-tab-panel <?php echo $first ? 'active' : ''; ?>"
				     id="ksppm-tab-<?php echo esc_attr( $key ); ?>"
				     role="tabpanel"
				     aria-labelledby="ksppm-tab-trigger-<?php echo esc_attr( $key ); ?>">
					<?php self::render_field_group( $post->ID, $group['fields'] ); ?>
					<?php self::render_schema_summary( $group['fields'] ); ?>
				</div>
			<?php $first = false; endforeach; ?>
		</div>
		<?php
	}

	/**
	 * フィールド入力済 / 総数カウント (T7 バッジ用)
	 */
	protected static function count_filled_fields( $post_id, $fields ) {
		$total = count( $fields );
		$filled = 0;
		foreach ( $fields as $field ) {
			$value = KSPPM_Meta::get( $post_id, $field['key'] );
			if ( $field['type'] === 'blocks' ) {
				// (custom blocks): items を持つブロックが1つでもあれば filled (空配列は filled にしない)
				$has = false;
				if ( is_array( $value ) ) {
					foreach ( $value as $b ) {
						if ( is_array( $b ) && ! empty( $b['items'] ) ) { $has = true; break; }
					}
				}
				if ( $has ) $filled++;
			} elseif ( $field['type'] === 'repeater' ) {
				if ( is_array( $value ) && count( $value ) > 0 ) $filled++;
			} elseif ( $field['type'] === 'checkbox' ) {
				if ( (int) $value === 1 ) $filled++;
			} elseif ( $field['type'] === 'image' ) {
				if ( absint( $value ) > 0 ) $filled++;
			} else {
				if ( $value !== '' && $value !== null ) $filled++;
			}
		}
		$class = 'empty';
		if ( $total > 0 ) {
			if ( $filled === $total ) $class = 'filled';
			elseif ( $filled > 0 ) $class = 'partial';
		}
		return array( 'filled' => $filled, 'total' => $total, 'class' => $class );
	}

	/**
	 * フィールド群描画 (T8: ペアリング、checkbox は前フィールドとグループ化)
	 */
	protected static function render_field_group( $post_id, $fields ) {
		// show_X チェックボックスを直前のフィールドとペアリング
		// 例: birth_date + show_birth_date → 1 つの fieldset にまとめる
		$pairs = self::build_field_pairs( $fields );
		foreach ( $pairs as $group ) {
			if ( count( $group ) > 1 ) {
				echo '<fieldset class="ksppm-fieldgroup">';
				$legend = $group[0]['group_legend'] ?? '';
				if ( $legend ) echo '<legend>' . esc_html( $legend ) . '</legend>';
				echo '<div class="ksppm-fieldgroup__row ksppm-fieldgroup__row--2col">';
				foreach ( $group as $field ) {
					self::render_field_block( $post_id, $field );
				}
				echo '</div></fieldset>';
			} else {
				self::render_field_block( $post_id, $group[0] );
			}
		}
	}

	/**
	 * フィールド定義からペアを作る (T8、key 名マッチング版)
	 * checkbox 'show_X' は **対応する key 名 X** のフィールドとペアリング (定義順は問わない)
	 * 例: show_birth_date → birth_date とペア (隣接していなくても OK)
	 * 関連する複数の派生キー (birth_place など) も同じ show_X グループに含める場合は同名 prefix で
	 */
	protected static function build_field_pairs( $fields ) {
		// まず show_X チェックボックスをインデックス化
		$show_map = array(); // base_key => show_field
		foreach ( $fields as $field ) {
			if ( $field['type'] === 'checkbox' && strpos( $field['key'], 'show_' ) === 0 ) {
				$base = substr( $field['key'], 5 ); // 'show_' を除去
				$show_map[ $base ] = $field;
			}
		}
		// F12: 順序非依存にするため、valid_show_map には「ペアリング可能な base」のみを入れる。
		//   base が存在し、かつ base 自身が repeater/wysiwyg でない (= 下のペアリング条件と一致) 場合のみ
		//   有効な show ペアとみなす。これにより show_X の skip 判定を base の処理順に依存させない。
		//   旧実装は $show_keys_used (base 処理済みフラグ) を skip 条件に使っていたため、
		//   show_X が base より前に並ぶと「base 未処理 → 単独 push」+「base 処理時に再 push」で
		//   同じ show_X が二重描画されるバグがあった (現 fields.php では未発火だが潜在)。
		$valid_show_map = array();
		foreach ( $show_map as $base => $sf ) {
			foreach ( $fields as $f ) {
				if ( $f['key'] === $base ) {
					if ( $f['type'] !== 'repeater' && $f['type'] !== 'wysiwyg' ) {
						$valid_show_map[ $base ] = $sf;
					}
					break;
				}
			}
		}

		$pairs = array();
		foreach ( $fields as $field ) {
			// 自身が show_X 系チェックボックスの場合
			if ( $field['type'] === 'checkbox' && strpos( $field['key'], 'show_' ) === 0 ) {
				$base = substr( $field['key'], 5 );
				// 対応するペアリング可能な base があれば、その base 側で必ずペアにされるので skip
				// (base が show_X より前でも後でも順序非依存に成立)
				if ( isset( $valid_show_map[ $base ] ) ) {
					continue;
				}
				// 対応ベース無し (または base が repeater/wysiwyg) なら単独表示
				$pairs[] = array( $field );
				continue;
			}
			// 自身に対応する show_X があるか (valid_show_map は既にペアリング可能 base のみ)
			if ( isset( $valid_show_map[ $field['key'] ] ) ) {
				$field['group_legend'] = $field['label'];
				$pairs[] = array( $field, $valid_show_map[ $field['key'] ] );
			} else {
				$pairs[] = array( $field );
			}
		}
		return $pairs;
	}

	/**
	 * 単一フィールドブロックの描画 (U1: schema 用語除去、U6: details 個別表示廃止)
	 */
	protected static function render_field_block( $post_id, $field ) {
		$type_class = 'ksppm-field--' . $field['type'];
		// U3: 入力済判定で .ksppm-field--filled クラス付与
		$value = KSPPM_Meta::get( $post_id, $field['key'] );
		$is_filled = false;
		if ( $field['type'] === 'repeater' || $field['type'] === 'blocks' ) {
			$is_filled = is_array( $value ) && count( $value ) > 0;
		} elseif ( $field['type'] === 'checkbox' ) {
			$is_filled = (int) $value === 1;
		} elseif ( $field['type'] === 'image' ) {
			$is_filled = absint( $value ) > 0;
		} else {
			$is_filled = ( $value !== '' && $value !== null );
		}
		$filled_class = $is_filled ? ' ksppm-field--filled' : '';
		?>
		<div class="ksppm-field <?php echo esc_attr( $type_class . $filled_class ); ?>" data-schema-prop="<?php echo esc_attr( $field['schema_property'] ?? '' ); ?>">
			<label for="ksppm_<?php echo esc_attr( $field['key'] ); ?>">
				<?php echo esc_html( $field['label'] ); ?>
				<?php if ( ! empty( $field['sensitive'] ) ) : ?>
					<span class="ksppm-sensitive-icon" title="<?php esc_attr_e( 'プライバシー: 公開設定で制御', 'kashiwazaki-seo-profilepage-maker' ); ?>">🔒</span>
				<?php endif; ?>
				<?php if ( $is_filled ) : ?>
					<span class="ksppm-field-filled-mark" aria-label="<?php esc_attr_e( '入力済', 'kashiwazaki-seo-profilepage-maker' ); ?>">✓</span>
				<?php endif; ?>
			</label>
			<?php self::render_field( $post_id, $field ); ?>
			<?php if ( ! empty( $field['help'] ) ) : ?>
				<p class="description"><?php echo esc_html( $field['help'] ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * U6: タブ末尾に schema.org マッピングを集約表示
	 */
	protected static function render_schema_summary( $fields ) {
		$mappings = array();
		foreach ( $fields as $f ) {
			if ( ! empty( $f['schema_property'] ) ) {
				$mappings[] = array(
					'label' => $f['label'],
					'prop'  => $f['schema_property'],
				);
			}
		}
		if ( empty( $mappings ) ) return;
		?>
		<details class="ksppm-schema-map">
			<summary><?php esc_html_e( 'このタブの構造化データ (schema.org) マッピング', 'kashiwazaki-seo-profilepage-maker' ); ?></summary>
			<table class="ksppm-schema-map-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'フィールド', 'kashiwazaki-seo-profilepage-maker' ); ?></th>
						<th><?php esc_html_e( 'schema.org プロパティ', 'kashiwazaki-seo-profilepage-maker' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $mappings as $m ) : ?>
						<tr>
							<td><?php echo esc_html( $m['label'] ); ?></td>
							<td><code><?php echo esc_html( $m['prop'] ); ?></code></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</details>
		<?php
	}

	protected static function render_field( $post_id, $field ) {
		$key   = $field['key'];
		$value = KSPPM_Meta::get( $post_id, $key );
		$name  = 'ksppm_' . $key;
		$id    = 'ksppm_' . $key;
		switch ( $field['type'] ) {
			case 'textarea':
				printf( '<textarea name="%s" id="%s" rows="3" class="large-text" placeholder="%s">%s</textarea>',
					esc_attr( $name ), esc_attr( $id ),
					esc_attr( $field['placeholder'] ?? '' ),
					esc_textarea( $value ) );
				break;
			case 'wysiwyg':
				wp_editor( $value, $id, array( 'textarea_name' => $name, 'media_buttons' => true, 'textarea_rows' => 6 ) );
				break;
			case 'email':
				printf( '<input type="email" name="%s" id="%s" value="%s" class="regular-text" placeholder="%s">',
					esc_attr( $name ), esc_attr( $id ), esc_attr( $value ),
					esc_attr( $field['placeholder'] ?? 'name@example.com' ) );
				break;
			case 'url':
				printf( '<input type="url" name="%s" id="%s" value="%s" class="regular-text" placeholder="%s">',
					esc_attr( $name ), esc_attr( $id ), esc_attr( $value ),
					esc_attr( $field['placeholder'] ?? 'https://example.com/' ) );
				break;
			case 'date':
				printf( '<input type="date" name="%s" id="%s" value="%s">', esc_attr( $name ), esc_attr( $id ), esc_attr( $value ) );
				break;
			case 'number':
				printf( '<input type="number" step="0.1" name="%s" id="%s" value="%s" placeholder="%s">',
					esc_attr( $name ), esc_attr( $id ), esc_attr( $value ),
					esc_attr( $field['placeholder'] ?? '' ) );
				break;
			case 'checkbox':
				$checked = (int) $value === 1 ? 'checked' : '';
				printf( '<input type="checkbox" name="%s" id="%s" value="1" %s>', esc_attr( $name ), esc_attr( $id ), esc_attr( $checked ) );
				break;
			case 'select':
				$opts = isset( $field['options'] ) ? $field['options'] : array();
				printf( '<select name="%s" id="%s">', esc_attr( $name ), esc_attr( $id ) );
				foreach ( $opts as $k => $label ) {
					printf( '<option value="%s" %s>%s</option>', esc_attr( $k ), selected( $value, $k, false ), esc_html( $label ) );
				}
				echo '</select>';
				break;
			case 'image':
				$img_id  = absint( $value );
				$img_url = $img_id ? wp_get_attachment_image_url( $img_id, 'medium' ) : '';
				$img_meta_text = '';
				if ( $img_id ) {
					$att = get_post( $img_id );
					if ( $att ) $img_meta_text = $att->post_title;
				}
				?>
				<div class="ksppm-image-field" data-name="<?php echo esc_attr( $name ); ?>">
					<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $img_id ); ?>">
					<div class="ksppm-image-preview">
						<?php if ( $img_url ) : ?>
							<img src="<?php echo esc_url( $img_url ); ?>" alt="">
						<?php else : ?>
							<div class="ksppm-image-empty">
								<span><?php esc_html_e( 'クリックして画像を選択', 'kashiwazaki-seo-profilepage-maker' ); ?></span>
							</div>
						<?php endif; ?>
					</div>
					<div class="ksppm-image-meta"><?php echo esc_html( $img_meta_text ); ?></div>
					<div class="ksppm-image-actions">
						<button type="button" class="button ksppm-select-image"><?php esc_html_e( '画像を選択', 'kashiwazaki-seo-profilepage-maker' ); ?></button>
						<button type="button" class="button ksppm-unset-image" <?php echo $img_id ? '' : 'hidden'; ?>><?php esc_html_e( '解除', 'kashiwazaki-seo-profilepage-maker' ); ?></button>
					</div>
				</div>
				<?php
				break;
			case 'blocks':
				// (custom blocks, spec S1/S3): 任意名称ブロックを複数追加。
				// max_input_vars 対策として個別フィールドは POST せず、JS が UI 状態を
				// 単一 hidden input ksppm_custom_blocks_json に JSON.stringify して送信する。
				$blocks_value = is_array( $value ) ? $value : array();
				// 既存画像のサムネ URL を _imgUrl として付与 (JS が初期描画でプレビュー表示、保存時は破棄)
				foreach ( $blocks_value as &$bk ) {
					if ( ! is_array( $bk ) || empty( $bk['items'] ) || ! is_array( $bk['items'] ) ) continue;
					foreach ( $bk['items'] as &$bit ) {
						if ( ! is_array( $bit ) ) continue;
						$iid = isset( $bit['image'] ) ? absint( $bit['image'] ) : 0;
						if ( $iid ) {
							$u = wp_get_attachment_image_url( $iid, 'medium' );
							if ( $u ) $bit['_imgUrl'] = $u;
						}
					}
					unset( $bit );
				}
				unset( $bk );
				$blocks_json  = wp_json_encode( $blocks_value, JSON_UNESCAPED_UNICODE );
				if ( ! is_string( $blocks_json ) ) $blocks_json = '[]';
				$max_blocks   = defined( 'KSPPM_MAX_BLOCKS' ) ? (int) KSPPM_MAX_BLOCKS : 20;
				$max_items    = defined( 'KSPPM_MAX_BLOCK_ITEMS' ) ? (int) KSPPM_MAX_BLOCK_ITEMS : 50;
				?>
				<div class="ksppm-blocks" data-name="<?php echo esc_attr( $name ); ?>" data-max-blocks="<?php echo esc_attr( $max_blocks ); ?>" data-max-items="<?php echo esc_attr( $max_items ); ?>">
					<input type="hidden" name="ksppm_custom_blocks_json" class="ksppm-blocks-json" value="<?php echo esc_attr( $blocks_json ); ?>">
					<div class="ksppm-blocks-list" aria-live="polite"></div>
					<button type="button" class="button ksppm-blocks-add">+ <?php esc_html_e( 'ブロックを追加', 'kashiwazaki-seo-profilepage-maker' ); ?></button>
					<p class="description"><?php echo esc_html( sprintf(
						/* translators: 1: max blocks, 2: max items */
						__( 'ブロックは最大 %1$d 個、各ブロックの項目は最大 %2$d 個まで登録できます。', 'kashiwazaki-seo-profilepage-maker' ),
						$max_blocks,
						$max_items
					) ); ?></p>
					<noscript><p class="description"><?php esc_html_e( 'カスタムブロックの編集には JavaScript が必要です。', 'kashiwazaki-seo-profilepage-maker' ); ?></p></noscript>
				</div>
				<?php
				break;
			case 'repeater':
				$rows = is_array( $value ) ? $value : array();
				$item_fields = isset( $field['item_fields'] ) ? $field['item_fields'] : array();
				$is_empty = empty( $rows );
				// U4: 空リピーターは大ボックス廃止、1 行プレースホルダーのみ
				?>
				<div class="ksppm-repeater<?php echo $is_empty ? ' ksppm-repeater--empty' : ''; ?>" data-name="<?php echo esc_attr( $name ); ?>">
					<div class="ksppm-repeater-rows">
						<?php foreach ( $rows as $i => $row ) : ?>
							<?php self::render_repeater_row( $name, $i, $row, $item_fields ); ?>
						<?php endforeach; ?>
					</div>
					<button type="button" class="button button-link ksppm-repeater-add">+ <?php
						/* translators: %s: フィールドラベル (例: 経歴) */
						printf( esc_html__( '%s を追加', 'kashiwazaki-seo-profilepage-maker' ), esc_html( $field['label'] ?? '行' ) );
					?></button>
					<template class="ksppm-repeater-template">
						<?php self::render_repeater_row( $name, '__INDEX__', array(), $item_fields ); ?>
					</template>
				</div>
				<?php
				break;
			case 'text':
			default:
				printf( '<input type="text" name="%s" id="%s" value="%s" class="regular-text" placeholder="%s">',
					esc_attr( $name ), esc_attr( $id ), esc_attr( $value ),
					esc_attr( $field['placeholder'] ?? '' ) );
		}
	}

	/**
	 * Repeater 行描画 (T5: details/summary で折り畳み、行サマリー)
	 */
	protected static function render_repeater_row( $base, $index, $row, $item_fields ) {
		$is_template = ( $index === '__INDEX__' );
		// サマリー候補値 (先頭の text 値)
		$summary_text = '';
		if ( ! $is_template ) {
			foreach ( $item_fields as $sub ) {
				if ( in_array( $sub['type'], array( 'text', 'email', 'url', 'textarea' ), true ) ) {
					$v = $row[ $sub['key'] ] ?? '';
					if ( $v !== '' ) {
						// F08: mbstring 拡張不在環境への fallback (Requires PHP 8.0 のみ、ext-mbstring 未要件)
						if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
							$summary_text = mb_strlen( $v ) > 60 ? mb_substr( $v, 0, 60 ) . '…' : $v;
						} else {
							$summary_text = strlen( $v ) > 60 ? substr( $v, 0, 60 ) . '…' : $v;
						}
						break;
					}
				}
			}
		}
		// 開閉状態: 新規空行は開く、既存値ありは閉じる
		$open_attr = ( $is_template || $summary_text === '' ) ? ' open' : '';
		// 複数列レイアウト判定
		$multicol = count( $item_fields ) > 3 ? ' ksppm-row-body--multicol' : '';
		?>
		<div class="ksppm-repeater-row" data-index="<?php echo esc_attr( $index ); ?>">
			<details<?php echo $open_attr; ?>>
				<summary>
					<span class="ksppm-row-handle" title="<?php esc_attr_e( 'ドラッグで並べ替え', 'kashiwazaki-seo-profilepage-maker' ); ?>">⋮⋮</span>
					<span class="ksppm-row-num">#<?php echo esc_html( is_int( $index ) ? $index + 1 : '' ); ?></span>
					<span class="ksppm-row-summary"><?php echo esc_html( $summary_text ); ?></span>
					<button type="button" class="ksppm-repeater-remove" title="<?php esc_attr_e( '削除', 'kashiwazaki-seo-profilepage-maker' ); ?>"><?php esc_html_e( '削除', 'kashiwazaki-seo-profilepage-maker' ); ?></button>
				</summary>
				<div class="ksppm-row-body<?php echo esc_attr( $multicol ); ?>">
					<?php foreach ( $item_fields as $sub ) :
						$sub_name = $base . '[' . $index . '][' . $sub['key'] . ']';
						$sub_val  = isset( $row[ $sub['key'] ] ) ? $row[ $sub['key'] ] : '';
						?>
						<label class="ksppm-sub-field ksppm-sub-<?php echo esc_attr( $sub['type'] ); ?>">
							<span><?php echo esc_html( $sub['label'] ); ?></span>
							<?php if ( $sub['type'] === 'textarea' ) : ?>
								<textarea name="<?php echo esc_attr( $sub_name ); ?>" rows="2"><?php echo esc_textarea( $sub_val ); ?></textarea>
							<?php elseif ( $sub['type'] === 'url' ) : ?>
								<input type="url" name="<?php echo esc_attr( $sub_name ); ?>" value="<?php echo esc_attr( $sub_val ); ?>" placeholder="https://...">
							<?php elseif ( $sub['type'] === 'email' ) : ?>
								<input type="email" name="<?php echo esc_attr( $sub_name ); ?>" value="<?php echo esc_attr( $sub_val ); ?>">
							<?php elseif ( $sub['type'] === 'date' ) : ?>
								<input type="date" name="<?php echo esc_attr( $sub_name ); ?>" value="<?php echo esc_attr( $sub_val ); ?>">
							<?php elseif ( $sub['type'] === 'number' ) : ?>
								<input type="number" step="0.1" name="<?php echo esc_attr( $sub_name ); ?>" value="<?php echo esc_attr( $sub_val ); ?>">
							<?php elseif ( $sub['type'] === 'image' ) : ?>
								<?php $sub_img = absint( $sub_val ); $sub_img_url = $sub_img ? wp_get_attachment_image_url( $sub_img, 'thumbnail' ) : ''; ?>
								<div class="ksppm-image-field" data-name="<?php echo esc_attr( $sub_name ); ?>">
									<input type="hidden" name="<?php echo esc_attr( $sub_name ); ?>" value="<?php echo esc_attr( $sub_img ); ?>">
									<div class="ksppm-image-preview">
										<?php if ( $sub_img_url ) : ?>
											<img src="<?php echo esc_url( $sub_img_url ); ?>" alt="">
										<?php else : ?>
											<div class="ksppm-image-empty"><span><?php esc_html_e( '画像を選択', 'kashiwazaki-seo-profilepage-maker' ); ?></span></div>
										<?php endif; ?>
									</div>
									<div class="ksppm-image-actions">
										<button type="button" class="button button-small ksppm-select-image"><?php esc_html_e( '選択', 'kashiwazaki-seo-profilepage-maker' ); ?></button>
										<button type="button" class="button button-small ksppm-unset-image" <?php echo $sub_img ? '' : 'hidden'; ?>><?php esc_html_e( '解除', 'kashiwazaki-seo-profilepage-maker' ); ?></button>
									</div>
								</div>
							<?php else : ?>
								<input type="text" name="<?php echo esc_attr( $sub_name ); ?>" value="<?php echo esc_attr( $sub_val ); ?>">
							<?php endif; ?>
						</label>
					<?php endforeach; ?>
				</div>
			</details>
		</div>
		<?php
	}

	public static function render_jsonld_preview( $post ) {
		if ( ! $post || $post->post_status === 'auto-draft' ) {
			echo '<p><em>' . esc_html__( '保存後に表示されます。', 'kashiwazaki-seo-profilepage-maker' ) . '</em></p>';
			return;
		}
		$json = KSPPM_Schema::get_jsonld( $post->ID );
		$pretty = $json ? wp_json_encode( $json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) : '';
		?>
		<details>
			<summary><?php esc_html_e( 'ProfilePage + Person を表示', 'kashiwazaki-seo-profilepage-maker' ); ?></summary>
			<textarea readonly class="ksppm-jsonld-preview" rows="14"><?php echo esc_textarea( $pretty ); ?></textarea>
		</details>
		<p>
			<a href="https://search.google.com/test/rich-results?url=<?php echo esc_attr( urlencode( get_permalink( $post->ID ) ) ); ?>" target="_blank" rel="noopener" class="button button-small">Rich Results Test</a>
			<a href="https://validator.schema.org/#url=<?php echo esc_attr( urlencode( get_permalink( $post->ID ) ) ); ?>" target="_blank" rel="noopener" class="button button-small">Schema.org Validator</a>
		</p>
		<?php
	}

	public static function render_completeness( $post ) {
		if ( ! $post || $post->post_status === 'auto-draft' ) {
			echo '<p><em>' . esc_html__( '保存後に表示されます。', 'kashiwazaki-seo-profilepage-maker' ) . '</em></p>';
			return;
		}
		$data = KSPPM_Meta::get_all( $post->ID );
		$score = self::calc_eeat_score( $data );
		?>
		<div class="ksppm-eeat-score">
			<div><strong>Experience:</strong> <progress max="100" value="<?php echo esc_attr( $score['experience'] ); ?>"></progress> <?php echo esc_html( $score['experience'] ); ?>%</div>
			<div><strong>Expertise:</strong> <progress max="100" value="<?php echo esc_attr( $score['expertise'] ); ?>"></progress> <?php echo esc_html( $score['expertise'] ); ?>%</div>
			<div><strong>Authoritativeness:</strong> <progress max="100" value="<?php echo esc_attr( $score['authoritativeness'] ); ?>"></progress> <?php echo esc_html( $score['authoritativeness'] ); ?>%</div>
			<div><strong>Trustworthiness:</strong> <progress max="100" value="<?php echo esc_attr( $score['trustworthiness'] ); ?>"></progress> <?php echo esc_html( $score['trustworthiness'] ); ?>%</div>
			<hr>
			<div><strong>総合:</strong> <progress max="100" value="<?php echo esc_attr( $score['total'] ); ?>"></progress> <?php echo esc_html( $score['total'] ); ?>%</div>
		</div>

		<?php
		// U15: 公開前チェックリスト
		$missing = self::missing_items( $post, $data );
		?>
		<div class="ksppm-checklist <?php echo empty( $missing ) ? 'ksppm-checklist--complete' : ''; ?>">
			<div class="ksppm-checklist__title"><?php esc_html_e( '次の改善ポイント', 'kashiwazaki-seo-profilepage-maker' ); ?></div>
			<?php if ( empty( $missing ) ) : ?>
				<p class="ksppm-checklist__complete-msg">✓ <?php esc_html_e( '主要項目はすべて入力済みです', 'kashiwazaki-seo-profilepage-maker' ); ?></p>
			<?php else : ?>
				<ul class="ksppm-checklist__items">
					<?php foreach ( $missing as $item ) : ?>
						<li>
							<a href="<?php echo esc_url( $item['link'] ); ?>"><?php echo esc_html( $item['label'] ); ?></a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * U15: 改善が必要な項目を返す (各項目に該当タブへのリンク付き)
	 */
	public static function missing_items( $post, $data ) {
		$items = array();
		$thumb_id = get_post_thumbnail_id( $post->ID );
		// アイキャッチ画像
		if ( ! $thumb_id ) {
			$items[] = array(
				'label' => __( 'アイキャッチ画像を追加 (顔写真推奨)', 'kashiwazaki-seo-profilepage-maker' ),
				'link'  => '#ksppm-thumbnail-section',
			);
		}
		// 自己紹介
		if ( empty( $data['description'] ) ) {
			$items[] = array(
				'label' => __( '自己紹介 (短文) を入力', 'kashiwazaki-seo-profilepage-maker' ),
				'link'  => '#ksppm-tab-basic',
			);
		}
		// 現職
		if ( empty( $data['job_title'] ) ) {
			$items[] = array(
				'label' => __( '現職 (肩書) を入力', 'kashiwazaki-seo-profilepage-maker' ),
				'link'  => '#ksppm-tab-basic',
			);
		}
		// SNS
		if ( empty( $data['same_as'] ) ) {
			$items[] = array(
				'label' => __( 'SNS / 外部プロフィール URL を追加', 'kashiwazaki-seo-profilepage-maker' ),
				'link'  => '#ksppm-tab-contact',
			);
		}
		// 経歴
		if ( empty( $data['works_for'] ) && empty( $data['past_career'] ) ) {
			$items[] = array(
				'label' => __( '現職または以前の経歴を追加', 'kashiwazaki-seo-profilepage-maker' ),
				'link'  => '#ksppm-tab-career',
			);
		}
		// 専門性
		if ( empty( $data['knows_about'] ) && empty( $data['skills'] ) ) {
			$items[] = array(
				'label' => __( '専門分野またはスキルを追加', 'kashiwazaki-seo-profilepage-maker' ),
				'link'  => '#ksppm-tab-expertise',
			);
		}
		// 著作
		if ( empty( $data['books'] ) && empty( $data['articles'] ) && empty( $data['media_mentions'] ) ) {
			$items[] = array(
				'label' => __( '著作 / 論文 / メディア掲載のいずれかを追加', 'kashiwazaki-seo-profilepage-maker' ),
				'link'  => '#ksppm-tab-works',
			);
		}
		return $items;
	}

	public static function calc_eeat_score( $data ) {
		$exp = 0;
		if ( ! empty( $data['works_for'] ) ) $exp += 30;
		if ( ! empty( $data['past_career'] ) ) $exp += 20;
		if ( ! empty( $data['speeches'] ) ) $exp += 20;
		if ( ! empty( $data['biography'] ) ) $exp += 30;
		$expt = 0;
		if ( ! empty( $data['knows_about'] ) ) $expt += 30;
		if ( ! empty( $data['skills'] ) ) $expt += 20;
		if ( ! empty( $data['credentials'] ) ) $expt += 25;
		if ( ! empty( $data['alumni_of'] ) ) $expt += 25;
		$auth = 0;
		if ( ! empty( $data['same_as'] ) ) $auth += min( 25, count( $data['same_as'] ) * 5 );
		if ( ! empty( $data['awards'] ) ) $auth += 20;
		if ( ! empty( $data['books'] ) ) $auth += 15;
		if ( ! empty( $data['articles'] ) ) $auth += 15;
		if ( ! empty( $data['patents'] ) ) $auth += 10;
		if ( ! empty( $data['media_mentions'] ) ) $auth += 15;
		$trust = 0;
		// F16: 「Trustworthiness (信頼性)」スコアは公開情報の信頼性指標であり、
		// 保存だけして非公開 (show_email/show_telephone=false) のフィールドはフロントに何も出ないため、
		// 公開チャネルとしては trust 信号にならない。show_* フラグ連動で算定する。
		$show_email     = ! empty( $data['show_email'] );
		$show_telephone = ! empty( $data['show_telephone'] );
		$has_public_contact = ( ! empty( $data['email'] ) && $show_email )
			|| ( ! empty( $data['telephone'] ) && $show_telephone );
		if ( $has_public_contact ) $trust += 25;
		if ( ! empty( $data['main_image'] ) ) $trust += 25;
		if ( ! empty( $data['url'] ) ) $trust += 15;
		if ( ! empty( $data['description'] ) ) $trust += 15;
		if ( ! empty( $data['contact_text'] ) || ! empty( $data['contact_form_url'] ) ) $trust += 20;

		$exp   = min( 100, $exp );
		$expt  = min( 100, $expt );
		$auth  = min( 100, $auth );
		$trust = min( 100, $trust );
		$total = (int) round( ( $exp + $expt + $auth + $trust ) / 4 );
		return array(
			'experience'        => $exp,
			'expertise'         => $expt,
			'authoritativeness' => $auth,
			'trustworthiness'   => $trust,
			'total'             => $total,
		);
	}
}
