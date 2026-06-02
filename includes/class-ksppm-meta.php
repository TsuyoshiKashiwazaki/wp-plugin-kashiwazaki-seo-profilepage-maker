<?php
/**
 * post_meta 保存・取得 (DES-05)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KSPPM_Meta {

	/**
	 * F03: $_POST mutation 廃止に伴う form data 復元用ストア。
	 * render_form 側で `set_restored_data()` を呼び、get_restored_value() で参照する。
	 * 1 リクエスト内のみ有効 (static)。$_POST を汚染しないため、後続の他プラグイン/コア処理が
	 * sanitize 済データを生 $_POST として誤って読むリスクを排除する。
	 *
	 * @var array<string,mixed>|null
	 */
	protected static $restored_form_data = null;

	public static function init() {
		// T9 + (v0.0.13-beta): save_post hook は KSPPM_Settings: handle_save と
		// nonce action 文字列が不一致で dead code 化していた (二重保存 + nonce 不一致)。
		// handle_save に一本化することで sanitize ロジックの二重実装解消 + nonce 統一を同時達成。
		// save() メソッド本体は将来の再活用 (REST 経由保存等) のため残置。
		// add_action( 'save_post_' . KSPPM_CPT, array( __CLASS__, 'save' ), 10, 2 ); // disabled, see T9/T16
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_meta' ) );
	}

	/**
	 * F03: render_form 側から transient で復元したフォームデータを注入する API。
	 * $_POST を直接書き換える従来方式の代替。
	 *
	 * @param array<string,mixed>|null $data sanitize 済みフォームデータ、null でクリア
	 */
	public static function set_restored_data( $data ) {
		self::$restored_form_data = is_array( $data ) ? $data : null;
	}

	/**
	 * F03: 注入済みフォームデータの取得 (主にテスト/デバッグ用)
	 *
	 * @return array<string,mixed>|null
	 */
	public static function get_restored_data() {
		return self::$restored_form_data;
	}

	/**
	 * 1 投稿の全フィールド値を取得 (フラット連想配列)
	 */
	public static function get_all( $post_id ) {
		$out = array();
		foreach ( KSPPM_Fields::get_all_fields() as $key => $field ) {
			$meta_key = KSPPM_META_PREFIX . $key;
			if ( $field['type'] === 'blocks' ) {
				// (custom blocks) 未移行投稿は旧 free_tools/partner_tools をメモリ内 fallback 合成。
				// get_all はフロント/管理両方から呼ばれるため persist=false (フロント GET で DB 書込しない)。
				$out[ $key ] = self::resolve_blocks( $post_id, false );
				continue;
			}
			$raw = get_post_meta( $post_id, $meta_key, true );
			if ( in_array( $field['type'], array( 'repeater' ), true ) ) {
				$out[ $key ] = self::decode_repeater( $raw );
			} else {
				$out[ $key ] = $raw === '' ? KSPPM_Fields::get_default( $field ) : $raw;
			}
		}
		return $out;
	}

	/**
	 * (custom blocks) 投稿のカスタムブロック配列を解決する。
	 * 移行済 (_ksppm_blocks_migrated) なら _ksppm_custom_blocks(JSON) を正として返す。
	 * 未移行なら旧 _ksppm_free_tools / _ksppm_partner_tools からブロックを合成する。
	 * $persist=true (管理 context) のときのみ合成結果を永続化 + 移行フラグを立てる。
	 * $persist=false (フロント GET) のときは DB 書込せずメモリ内 fallback のみ (WP アンチパターン回避、spec E2)。
	 *
	 * @return array ブロック配列 [{id,title,items:[{heading,description,url,image}]}]
	 */
	public static function resolve_blocks( $post_id, $persist = false ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) return array();
		$migrated = (int) get_post_meta( $post_id, KSPPM_META_PREFIX . 'blocks_migrated', true );
		if ( $migrated ) {
			return self::decode_repeater( get_post_meta( $post_id, KSPPM_META_PREFIX . 'custom_blocks', true ) );
		}
		$blocks = self::migrate_legacy_blocks( $post_id );
		if ( $persist ) {
			update_post_meta( $post_id, KSPPM_META_PREFIX . 'custom_blocks', wp_slash( wp_json_encode( $blocks, JSON_UNESCAPED_UNICODE ) ) );
			update_post_meta( $post_id, KSPPM_META_PREFIX . 'blocks_migrated', 1 );
		}
		return $blocks;
	}

	/**
	 * (custom blocks) 旧 free_tools / partner_tools をブロック配列に変換 (移行ロジック、書込なし)。
	 * 旧 anchor id 後方互換: free_tools → id 'ksppm-tools'、partner_tools → id 'ksppm-partner-tools'。
	 * partner_tools の role は意味変化を許容して description にマップ (spec D8)。
	 */
	protected static function migrate_legacy_blocks( $post_id ) {
		$blocks = array();
		$free = self::decode_repeater( get_post_meta( $post_id, KSPPM_META_PREFIX . 'free_tools', true ) );
		if ( ! empty( $free ) ) {
			$items = array();
			foreach ( $free as $r ) {
				if ( ! is_array( $r ) ) continue;
				$items[] = array(
					'heading'     => isset( $r['name'] ) ? (string) $r['name'] : '',
					'description' => isset( $r['description'] ) ? (string) $r['description'] : '',
					'url'         => isset( $r['url'] ) ? (string) $r['url'] : '',
					'image'       => 0,
				);
			}
			if ( $items ) {
				$blocks[] = array( 'id' => 'ksppm-tools', 'title' => __( '公開中の無料システムツール', 'kashiwazaki-seo-profilepage-maker' ), 'items' => $items );
			}
		}
		$partner = self::decode_repeater( get_post_meta( $post_id, KSPPM_META_PREFIX . 'partner_tools', true ) );
		if ( ! empty( $partner ) ) {
			$items = array();
			foreach ( $partner as $r ) {
				if ( ! is_array( $r ) ) continue;
				$items[] = array(
					'heading'     => isset( $r['name'] ) ? (string) $r['name'] : '',
					'description' => isset( $r['role'] ) ? (string) $r['role'] : '', // role→説明 (移行時の意味変化を許容)
					'url'         => isset( $r['url'] ) ? (string) $r['url'] : '',
					'image'       => 0,
				);
			}
			if ( $items ) {
				$blocks[] = array( 'id' => 'ksppm-partner-tools', 'title' => __( '監修・共同開発システムツール', 'kashiwazaki-seo-profilepage-maker' ), 'items' => $items );
			}
		}
		return $blocks;
	}

	public static function get( $post_id, $key, $default = null ) {
		$fields = KSPPM_Fields::get_all_fields();
		if ( ! isset( $fields[ $key ] ) ) {
			return $default;
		}
		$field = $fields[ $key ];

		// (custom blocks, spec E3a): blocks は POST key が ksppm_custom_blocks_json (特殊)。
		// 保存失敗時の transient 復元はこの key から行い、通常時は resolve_blocks で解決する。
		if ( $field['type'] === 'blocks' ) {
			if ( apply_filters( 'ksppm_use_form_data_restore', true )
				&& is_array( self::$restored_form_data )
				&& isset( self::$restored_form_data['ksppm_custom_blocks_json'] )
				&& isset( $_GET['msg'] ) ) {
				$msg_value = sanitize_key( wp_unslash( $_GET['msg'] ) );
				if ( in_array( $msg_value, array( 'title_empty', 'save_failed' ), true ) ) {
					$rj  = self::$restored_form_data['ksppm_custom_blocks_json'];
					$dec = is_string( $rj ) ? json_decode( $rj, true ) : null;
					if ( is_array( $dec ) ) {
						return $dec;
					}
				}
			}
			return self::resolve_blocks( $post_id, false );
		}

		// T4 復元: 保存失敗後の transient フォームデータがあれば優先して返す
		// (handle_save で全 $_POST を transient に保存、render_form で復元中の場合に発火)
		if ( apply_filters( 'ksppm_use_form_data_restore', true ) ) {
			$restored = self::get_restored_value( $key, $field );
			if ( $restored !== null ) {
				return $restored;
			}
		}

		$raw = get_post_meta( $post_id, KSPPM_META_PREFIX . $key, true );
		if ( $field['type'] === 'repeater' ) {
			return self::decode_repeater( $raw );
		}
		return $raw === '' ? ( $default === null ? KSPPM_Fields::get_default( $field ) : $default ) : $raw;
	}

	/**
	 * T4 / F03: 復元データの取得。
	 * F03 で $_POST mutation を廃止し、render_form 側が set_restored_data() で渡した
	 * 専用 store (self::$restored_form_data) を参照する。
	 * 失敗時の入力データ消失を防ぐ目的は変えず、$_POST 汚染を排除する。
	 */
	protected static function get_restored_value( $key, $field ) {
		$input_key = 'ksppm_' . $key;
		// F03: render_form で set_restored_data() 済みの場合のみ動作。
		// msg=title_empty / save_failed redirect 後の render_form 経路で発火。
		if ( ! is_array( self::$restored_form_data ) ) return null;
		if ( ! isset( self::$restored_form_data[ $input_key ] ) ) return null;
		// $_GET['msg'] は二重チェック (form 復元の意図的な path であることを確認)
		if ( ! isset( $_GET['msg'] ) ) return null;
		$msg_value = sanitize_key( wp_unslash( $_GET['msg'] ) );
		if ( ! in_array( $msg_value, array( 'title_empty', 'save_failed' ), true ) ) return null;

		$raw = self::$restored_form_data[ $input_key ];
		// type 別に簡易 sanitize して返す (フィールド描画用、最終 sanitize は handle_save 側で実施)。
		// 復元 store は render_form 側で sanitize_text_field 経由済みのため、文字列は wp_unslash 不要
		// (mutation 廃止に伴い slash 状態が render_form で吸収済み)。互換のため repeater は配列キャストのみ。
		switch ( $field['type'] ) {
			case 'repeater':
				return is_array( $raw ) ? $raw : array();
			case 'checkbox':
				return $raw ? 1 : 0;
			case 'number':
				return is_numeric( $raw ) ? floatval( $raw ) : '';
			case 'image':
				return absint( $raw );
			case 'textarea':
			case 'wysiwyg':
				return is_string( $raw ) ? $raw : '';
			default:
				return is_string( $raw ) ? $raw : '';
		}
	}

	public static function decode_repeater( $raw ) {
		if ( is_array( $raw ) ) return $raw;
		if ( ! is_string( $raw ) || $raw === '' ) return array();
		// get_post_meta は既に unslash された値を返すため、ここでさらに wp_unslash は不要
		// (JSON 内の \" \/ \\ が壊れて parse error になる)
		$decoded = json_decode( $raw, true );
		// fallback: 保存方式が混在する古いデータで slash が残っている場合
		if ( ! is_array( $decoded ) ) {
			$decoded = json_decode( wp_unslash( $raw ), true );
		}
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * (v0.0.18-beta): save() と sanitize_repeater() の dead code を削除。
	 * 旧 save_post hook 経路は v5.34 時代の v0.0.13-beta で nonce action 不一致で常時 return する dead code 化されていた。
	 * sanitize_repeater も KSPPM_Settings 側に同一実装が存在し、KSPPM_Settings: handle_save が single source of truth として機能。
	 * save() を完全削除することで:
	 *   - dead code 排除 (保守性向上)
	 *   - sanitize_repeater 二重実装解消 (T7 と統合)
	 * 旧 save() の sanitize 仕様は KSPPM_Settings: handle_save に移植済。
	 */

	public static function register_rest_meta() {
		foreach ( KSPPM_Fields::get_all_fields() as $key => $field ) {
			// (custom blocks, spec E3b): blocks は REST 経由更新を受けず、管理画面の単一 JSON
			// フィールド (ksppm_custom_blocks_json) 経由でのみ保存する。show_in_rest 露出と
			// REST sanitize の default 分岐による誤処理を避けるため register_post_meta から除外。
			if ( $field['type'] === 'blocks' ) {
				continue;
			}
			// F20: field type → REST API type マッピング (string/boolean/number/array)
			$rest_type = self::map_rest_type( $field['type'] );
			$field_type   = $field['type'];
			$item_fields  = isset( $field['item_fields'] ) ? $field['item_fields'] : array(); // F11
			// sensitive=true のフィールド (PII: birth_date / gender / height / weight / email / telephone)
			// は REST 経由で未認証ユーザーに露出させない。show_in_rest=false で完全除外。
			// 管理画面側は meta_key 直接アクセス (get_post_meta) で読むため影響なし。
			$is_sensitive = ! empty( $field['sensitive'] );
			register_post_meta(
				KSPPM_CPT,
				KSPPM_META_PREFIX . $key,
				array(
					'show_in_rest'  => $is_sensitive ? false : true,
					'single'        => true,
					'type'          => $rest_type,
					// F3: auth_callback は対象投稿を編集できるかで判定 (権限過大化を防ぐ)
					'auth_callback' => function ( $allowed, $meta_key, $object_id ) {
						return current_user_can( 'edit_post', $object_id );
					},
					// REST 経由更新時の type 別 sanitize (handle_save と整合)
					// F11: repeater は item_fields を渡して sub-field 別 sanitize (admin 経路と整合)
					'sanitize_callback' => function ( $value ) use ( $field_type, $item_fields ) {
						return self::sanitize_rest_value( $value, $field_type, $item_fields );
					},
				)
			);
		}
	}

	/**
	 * REST API 経由 meta 更新時の type 別 sanitize
	 * (handle_save の case 文と整合する最小実装)
	 */
	public static function sanitize_rest_value( $value, $field_type, $item_fields = array() ) {
		switch ( $field_type ) {
			case 'email':
				return sanitize_email( $value );
			case 'url':
				return esc_url_raw( $value );
			case 'number':
				return is_numeric( $value ) ? floatval( $value ) : '';
			case 'checkbox':
				return $value ? 1 : 0;
			case 'image':
				return self::sanitize_image_id( $value ); // F2: 画像添付検証
			case 'textarea':
				return sanitize_textarea_field( $value );
			case 'wysiwyg':
				return wp_kses_post( $value );
			case 'date':
				if ( is_string( $value ) && preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
					return $value;
				}
				return '';
			case 'repeater':
				// F11: JSON エンコード済 string を decode し、item_fields に従って sub-field 別 sanitize
				//   してから re-encode する (admin 経路 sanitize_repeater と整合)。
				//   従来は decode せず raw 文字列を素通ししていたため、admin/REST で sanitize 不整合だった。
				return self::sanitize_repeater_json( $value, $item_fields );
			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * F11: repeater (JSON 文字列) を sub-field 別 sanitize して JSON へ戻す。
	 * KSPPM_Settings::sanitize_repeater (admin 経路) と同一の type 別ルールを REST 経路にも適用。
	 * item_fields が空の場合は decode 可能性のみ確認して安全な JSON を返す。
	 *
	 * @param mixed $value       JSON 文字列 (または配列)
	 * @param array $item_fields repeater の sub-field 定義
	 * @return string JSON 文字列
	 */
	protected static function sanitize_repeater_json( $value, $item_fields ) {
		if ( is_string( $value ) ) {
			$rows = json_decode( $value, true );
			if ( ! is_array( $rows ) ) {
				$rows = json_decode( wp_unslash( $value ), true );
			}
		} elseif ( is_array( $value ) ) {
			$rows = $value;
		} else {
			$rows = array();
		}
		if ( ! is_array( $rows ) ) {
			return wp_json_encode( array(), JSON_UNESCAPED_UNICODE );
		}
		$out = array();
		foreach ( $rows as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$row = array();
			foreach ( $item_fields as $sub ) {
				$k   = $sub['key'];
				$val = isset( $item[ $k ] ) ? $item[ $k ] : '';
				// F8: REST 経由でも配列 sub 値で sanitize 系に配列が渡る PHP8 TypeError を防ぐ。
				if ( ! is_scalar( $val ) ) { $row[ $k ] = ( $sub['type'] === 'image' ) ? 0 : ''; continue; }
				switch ( $sub['type'] ) {
					case 'email':    $row[ $k ] = sanitize_email( $val ); break;
					case 'url':      $row[ $k ] = esc_url_raw( $val ); break;
					case 'date':
						$row[ $k ] = ( is_string( $val ) && preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $val, $mm ) && checkdate( (int) $mm[2], (int) $mm[3], (int) $mm[1] ) ) ? $val : '';
						break;
					case 'number':   $row[ $k ] = is_numeric( $val ) ? floatval( $val ) : ''; break;
					case 'textarea': $row[ $k ] = sanitize_textarea_field( $val ); break;
					case 'image':    $row[ $k ] = self::sanitize_image_id( $val ); break; // F2: 画像添付検証
					default:         $row[ $k ] = sanitize_text_field( $val );
				}
			}
			if ( empty( $item_fields ) ) {
				// 定義が無い場合は値ごとに最小 sanitize (型情報なし)
				foreach ( $item as $k => $val ) {
					$row[ $k ] = is_string( $val ) ? sanitize_text_field( $val ) : $val;
				}
			}
			// F11: admin 経路 (KSPPM_Settings::sanitize_repeater) と整合させ、全項目が空の行は捨てる。
			//   これにより REST 経由と admin 経由で同一の repeater 正規化結果になる。
			$has_value = false;
			foreach ( $row as $v ) {
				if ( ! ( $v === '' || $v === 0 || $v === 0.0 || $v === '0' || $v === null ) ) {
					$has_value = true;
					break;
				}
			}
			if ( $has_value ) {
				$out[] = $row;
			}
		}
		return wp_json_encode( $out, JSON_UNESCAPED_UNICODE );
	}

	/**
	 * F20: field type を register_post_meta の REST type にマッピング
	 */
	/**
	 * (F2) image 型添付 ID のサニタイズ。absint 後、実在する画像添付 (wp_attachment_is_image) の
	 * 場合のみ ID を保持し、それ以外 (存在しない / 非画像 / 他者の任意 attachment) は 0 に倒す。
	 * IDOR (任意 attachment ID を schema/front に露出) を防ぐ。
	 */
	public static function sanitize_image_id( $raw ) {
		$id = absint( $raw );
		if ( ! $id || ! wp_attachment_is_image( $id ) ) {
			return 0;
		}
		// (D1) IDOR 防御: ログインユーザー経由の保存では、現ユーザーが編集権を持つ添付のみ許可し、
		//   任意 attachment ID (他者の非公開画像等) を front/JSON-LD へ URL 露出するのを防ぐ。
		//   プログラム的更新 (wp-cli / deploy / 移行スクリプト等で current user 不在) は信頼コンテキスト
		//   として検証をスキップ (current_user_can は user 不在時 false を返し画像を 0 化する回帰を避ける)。
		if ( get_current_user_id() && ! current_user_can( 'edit_post', $id ) ) {
			return 0;
		}
		return $id;
	}

	protected static function map_rest_type( $field_type ) {
		switch ( $field_type ) {
			case 'checkbox':
				return 'boolean';
			case 'number':
				return 'number';
			case 'image':
				return 'integer';
			case 'repeater':
				// JSON serialized string なので REST 上は string (object 内部構造は別途要)
				return 'string';
			default:
				return 'string';
		}
	}
}
