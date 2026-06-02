<?php
/**
 * JSON-LD 出力: ProfilePage + Person (STR-01 〜 STR-23)
 * DES-10 transient キャッシュ、DES-13 フィルタフック
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KSPPM_Schema {

	public static function init() {
		// option 未保存時のデフォルトを適用 (F1 修正の副作用: 空 option で全機能 off になる問題への対処)
		$defaults = array(
			'enable_jsonld'     => 1,
			'enable_breadcrumb' => 1,
			'enable_speakable'  => 1,
			'jsonld_position'   => 'head',
		);
		$settings = wp_parse_args( get_option( KSPPM_OPTION_KEY, array() ), $defaults );
		// F1: enable_jsonld トグル尊重 + jsonld_position で wp_head / wp_footer 切替
		if ( ! empty( $settings['enable_jsonld'] ) ) {
			$hook = ( isset( $settings['jsonld_position'] ) && $settings['jsonld_position'] === 'footer' ) ? 'wp_footer' : 'wp_head';
			add_action( $hook, array( __CLASS__, 'render_jsonld' ), 5 );
		}
		add_action( 'save_post_' . KSPPM_CPT, array( __CLASS__, 'flush_cache' ), 20 );
		// F15: attachment 単独更新時もキャッシュ無効化
		add_action( 'attachment_updated', array( __CLASS__, 'flush_all_cache' ) );
		add_action( 'delete_attachment', array( __CLASS__, 'flush_all_cache' ) );
		// (v0.0.13-beta): REST API 経由の meta 更新でも JSON-LD cache を flush。
		// 背景: 管理画面 form save は save_post_KSPPM_CPT で flush されるが、Gutenberg/REST 経由は同 hook が発火しない。
		// updated_post_meta で KSPPM_META_PREFIX prefix の meta_key 変更時に該当 post の cache を flush。
		add_action( 'updated_post_meta', array( __CLASS__, 'maybe_flush_on_meta_update' ), 10, 4 );
		add_action( 'added_post_meta', array( __CLASS__, 'maybe_flush_on_meta_update' ), 10, 4 );
		add_action( 'deleted_post_meta', array( __CLASS__, 'maybe_flush_on_meta_update' ), 10, 4 );
	}

	/**
	 * T6: REST API 経由の meta 更新時の JSON-LD cache flush trigger
	 * KSPPM_META_PREFIX prefix の meta_key かつ post_type が KSPPM_CPT なら該当 post の cache を flush
	 */
	public static function maybe_flush_on_meta_update( $meta_id, $post_id, $meta_key, $meta_value ) {
		if ( strpos( $meta_key, KSPPM_META_PREFIX ) !== 0 ) return;
		$post = get_post( $post_id );
		if ( ! $post || $post->post_type !== KSPPM_CPT ) return;
		self::flush_cache( $post_id );
	}

	public static function render_jsonld() {
		if ( ! is_singular( KSPPM_CPT ) ) {
			return;
		}
		$post_id = get_queried_object_id();
		// (v0.0.18-beta): パスワード保護投稿で JSON-LD を抑制
		// 背景: post_password 設定でもフロント本文は隠されるが JSON-LD は無条件出力されていた
		//      → PII (生年月日 / 出生地等) を含む schema.org JSON がゲスト閲覧者に漏洩していた
		if ( $post_id && post_password_required( $post_id ) ) {
			return;
		}
		$json = self::get_jsonld( $post_id );
		if ( ! $json ) return;

		// F19: プラグイン名を明かす HTML コメントは fingerprinting の攻撃面になるため削除 (機能影響なし)。
		// JSON_HEX_TAG/JSON_HEX_AMP で </script> や &amp; を hex escape (XSS 防御深化、JSON-LD パーサは \/ も解釈可)
		echo "\n" . '<script type="application/ld+json">' . "\n" . wp_json_encode( $json, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_PRETTY_PRINT ) . "\n" . '</script>' . "\n";
	}

	/**
	 * ProfilePage + Person JSON-LD を構築
	 */
	public static function get_jsonld( $post_id ) {
		// (v0.0.20-beta / multiprefix): cache key に投稿固有プレフィックスの hash を含めて
		// prefix 変更時の即時 cache miss を保証 (古い prefix の BreadcrumbList が混入する事故防止)。
		// マルチプレフィックスでは投稿ごとに prefix が異なるため、サイト共通 get_url_prefix() ではなく
		// 投稿固有 get_post_url_prefix() を使う (per-profile prefix 変更時に確実に invalidate される)。
		$prefix_for_cache = class_exists( 'KSPPM_CPT' ) ? KSPPM_CPT::get_post_url_prefix( $post_id ) : '';
		$cache_key = self::cache_key( $post_id, $prefix_for_cache );
		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			return apply_filters( 'ksppm/json_ld', $cached, $post_id );
		}

		$data = KSPPM_Meta::get_all( $post_id );
		$url  = get_permalink( $post_id );

		$person_id = trailingslashit( $url ) . '#person';
		$page_id   = trailingslashit( $url ) . '#profilepage';

		$person = array(
			'@type'             => 'Person',
			'@id'               => $person_id,
			'mainEntityOfPage'  => $page_id,
		);

		// name
		$name = $data['full_name'] !== '' ? $data['full_name'] : trim( ( $data['family_name'] ?? '' ) . ' ' . ( $data['given_name'] ?? '' ) );
		if ( $name === '' ) $name = get_the_title( $post_id );
		$person['name'] = $name;

		if ( ! empty( $data['family_name'] ) ) $person['familyName'] = $data['family_name'];
		if ( ! empty( $data['given_name'] ) )  $person['givenName']  = $data['given_name'];

		// alternateName: Romaji + nickname を array で
		$alt = array();
		if ( ! empty( $data['name_romaji'] ) ) $alt[] = $data['name_romaji'];
		if ( ! empty( $data['nickname'] ) )    $alt[] = $data['nickname'];
		if ( $alt ) $person['alternateName'] = count( $alt ) === 1 ? $alt[0] : $alt;

		if ( ! empty( $data['honorific_prefix'] ) ) $person['honorificPrefix'] = $data['honorific_prefix'];
		if ( ! empty( $data['honorific_suffix'] ) ) $person['honorificSuffix'] = $data['honorific_suffix'];
		if ( ! empty( $data['job_title'] ) )        $person['jobTitle'] = $data['job_title'];
		if ( ! empty( $data['description'] ) )      $person['description'] = $data['description'];
		if ( ! empty( $data['pronouns'] ) )         $person['pronouns'] = $data['pronouns'];

		// image
		if ( ! empty( $data['main_image'] ) ) {
			$image_url = wp_get_attachment_url( $data['main_image'] );
			if ( $image_url ) $person['image'] = $image_url;
		}
		$person['url'] = $url;

		// 個人情報 (公開設定で制御)
		if ( ! empty( $data['show_birth_date'] ) && ! empty( $data['birth_date'] ) ) {
			$person['birthDate'] = $data['birth_date'];
		}
		// F18: birthPlace も show_birth_date でガード (生年月日と関連する PII)
		if ( ! empty( $data['show_birth_date'] ) && ! empty( $data['birth_place'] ) ) {
			$person['birthPlace'] = array( '@type' => 'Place', 'name' => $data['birth_place'] );
		}
		// (v0.0.18-beta): show_nationality で JSON-LD 出力制御 (default 1=ON で後方互換)
		if ( ! empty( $data['nationality'] ) && ! empty( $data['show_nationality'] ) ) {
			$person['nationality'] = array( '@type' => 'Country', 'name' => $data['nationality'] );
		}
		if ( ! empty( $data['show_height_weight'] ) ) {
			if ( ! empty( $data['height_cm'] ) ) {
				$person['height'] = array( '@type' => 'QuantitativeValue', 'value' => floatval( $data['height_cm'] ), 'unitCode' => 'CMT' );
			}
			if ( ! empty( $data['weight_kg'] ) ) {
				$person['weight'] = array( '@type' => 'QuantitativeValue', 'value' => floatval( $data['weight_kg'] ), 'unitCode' => 'KGM' );
			}
		}
		// F5: gender 公開制御 (show_gender ガード)
		if ( ! empty( $data['show_gender'] ) && ! empty( $data['gender'] ) ) {
			$person['gender'] = $data['gender'];
		}

		// 連絡先 (公開設定で制御)
		if ( ! empty( $data['show_email'] ) && ! empty( $data['email'] ) ) $person['email'] = $data['email'];
		if ( ! empty( $data['show_telephone'] ) && ! empty( $data['telephone'] ) ) $person['telephone'] = $data['telephone'];

		// sameAs
		$same_as = array();
		foreach ( $data['same_as'] ?? array() as $row ) {
			if ( ! empty( $row['url'] ) ) $same_as[] = $row['url'];
		}
		if ( $same_as ) $person['sameAs'] = $same_as;

		// worksFor
		$works_for = array();
		foreach ( $data['works_for'] ?? array() as $row ) {
			if ( empty( $row['name'] ) ) continue;
			$org = array( '@type' => 'Organization', 'name' => $row['name'] );
			if ( ! empty( $row['url'] ) ) $org['url'] = $row['url'];
			$works_for[] = $org;
		}
		if ( $works_for ) $person['worksFor'] = count( $works_for ) === 1 ? $works_for[0] : $works_for;

		// alumniOf
		$alumni = array();
		foreach ( $data['alumni_of'] ?? array() as $row ) {
			if ( empty( $row['name'] ) ) continue;
			$org = array( '@type' => 'EducationalOrganization', 'name' => $row['name'] );
			if ( ! empty( $row['url'] ) ) $org['url'] = $row['url'];
			$alumni[] = $org;
		}
		if ( $alumni ) $person['alumniOf'] = count( $alumni ) === 1 ? $alumni[0] : $alumni;

		// hasCredential
		$creds = array();
		foreach ( $data['credentials'] ?? array() as $row ) {
			if ( empty( $row['name'] ) ) continue;
			$c = array( '@type' => 'EducationalOccupationalCredential', 'name' => $row['name'] );
			if ( ! empty( $row['issuer'] ) )    $c['recognizedBy'] = array( '@type' => 'Organization', 'name' => $row['issuer'] );
			if ( ! empty( $row['issued_at'] ) ) $c['dateCreated'] = $row['issued_at'];
			if ( ! empty( $row['url'] ) )       $c['url'] = $row['url'];
			$creds[] = $c;
		}
		if ( $creds ) $person['hasCredential'] = $creds;

		// award
		$awards = array();
		foreach ( $data['awards'] ?? array() as $row ) {
			if ( ! empty( $row['name'] ) ) $awards[] = $row['name'];
		}
		if ( $awards ) $person['award'] = count( $awards ) === 1 ? $awards[0] : $awards;

		// knowsAbout / knowsLanguage / skills
		$knows_about = array();
		foreach ( $data['knows_about'] ?? array() as $row ) {
			if ( ! empty( $row['topic'] ) ) $knows_about[] = $row['topic'];
		}
		if ( $knows_about ) $person['knowsAbout'] = $knows_about;

		$knows_language = array();
		foreach ( $data['knows_language'] ?? array() as $row ) {
			if ( empty( $row['name'] ) ) continue;
			$lang = array( '@type' => 'Language', 'name' => $row['name'] );
			if ( ! empty( $row['code'] ) ) $lang['alternateName'] = $row['code'];
			$knows_language[] = $lang;
		}
		if ( $knows_language ) $person['knowsLanguage'] = $knows_language;

		$skills = array();
		foreach ( $data['skills'] ?? array() as $row ) {
			if ( ! empty( $row['name'] ) ) $skills[] = $row['name'];
		}
		if ( $skills ) $person['skills'] = count( $skills ) === 1 ? $skills[0] : $skills;

		// owns (Book / ScholarlyArticle / Patent)
		$owns = array();
		foreach ( $data['books'] ?? array() as $row ) {
			if ( empty( $row['name'] ) ) continue;
			$b = array( '@type' => 'Book', 'name' => $row['name'] );
			if ( ! empty( $row['isbn'] ) )      $b['isbn'] = $row['isbn'];
			if ( ! empty( $row['publisher'] ) ) $b['publisher'] = array( '@type' => 'Organization', 'name' => $row['publisher'] );
			if ( ! empty( $row['date'] ) )      $b['datePublished'] = $row['date'];
			if ( ! empty( $row['url'] ) )       $b['url'] = $row['url'];
			if ( ! empty( $row['image'] ) && ( $img_url = wp_get_attachment_url( absint( $row['image'] ) ) ) ) $b['image'] = $img_url;
			if ( ! empty( $row['description'] ) ) $b['description'] = $row['description'];
			$owns[] = $b;
		}
		foreach ( $data['articles'] ?? array() as $row ) {
			if ( empty( $row['name'] ) ) continue;
			$a = array( '@type' => 'ScholarlyArticle', 'name' => $row['name'], 'headline' => $row['name'] );
			if ( ! empty( $row['doi'] ) )       $a['identifier'] = array( '@type' => 'PropertyValue', 'propertyID' => 'DOI', 'value' => $row['doi'] );
			if ( ! empty( $row['publisher'] ) ) $a['publisher'] = array( '@type' => 'Organization', 'name' => $row['publisher'] );
			if ( ! empty( $row['date'] ) )      $a['datePublished'] = $row['date'];
			if ( ! empty( $row['date_modified'] ) ) $a['dateModified'] = $row['date_modified'];
			if ( ! empty( $row['url'] ) )       $a['url'] = $row['url'];
			if ( ! empty( $row['abstract'] ) )  $a['abstract'] = $row['abstract'];
			if ( ! empty( $row['image'] ) && ( $img_url = wp_get_attachment_url( absint( $row['image'] ) ) ) ) $a['image'] = $img_url;
			$a['author'] = array( '@type' => 'Person', 'name' => $name, 'url' => $url );
			$owns[] = $a;
		}
		foreach ( $data['patents'] ?? array() as $row ) {
			if ( empty( $row['name'] ) ) continue;
			$p = array( '@type' => 'CreativeWork', 'additionalType' => 'Patent', 'name' => $row['name'] );
			if ( ! empty( $row['patent_no'] ) ) $p['identifier'] = array( '@type' => 'PropertyValue', 'propertyID' => 'PatentNumber', 'value' => $row['patent_no'] );
			if ( ! empty( $row['date'] ) )      $p['dateCreated'] = $row['date'];
			if ( ! empty( $row['url'] ) )       $p['url'] = $row['url'];
			if ( ! empty( $row['description'] ) ) $p['description'] = $row['description'];
			if ( ! empty( $row['image'] ) && ( $img_url = wp_get_attachment_url( absint( $row['image'] ) ) ) ) $p['image'] = $img_url;
			$owns[] = $p;
		}
		if ( $owns ) $person['owns'] = $owns;

		// subjectOf (取材記事)
		$subject_of = array();
		foreach ( $data['media_mentions'] ?? array() as $row ) {
			if ( empty( $row['title'] ) ) continue;
			$a = array( '@type' => 'Article', 'headline' => $row['title'] );
			if ( ! empty( $row['publisher'] ) ) $a['publisher'] = array( '@type' => 'Organization', 'name' => $row['publisher'] );
			if ( ! empty( $row['date'] ) )      $a['datePublished'] = $row['date'];
			if ( ! empty( $row['date_modified'] ) ) $a['dateModified'] = $row['date_modified'];
			if ( ! empty( $row['url'] ) )       $a['url'] = $row['url'];
			if ( ! empty( $row['author'] ) )    $a['author'] = array( '@type' => 'Person', 'name' => $row['author'] );
			if ( ! empty( $row['image'] ) && ( $img_url = wp_get_attachment_url( absint( $row['image'] ) ) ) ) $a['image'] = $img_url;
			$subject_of[] = $a;
		}
		if ( $subject_of ) $person['subjectOf'] = $subject_of;

		// performerIn (講演イベント)
		$perform = array();
		foreach ( $data['speeches'] ?? array() as $row ) {
			if ( empty( $row['name'] ) ) continue;
			$e = array( '@type' => 'Event', 'name' => $row['name'] );
			if ( ! empty( $row['organizer'] ) ) $e['organizer'] = array( '@type' => 'Organization', 'name' => $row['organizer'] );
			if ( ! empty( $row['date'] ) )      $e['startDate'] = $row['date'];
			if ( ! empty( $row['end_date'] ) ) $e['endDate'] = $row['end_date'];
			if ( ! empty( $row['location'] ) || ! empty( $row['address'] ) ) {
				$e['location'] = array( '@type' => 'Place' );
				if ( ! empty( $row['location'] ) ) $e['location']['name'] = $row['location'];
				if ( ! empty( $row['address'] ) )  $e['location']['address'] = $row['address'];
			}
			if ( ! empty( $row['url'] ) )       $e['url'] = $row['url'];
			if ( ! empty( $row['image'] ) && ( $img_url = wp_get_attachment_url( absint( $row['image'] ) ) ) ) $e['image'] = $img_url;
			$perform[] = $e;
		}
		if ( $perform ) $person['performerIn'] = $perform;

		// Person を最終フィルタ
		$person = apply_filters( 'ksppm/person', $person, $post_id, $data );

		// option 未保存時のデフォルト適用 (init と同じ defaults)
		$settings = wp_parse_args( get_option( KSPPM_OPTION_KEY, array() ), array(
			'enable_jsonld'     => 1,
			'enable_breadcrumb' => 1,
			'enable_speakable'  => 1,
			'jsonld_position'   => 'head',
		) );

		// ProfilePage を組み立て (F11: 未使用の $post = get_post を削除)
		$profile_page = array(
			'@context'            => 'https://schema.org',
			'@type'               => 'ProfilePage',
			'@id'                 => $page_id,
			'url'                 => $url,
			'name'                => get_the_title( $post_id ),
			'dateCreated'         => get_the_date( 'c', $post_id ),
			'dateModified'        => get_the_modified_date( 'c', $post_id ),
			'mainEntity'          => $person,
		);

		// F1: breadcrumb トグル尊重 (enable_breadcrumb=0 なら出力しない)
		if ( ! empty( $settings['enable_breadcrumb'] ) ) {
			$items = array();
			$pos = 1;
			$items[] = array( '@type' => 'ListItem', 'position' => $pos++, 'name' => 'HOME', 'item' => home_url( '/' ) );
			// (v0.0.20-beta / multiprefix): 投稿固有プレフィックスがあれば中間セグメントを追加。
			// Google リッチリザルトは中間 (= 最終以外) の ListItem に item (URL) を必須とするため、
			// default で prefix アーカイブ URL (/{prefix}/) を item に埋め込む。
			// 名称は filter で上書き可能、default は prefix の最終セグメントを capitalize。
			// マルチプレフィックスでは投稿ごとに prefix が異なるため、サイト共通 get_url_prefix() ではなく
			// 投稿固有 get_post_url_prefix() を使う (breadcrumb のセグメント名が投稿の実 prefix と一致)。
			$prefix = class_exists( 'KSPPM_CPT' ) ? KSPPM_CPT::get_post_url_prefix( $post_id ) : '';
			if ( $prefix !== '' ) {
				$segments = explode( '/', $prefix );
				$default_label = ucwords( str_replace( array( '-', '_' ), ' ', end( $segments ) ) );
				$label = apply_filters( 'ksppm_breadcrumb_prefix_label', $default_label, $prefix );
				// 中間 item の URL。default は prefix アーカイブ URL。filter で上書き可能。
				// filter が空文字を返した場合は中間 ListItem 自体を省略する
				// (item 無しの中間 ListItem は Google リッチリザルトでエラーになるため出力しない)。
				$default_item_url = home_url( '/' . trailingslashit( $prefix ) );
				$item_url = apply_filters( 'ksppm_breadcrumb_prefix_item_url', $default_item_url, $prefix );
				// default URL を使う場合のみ実在ページに解決するか検証 (解決しなければ中間 ListItem を省略し 404 リンクを防ぐ)。
				// filter で明示上書きされた URL は owner の意図として尊重する。
				if ( $item_url === $default_item_url && ! url_to_postid( $default_item_url ) ) {
					$item_url = '';
				}
				if ( is_string( $label ) && $label !== '' && is_string( $item_url ) && $item_url !== '' ) {
					$items[] = array(
						'@type'    => 'ListItem',
						'position' => $pos++,
						'name'     => $label,
						'item'     => esc_url_raw( $item_url ),
					);
				}
			}
			$items[] = array( '@type' => 'ListItem', 'position' => $pos++, 'name' => $person['name'], 'item' => $url );
			$profile_page['breadcrumb'] = array(
				'@type'           => 'BreadcrumbList',
				'itemListElement' => $items,
			);
		}

		// primaryImageOfPage (STR-23)
		if ( ! empty( $person['image'] ) ) {
			$profile_page['primaryImageOfPage'] = array(
				'@type'      => 'ImageObject',
				'contentUrl' => $person['image'],
			);
		}

		// speakable (STR-23, LLMO 対応)
		if ( ! empty( $settings['enable_speakable'] ) ) {
			$profile_page['speakable'] = array(
				'@type'    => 'SpeakableSpecification',
				'cssSelector' => array( '.ksppm-summary', '.ksppm-fullname', '.ksppm-jobtitle' ),
			);
		}

		$profile_page = apply_filters( 'ksppm/profile_page', $profile_page, $post_id, $data );

		set_transient( $cache_key, $profile_page, HOUR_IN_SECONDS );
		return apply_filters( 'ksppm/json_ld', $profile_page, $post_id );
	}

	// (F3) JSON-LD transient の cache key を生成。プラグイン版数を含めることでコード更新ごとに自動 invalidate。
	// 生成(get_jsonld)と削除(flush_cache)で必ず本メソッドを共用し、キー式のドリフトを防ぐ。
	protected static function cache_key( $post_id, $prefix ) {
		return 'ksppm_jsonld_' . $post_id . '_' . substr( md5( (string) $prefix . '|' . KSPPM_VERSION ), 0, 8 );
	}

	public static function flush_cache( $post_id ) {
		// cache key に prefix hash suffix が付くので
		// hash 無し旧 key + 現 prefix の key + 履歴 prefix の key を全て削除する
		delete_transient( 'ksppm_jsonld_' . $post_id ); // 旧形式 (後方互換)
		$settings = get_option( KSPPM_OPTION_KEY, array() );
		$prefixes = array( isset( $settings['url_prefix'] ) ? (string) $settings['url_prefix'] : '' );
		if ( ! empty( $settings['url_prefix_history'] ) && is_array( $settings['url_prefix_history'] ) ) {
			$prefixes = array_merge( $prefixes, $settings['url_prefix_history'] );
		}
		// (multiprefix): cache key は get_post_url_prefix() で組まれるため、投稿固有の現プレフィックスと
		// その変更履歴 (_ksppm_url_prefix_history) の hash variant も削除しないと stale JSON-LD が残る。
		if ( class_exists( 'KSPPM_CPT' ) ) {
			$prefixes[] = KSPPM_CPT::get_post_url_prefix( $post_id );
		}
		$post_hist = get_post_meta( $post_id, KSPPM_META_PREFIX . 'url_prefix_history', true );
		if ( is_array( $post_hist ) ) {
			$prefixes = array_merge( $prefixes, $post_hist );
		}
		// 念のため空文字も含めて重複排除
		$prefixes = array_unique( array_merge( $prefixes, array( '' ) ) );
		foreach ( $prefixes as $p ) {
			delete_transient( self::cache_key( $post_id, $p ) );
		}
	}

	/**
	 * F15: attachment 単独更新で全プロフィールのキャッシュを無効化
	 *
	 * (v0.0.13-beta): posts_per_page=-1 を LIMIT 500 + while offset loop に置換。
	 * fields => 'ids' でメモリ消費は ID 配列のみ (1 件 8 bytes) のため実害は極小だが、防御的プログラミングとして
	 * LIMIT 500 で段階処理する (10 万件超でもメモリ安全)。
	 */
	public static function flush_all_cache() {
		$offset = 0;
		$limit  = 500;
		do {
			$ids = get_posts( array(
				'post_type'      => KSPPM_CPT,
				'posts_per_page' => $limit,
				'offset'         => $offset,
				'fields'         => 'ids',
				'post_status'    => 'any',
				'no_found_rows'  => true,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			) );
			foreach ( $ids as $id ) {
				self::flush_cache( $id ); // 全 prefix variant の transient を削除
			}
			$offset += $limit;
		} while ( count( $ids ) === $limit );
	}
}
