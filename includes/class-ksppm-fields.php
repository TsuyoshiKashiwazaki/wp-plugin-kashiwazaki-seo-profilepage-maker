<?php
/**
 * フィールドスキーマ定義: 模範ページ 28 h3 セクション網羅 + schema.org Person プロパティ
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KSPPM_Fields {

	/**
	 * 全フィールド定義 (タブ別グループ化)
	 * 各フィールドは:
	 *   key, label, type (text/textarea/email/url/date/number/select/checkbox/wysiwyg/image/repeater),
	 *   schema_property (schema.org マッピング, null なら出力なし),
	 *   sensitive (個人情報フラグ),
	 *   help, default
	 */
	public static function get_groups() {
		return array(
			'basic' => array(
				'label'  => __( '基本情報', 'kashiwazaki-seo-profilepage-maker' ),
				'fields' => array(
					array( 'key' => 'family_name',      'label' => __( '姓', 'kashiwazaki-seo-profilepage-maker' ),                    'type' => 'text',     'schema_property' => 'familyName' ),
					array( 'key' => 'given_name',       'label' => __( '名', 'kashiwazaki-seo-profilepage-maker' ),                    'type' => 'text',     'schema_property' => 'givenName' ),
					array( 'key' => 'full_name',        'label' => __( '氏名 (表示用)', 'kashiwazaki-seo-profilepage-maker' ),         'type' => 'text',     'schema_property' => 'name' ),
					array( 'key' => 'name_kana',        'label' => __( 'ふりがな', 'kashiwazaki-seo-profilepage-maker' ),              'type' => 'text',     'schema_property' => null, 'help' => __( '日本語ふりがな (任意)', 'kashiwazaki-seo-profilepage-maker' ) ),
					array( 'key' => 'name_romaji',      'label' => __( 'ローマ字 (英字表記)', 'kashiwazaki-seo-profilepage-maker' ),     'type' => 'text',     'schema_property' => 'alternateName', 'help' => __( '海外 SEO 対応用', 'kashiwazaki-seo-profilepage-maker' ) ),
					array( 'key' => 'nickname',         'label' => __( 'ニックネーム', 'kashiwazaki-seo-profilepage-maker' ),          'type' => 'text',     'schema_property' => 'alternateName' ),
					array( 'key' => 'honorific_prefix', 'label' => __( '敬称 (前)', 'kashiwazaki-seo-profilepage-maker' ),             'type' => 'text',     'schema_property' => 'honorificPrefix', 'help' => __( 'Dr., 教授 等', 'kashiwazaki-seo-profilepage-maker' ) ),
					array( 'key' => 'honorific_suffix', 'label' => __( '敬称 (後)', 'kashiwazaki-seo-profilepage-maker' ),             'type' => 'text',     'schema_property' => 'honorificSuffix' ),
					array( 'key' => 'job_title',        'label' => __( '現職 (肩書)', 'kashiwazaki-seo-profilepage-maker' ),           'type' => 'text',     'schema_property' => 'jobTitle' ),
					array( 'key' => 'description',      'label' => __( '自己紹介 (短文)', 'kashiwazaki-seo-profilepage-maker' ),       'type' => 'textarea', 'schema_property' => 'description', 'help' => __( '1-3 行のプレーンテキスト', 'kashiwazaki-seo-profilepage-maker' ) ),
					array( 'key' => 'biography',        'label' => __( '来歴 (長文・リッチ)', 'kashiwazaki-seo-profilepage-maker' ),   'type' => 'wysiwyg',  'schema_property' => null, 'help' => __( 'フロント表示用 (HTML 可)', 'kashiwazaki-seo-profilepage-maker' ) ),
					array( 'key' => 'pronouns',         'label' => __( '代名詞', 'kashiwazaki-seo-profilepage-maker' ),                'type' => 'text',     'schema_property' => 'pronouns', 'help' => __( 'he/him, she/her, they/them 等', 'kashiwazaki-seo-profilepage-maker' ) ),
				),
			),
			'identity' => array(
				'label'  => __( '個人情報', 'kashiwazaki-seo-profilepage-maker' ),
				'fields' => array(
					array( 'key' => 'birth_date',       'label' => __( '生年月日', 'kashiwazaki-seo-profilepage-maker' ),         'type' => 'date',   'schema_property' => 'birthDate', 'sensitive' => true ),
					array( 'key' => 'birth_place',      'label' => __( '出生地', 'kashiwazaki-seo-profilepage-maker' ),           'type' => 'text',   'schema_property' => 'birthPlace.name', 'sensitive' => true ),
					array( 'key' => 'nationality',      'label' => __( '国籍', 'kashiwazaki-seo-profilepage-maker' ),             'type' => 'text',   'schema_property' => 'nationality.name' ),
					// (v0.0.18-beta): show_nationality 追加で他 PII と一貫性確保。default 1 (true) で後方互換維持。
					array( 'key' => 'show_nationality', 'label' => __( '国籍を公開する', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'checkbox', 'schema_property' => null, 'default' => 1, 'help' => __( 'OFF の場合は JSON-LD と表示の両方から除外されます (デフォルト: ON / 後方互換維持)', 'kashiwazaki-seo-profilepage-maker' ) ),
					array( 'key' => 'gender',           'label' => __( '性別', 'kashiwazaki-seo-profilepage-maker' ),             'type' => 'select', 'schema_property' => 'gender', 'options' => array( '' => __( '未選択', 'kashiwazaki-seo-profilepage-maker' ), 'Male' => __( '男性', 'kashiwazaki-seo-profilepage-maker' ), 'Female' => __( '女性', 'kashiwazaki-seo-profilepage-maker' ), 'Other' => __( 'その他', 'kashiwazaki-seo-profilepage-maker' ) ), 'sensitive' => true ),
					array( 'key' => 'height_cm',        'label' => __( '身長 (cm)', 'kashiwazaki-seo-profilepage-maker' ),        'type' => 'number', 'schema_property' => 'height', 'sensitive' => true ),
					array( 'key' => 'weight_kg',        'label' => __( '体重 (kg)', 'kashiwazaki-seo-profilepage-maker' ),        'type' => 'number', 'schema_property' => 'weight', 'sensitive' => true ),
					array( 'key' => 'show_birth_date',  'label' => __( '生年月日・出生地を公開する', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'checkbox', 'schema_property' => null, 'default' => 0, 'help' => __( 'OFF の場合は構造化データに出力されません', 'kashiwazaki-seo-profilepage-maker' ) ),
					array( 'key' => 'show_height_weight', 'label' => __( '身長・体重を公開する', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'checkbox', 'schema_property' => null, 'default' => 0 ),
					array( 'key' => 'show_gender',      'label' => __( '性別を公開する', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'checkbox', 'schema_property' => null, 'default' => 0 ),
				),
			),
			'contact' => array(
				'label'  => __( '連絡先・SNS', 'kashiwazaki-seo-profilepage-maker' ),
				'fields' => array(
					array( 'key' => 'email',            'label' => __( 'メールアドレス', 'kashiwazaki-seo-profilepage-maker' ),   'type' => 'email', 'schema_property' => 'email', 'sensitive' => true ),
					array( 'key' => 'show_email',       'label' => __( 'メールを公開する', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'checkbox', 'schema_property' => null, 'default' => 0 ),
					array( 'key' => 'telephone',        'label' => __( '電話番号', 'kashiwazaki-seo-profilepage-maker' ),         'type' => 'text',  'schema_property' => 'telephone', 'sensitive' => true ),
					array( 'key' => 'show_telephone',   'label' => __( '電話を公開する', 'kashiwazaki-seo-profilepage-maker' ),   'type' => 'checkbox', 'schema_property' => null, 'default' => 0 ),
					array( 'key' => 'url',              'label' => __( 'ウェブサイト URL', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'url',   'schema_property' => 'url' ),
					array( 'key' => 'same_as',          'label' => __( 'SNS・外部プロフィール URL', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'repeater', 'schema_property' => 'sameAs', 'item_fields' => array(
						array( 'key' => 'url',    'label' => __( 'URL', 'kashiwazaki-seo-profilepage-maker' ),    'type' => 'url' ),
						array( 'key' => 'label',  'label' => __( 'サービス名 (表示用)', 'kashiwazaki-seo-profilepage-maker' ),  'type' => 'text' ),
					) ),
					array( 'key' => 'video_channels',  'label' => __( '動画配信チャンネル', 'kashiwazaki-seo-profilepage-maker' ),  'type' => 'repeater', 'schema_property' => null, 'item_fields' => array(
						// (v0.0.21-beta): チャンネル名 field を追加 (page 7「柏崎剛チャンネル」等の識別用)
						array( 'key' => 'name',     'label' => __( 'チャンネル名', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'text' ),
						array( 'key' => 'platform', 'label' => __( 'プラットフォーム (YouTube/TikTok 等)', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'text' ),
						array( 'key' => 'url',      'label' => __( 'チャンネル URL', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'url' ),
					) ),
				),
			),
			'career' => array(
				'label'  => __( '経歴・所属', 'kashiwazaki-seo-profilepage-maker' ),
				'fields' => array(
					array( 'key' => 'works_for', 'label' => __( '現職・所属組織', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'repeater', 'schema_property' => 'worksFor', 'item_fields' => array(
						array( 'key' => 'name', 'label' => __( '組織名', 'kashiwazaki-seo-profilepage-maker' ),     'type' => 'text' ),
						array( 'key' => 'url',  'label' => __( '組織 URL', 'kashiwazaki-seo-profilepage-maker' ),  'type' => 'url' ),
						array( 'key' => 'role', 'label' => __( '役職', 'kashiwazaki-seo-profilepage-maker' ),       'type' => 'text' ),
						array( 'key' => 'start_date', 'label' => __( '在任開始', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'date' ),
						array( 'key' => 'end_date',   'label' => __( '在任終了 (現職は空)', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'date' ),
					) ),
					array( 'key' => 'past_career', 'label' => __( '以前の経歴', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'repeater', 'schema_property' => null, 'item_fields' => array(
						array( 'key' => 'name', 'label' => __( '組織名', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'text' ),
						array( 'key' => 'role', 'label' => __( '役職', 'kashiwazaki-seo-profilepage-maker' ),   'type' => 'text' ),
						array( 'key' => 'start_date', 'label' => __( '在任開始', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'date' ),
						array( 'key' => 'end_date',   'label' => __( '在任終了', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'date' ),
						array( 'key' => 'description', 'label' => __( '説明', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'textarea' ),
					) ),
					array( 'key' => 'alumni_of', 'label' => __( '学歴', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'repeater', 'schema_property' => 'alumniOf', 'item_fields' => array(
						array( 'key' => 'name', 'label' => __( '学校名', 'kashiwazaki-seo-profilepage-maker' ),  'type' => 'text' ),
						array( 'key' => 'url',  'label' => __( '学校 URL', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'url' ),
						array( 'key' => 'degree', 'label' => __( '学位', 'kashiwazaki-seo-profilepage-maker' ),  'type' => 'text' ),
						array( 'key' => 'graduated_at', 'label' => __( '卒業年月', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'date' ),
					) ),
					array( 'key' => 'credentials', 'label' => __( '資格・認定', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'repeater', 'schema_property' => 'hasCredential', 'item_fields' => array(
						array( 'key' => 'name',        'label' => __( '資格名', 'kashiwazaki-seo-profilepage-maker' ),   'type' => 'text' ),
						array( 'key' => 'issuer',      'label' => __( '発行機関', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'text' ),
						array( 'key' => 'issued_at',   'label' => __( '取得日', 'kashiwazaki-seo-profilepage-maker' ),   'type' => 'date' ),
						array( 'key' => 'expires_at',  'label' => __( '有効期限', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'date' ),
						array( 'key' => 'url',         'label' => __( '証明 URL', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'url' ),
					) ),
					array( 'key' => 'awards', 'label' => __( '受賞・表彰', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'repeater', 'schema_property' => 'award', 'item_fields' => array(
						array( 'key' => 'name',        'label' => __( '賞名', 'kashiwazaki-seo-profilepage-maker' ),     'type' => 'text' ),
						array( 'key' => 'date',        'label' => __( '受賞日', 'kashiwazaki-seo-profilepage-maker' ),   'type' => 'date' ),
						array( 'key' => 'issuer',      'label' => __( '授与機関', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'text' ),
						array( 'key' => 'url',         'label' => __( '関連 URL', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'url' ),
					) ),
					array( 'key' => 'hobbies', 'label' => __( '趣味・特技', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'textarea', 'schema_property' => null ),
				),
			),
			'expertise' => array(
				'label'  => __( '専門性・知識', 'kashiwazaki-seo-profilepage-maker' ),
				'fields' => array(
					array( 'key' => 'knows_about', 'label' => __( '専門分野', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'repeater', 'schema_property' => 'knowsAbout', 'item_fields' => array(
						array( 'key' => 'topic', 'label' => __( 'トピック', 'kashiwazaki-seo-profilepage-maker' ),  'type' => 'text' ),
						array( 'key' => 'url',   'label' => __( 'Wikipedia / 関連 URL', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'url' ),
					) ),
					array( 'key' => 'knows_language', 'label' => __( '対応言語', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'repeater', 'schema_property' => 'knowsLanguage', 'item_fields' => array(
						array( 'key' => 'name', 'label' => __( '言語名', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'text' ),
						array( 'key' => 'code', 'label' => __( '言語コード (ja, en 等)', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'text' ),
					) ),
					array( 'key' => 'skills', 'label' => __( 'スキル', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'repeater', 'schema_property' => 'skills', 'item_fields' => array(
						array( 'key' => 'name', 'label' => __( 'スキル名', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'text' ),
						array( 'key' => 'level', 'label' => __( 'レベル', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'text' ),
					) ),
				),
			),
			'works' => array(
				'label'  => __( '著作・論文・特許', 'kashiwazaki-seo-profilepage-maker' ),
				'fields' => array(
					array( 'key' => 'books', 'label' => __( '書籍 (Book)', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'repeater', 'schema_property' => 'owns.Book', 'item_fields' => array(
						array( 'key' => 'name',        'label' => __( 'タイトル', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'text' ),
						array( 'key' => 'isbn',        'label' => __( 'ISBN', 'kashiwazaki-seo-profilepage-maker' ),     'type' => 'text' ),
						array( 'key' => 'publisher',   'label' => __( '出版社', 'kashiwazaki-seo-profilepage-maker' ),   'type' => 'text' ),
						array( 'key' => 'date',        'label' => __( '出版日', 'kashiwazaki-seo-profilepage-maker' ),   'type' => 'date' ),
						array( 'key' => 'url',         'label' => __( 'URL', 'kashiwazaki-seo-profilepage-maker' ),      'type' => 'url' ),
						array( 'key' => 'image',       'label' => __( '表紙画像', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'image' ),
						array( 'key' => 'description', 'label' => __( '概要', 'kashiwazaki-seo-profilepage-maker' ),     'type' => 'textarea' ),
					) ),
					array( 'key' => 'articles', 'label' => __( '学術論文 (ScholarlyArticle)', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'repeater', 'schema_property' => 'owns.ScholarlyArticle', 'item_fields' => array(
						array( 'key' => 'name',         'label' => __( 'タイトル', 'kashiwazaki-seo-profilepage-maker' ),   'type' => 'text' ),
						array( 'key' => 'doi',          'label' => __( 'DOI', 'kashiwazaki-seo-profilepage-maker' ),        'type' => 'text' ),
						array( 'key' => 'date',         'label' => __( '発行日', 'kashiwazaki-seo-profilepage-maker' ),     'type' => 'date' ),
						array( 'key' => 'date_modified', 'label' => __( '更新日', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'date' ),
						array( 'key' => 'publisher',    'label' => __( '出版誌', 'kashiwazaki-seo-profilepage-maker' ),     'type' => 'text' ),
						array( 'key' => 'url',          'label' => __( 'URL', 'kashiwazaki-seo-profilepage-maker' ),        'type' => 'url' ),
						array( 'key' => 'pdf_url',      'label' => __( 'PDF URL', 'kashiwazaki-seo-profilepage-maker' ),    'type' => 'url' ),
						array( 'key' => 'image',        'label' => __( '論文サムネイル', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'image' ),
						array( 'key' => 'abstract',     'label' => __( '要旨', 'kashiwazaki-seo-profilepage-maker' ),       'type' => 'textarea' ),
					) ),
					array( 'key' => 'patents', 'label' => __( '特許 (CreativeWork)', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'repeater', 'schema_property' => 'owns.Patent', 'item_fields' => array(
						array( 'key' => 'name',         'label' => __( '特許名', 'kashiwazaki-seo-profilepage-maker' ),     'type' => 'text' ),
						array( 'key' => 'patent_no',    'label' => __( '特許番号', 'kashiwazaki-seo-profilepage-maker' ),   'type' => 'text' ),
						array( 'key' => 'app_no',       'label' => __( '出願番号', 'kashiwazaki-seo-profilepage-maker' ),   'type' => 'text' ),
						array( 'key' => 'date',         'label' => __( '取得/出願日', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'date' ),
						array( 'key' => 'url',          'label' => __( 'URL', 'kashiwazaki-seo-profilepage-maker' ),        'type' => 'url' ),
						array( 'key' => 'image',        'label' => __( '特許図面', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'image' ),
						array( 'key' => 'description',  'label' => __( '説明', 'kashiwazaki-seo-profilepage-maker' ),       'type' => 'textarea' ),
					) ),
					array( 'key' => 'trademarks', 'label' => __( '商標登録', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'repeater', 'schema_property' => null, 'item_fields' => array(
						array( 'key' => 'name',         'label' => __( '商標名', 'kashiwazaki-seo-profilepage-maker' ),     'type' => 'text' ),
						array( 'key' => 'reg_no',       'label' => __( '登録番号', 'kashiwazaki-seo-profilepage-maker' ),   'type' => 'text' ),
						array( 'key' => 'date',         'label' => __( '出願日', 'kashiwazaki-seo-profilepage-maker' ),     'type' => 'date' ),
						array( 'key' => 'classes',      'label' => __( '区分', 'kashiwazaki-seo-profilepage-maker' ),       'type' => 'text' ),
						array( 'key' => 'url',          'label' => __( 'URL', 'kashiwazaki-seo-profilepage-maker' ),        'type' => 'url' ),
					) ),
					array( 'key' => 'media_mentions', 'label' => __( '取材・執筆・監修', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'repeater', 'schema_property' => 'subjectOf', 'item_fields' => array(
						array( 'key' => 'title',        'label' => __( 'タイトル / メディア名', 'kashiwazaki-seo-profilepage-maker' ),   'type' => 'text' ),
						// v0.0.23-beta: ラベルを「メディア名」→「発行元 (法人名)」に明確化、運営会社を入れる
						array( 'key' => 'publisher',    'label' => __( '発行元 (法人名)', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'text' ),
						array( 'key' => 'date',         'label' => __( '掲載日', 'kashiwazaki-seo-profilepage-maker' ),     'type' => 'date' ),
						array( 'key' => 'date_modified', 'label' => __( '更新日', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'date' ),
						array( 'key' => 'url',          'label' => __( 'URL', 'kashiwazaki-seo-profilepage-maker' ),        'type' => 'url' ),
						array( 'key' => 'type',         'label' => __( '種別 (取材/執筆/監修)', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'text' ),
						array( 'key' => 'author',       'label' => __( '著者 / 執筆者', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'text' ),
						array( 'key' => 'image',        'label' => __( 'サムネイル画像', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'image' ),
					) ),
					array( 'key' => 'speeches', 'label' => __( '講演・セミナー', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'repeater', 'schema_property' => 'performerIn', 'item_fields' => array(
						array( 'key' => 'name',         'label' => __( 'イベント名', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'text' ),
						array( 'key' => 'organizer',    'label' => __( '主催', 'kashiwazaki-seo-profilepage-maker' ),       'type' => 'text' ),
						array( 'key' => 'date',         'label' => __( '開催日', 'kashiwazaki-seo-profilepage-maker' ),     'type' => 'date' ),
						array( 'key' => 'end_date', 'label' => __( '終了日', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'date' ),
						array( 'key' => 'location',     'label' => __( '会場', 'kashiwazaki-seo-profilepage-maker' ),       'type' => 'text' ),
						array( 'key' => 'address', 'label' => __( '会場住所', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'text' ),
						array( 'key' => 'image',        'label' => __( 'イベント画像', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'image' ),
						array( 'key' => 'url',          'label' => __( 'URL', 'kashiwazaki-seo-profilepage-maker' ),        'type' => 'url' ),
					) ),
				),
			),
			// (custom blocks): 旧「ツール・サービス」(柏崎氏専用の固定リピーター free_tools/partner_tools) を
			// 汎用「カスタムブロック」(任意名称ブロックを複数追加、各ブロックに 見出し/説明/URL/画像 の項目) に置換。
			// key は 'tools' を維持 (ショートコード section='tools' / display_order_preset / タブ id 後方互換)。
			'tools' => array(
				'label'  => __( 'カスタムブロック', 'kashiwazaki-seo-profilepage-maker' ),
				'fields' => array(
					array(
						'key'         => 'custom_blocks',
						'label'       => __( 'カスタムブロック', 'kashiwazaki-seo-profilepage-maker' ),
						'type'        => 'blocks',
						'schema_property' => null,
						'help'        => __( '任意の名称のブロックを追加し、各ブロックに「見出し・説明・URL・画像」の項目を登録できます。フロントではブロック名が見出しとして表示されます。', 'kashiwazaki-seo-profilepage-maker' ),
						// ブロック内「項目」のサブフィールド (見出し+説明+URL+画像)
						'item_fields' => array(
							array( 'key' => 'heading',     'label' => __( '見出し', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'text' ),
							array( 'key' => 'description', 'label' => __( '説明', 'kashiwazaki-seo-profilepage-maker' ),   'type' => 'textarea' ),
							array( 'key' => 'url',         'label' => __( 'URL', 'kashiwazaki-seo-profilepage-maker' ),    'type' => 'url' ),
							array( 'key' => 'image',       'label' => __( '画像', 'kashiwazaki-seo-profilepage-maker' ),   'type' => 'image' ),
						),
					),
				),
			),
			'inquiry' => array(
				'label'  => __( 'お問い合わせ', 'kashiwazaki-seo-profilepage-maker' ),
				'fields' => array(
					array( 'key' => 'contact_text',     'label' => __( 'お問い合わせ本文 (記事本文に存在する 1 段落のみ)', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'textarea', 'schema_property' => null ),
					array( 'key' => 'contact_form_url', 'label' => __( 'お問い合わせフォーム URL', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'url', 'schema_property' => null ),
				),
			),
			'media' => array(
				'label'  => __( '画像・メディア', 'kashiwazaki-seo-profilepage-maker' ),
				'fields' => array(
					array( 'key' => 'main_image',    'label' => __( 'メインプロフィール画像 (顔写真)', 'kashiwazaki-seo-profilepage-maker' ), 'type' => 'image', 'schema_property' => 'image' ),
					array( 'key' => 'cover_image',   'label' => __( 'カバー画像 (バナー)', 'kashiwazaki-seo-profilepage-maker' ),              'type' => 'image', 'schema_property' => null ),
				),
			),
		);
	}

	/**
	 * 全フィールド (フラット展開)
	 */
	public static function get_all_fields() {
		$out = array();
		foreach ( self::get_groups() as $group_key => $group ) {
			foreach ( $group['fields'] as $field ) {
				$field['group'] = $group_key;
				$out[ $field['key'] ] = $field;
			}
		}
		return $out;
	}

	/**
	 * デフォルト値を取得
	 */
	public static function get_default( $field ) {
		return isset( $field['default'] ) ? $field['default'] : '';
	}
}
