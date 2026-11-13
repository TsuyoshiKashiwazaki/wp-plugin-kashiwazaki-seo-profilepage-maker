<?php
/**
 * Profile content template (rendered by KSPPM_Frontend: render)
 * Variables: $post_id, $data, $args
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$section = $args['section'] ?? 'all';
$show    = function ( $sec ) use ( $section ) { return $section === 'all' || $section === $sec; };

// F16: shortcode 経路では heading_offset=1 で h1→h2 / h2→h3 にシフト (a11y h1 重複回避)
$ho           = isset( $args['heading_offset'] ) ? (int) $args['heading_offset'] : 0;
$h_top        = 'h' . max( 1, min( 6, 1 + $ho ) );
$h_section    = 'h' . max( 1, min( 6, 2 + $ho ) );
$h_subsection = 'h' . max( 1, min( 6, 3 + $ho ) );

$display_name = $data['full_name'] !== '' ? $data['full_name'] : ( ( $data['family_name'] ?? '' ) . ' ' . ( $data['given_name'] ?? '' ) );
$display_name = trim( $display_name );
if ( $display_name === '' ) $display_name = get_the_title( $post_id );
?>

<div class="ksppm-profile" id="ksppm-profile-<?php echo esc_attr( $post_id ); ?>">

	<?php if ( $show( 'basic' ) ) : ?>
	<header class="ksppm-hero">
		<?php if ( ! empty( $data['cover_image'] ) ) : ?>
			<div class="ksppm-cover"><?php echo wp_get_attachment_image( absint( $data['cover_image'] ), 'large' ); ?></div>
		<?php endif; ?>
		<?php if ( ! empty( $data['main_image'] ) ) : ?>
			<div class="ksppm-portrait"><?php echo wp_get_attachment_image( absint( $data['main_image'] ), 'medium', false, array( 'class' => 'ksppm-portrait-img' ) ); ?></div>
		<?php endif; ?>
		<<?php echo esc_attr( $h_top ); ?> class="ksppm-fullname"><?php echo esc_html( $display_name ); ?>
			<?php if ( ! empty( $data['name_kana'] ) ) : ?><small class="ksppm-kana">（<?php echo esc_html( $data['name_kana'] ); ?>）</small><?php endif; ?>
		</<?php echo esc_attr( $h_top ); ?>>
		<?php if ( ! empty( $data['name_romaji'] ) ) : ?><p class="ksppm-romaji"><?php echo esc_html( $data['name_romaji'] ); ?></p><?php endif; ?>
		<?php if ( ! empty( $data['job_title'] ) ) : ?><p class="ksppm-jobtitle"><?php echo esc_html( $data['job_title'] ); ?></p><?php endif; ?>
		<?php if ( ! empty( $data['description'] ) ) : ?>
			<p class="ksppm-summary" data-llm-summary><?php echo esc_html( $data['description'] ); ?></p>
		<?php endif; ?>
	</header>
	<?php endif; ?>

	<?php // 目次 (.ksppm-toc) はユーザー指示で削除 (v0.0.19-beta、2026-05-25) — テーマ側の目次プラグインに任せる方針 ?>

	<?php if ( $show( 'basic' ) && ! empty( $data['biography'] ) ) : ?>
	<section class="ksppm-section ksppm-section-bio" id="ksppm-bio">
		<<?php echo esc_attr( $h_section ); ?>><?php esc_html_e( '来歴', 'kashiwazaki-seo-profilepage-maker' ); ?></<?php echo esc_attr( $h_section ); ?>>
		<div class="ksppm-bio"><?php echo do_shortcode( wp_kses_post( KSPPM_Frontend::normalize_html_fragment( $data['biography'] ) ) ); ?></div>
	</section>
	<?php endif; ?>

	<?php if ( $show( 'basic' ) ) : ?>
	<section class="ksppm-section ksppm-section-info" id="ksppm-info">
		<<?php echo esc_attr( $h_section ); ?>><?php esc_html_e( '基本情報', 'kashiwazaki-seo-profilepage-maker' ); ?></<?php echo esc_attr( $h_section ); ?>>
		<dl class="ksppm-dl">
			<?php if ( ! empty( $display_name ) ) : ?><dt><?php esc_html_e( '氏名', 'kashiwazaki-seo-profilepage-maker' ); ?></dt><dd><?php echo esc_html( $display_name ); ?></dd><?php endif; ?>
			<?php if ( ! empty( $data['name_kana'] ) ) : ?><dt><?php esc_html_e( 'ふりがな', 'kashiwazaki-seo-profilepage-maker' ); ?></dt><dd><?php echo esc_html( $data['name_kana'] ); ?></dd><?php endif; ?>
			<?php if ( ! empty( $data['nickname'] ) ) : ?><dt><?php esc_html_e( 'ニックネーム', 'kashiwazaki-seo-profilepage-maker' ); ?></dt><dd><?php echo esc_html( $data['nickname'] ); ?></dd><?php endif; ?>
			<?php if ( ! empty( $data['show_birth_date'] ) && ! empty( $data['birth_place'] ) ) : ?><dt><?php esc_html_e( '出生地', 'kashiwazaki-seo-profilepage-maker' ); ?></dt><dd><?php echo esc_html( $data['birth_place'] ); ?></dd><?php endif; ?>
			<?php if ( ! empty( $data['show_birth_date'] ) && ! empty( $data['birth_date'] ) ) : ?><dt><?php esc_html_e( '生年月日', 'kashiwazaki-seo-profilepage-maker' ); ?></dt><dd><?php echo esc_html( $data['birth_date'] ); ?></dd><?php endif; ?>
			<?php // (v0.0.18-beta): show_nationality で表示制御 (default 1=ON で後方互換) ?>
			<?php if ( ! empty( $data['nationality'] ) && ! empty( $data['show_nationality'] ) ) : ?><dt><?php esc_html_e( '国籍', 'kashiwazaki-seo-profilepage-maker' ); ?></dt><dd><?php echo esc_html( $data['nationality'] ); ?></dd><?php endif; ?>
			<?php if ( ! empty( $data['show_height_weight'] ) && ! empty( $data['height_cm'] ) ) : ?><dt><?php esc_html_e( '身長', 'kashiwazaki-seo-profilepage-maker' ); ?></dt><dd><?php echo esc_html( $data['height_cm'] ); ?> cm</dd><?php endif; ?>
			<?php if ( ! empty( $data['show_height_weight'] ) && ! empty( $data['weight_kg'] ) ) : ?><dt><?php esc_html_e( '体重', 'kashiwazaki-seo-profilepage-maker' ); ?></dt><dd><?php echo esc_html( $data['weight_kg'] ); ?> kg</dd><?php endif; ?>
			<?php if ( ! empty( $data['job_title'] ) ) : ?><dt><?php esc_html_e( '現職', 'kashiwazaki-seo-profilepage-maker' ); ?></dt><dd><?php echo esc_html( $data['job_title'] ); ?></dd><?php endif; ?>
			<?php if ( ! empty( $data['show_email'] ) && ! empty( $data['email'] ) ) : ?><dt><?php esc_html_e( 'メール', 'kashiwazaki-seo-profilepage-maker' ); ?></dt><dd><a href="<?php echo esc_url( 'mailto:' . $data['email'] ); ?>"><?php echo esc_html( $data['email'] ); ?></a></dd><?php endif; ?>
			<?php if ( ! empty( $data['show_telephone'] ) && ! empty( $data['telephone'] ) ) : ?><dt><?php esc_html_e( '電話', 'kashiwazaki-seo-profilepage-maker' ); ?></dt><dd><?php echo esc_html( $data['telephone'] ); ?></dd><?php endif; ?>
		</dl>
	</section>
	<?php endif; ?>

	<?php if ( $show( 'contact' ) && ! empty( $data['same_as'] ) ) : ?>
	<section class="ksppm-section ksppm-section-sns" id="ksppm-sns">
		<<?php echo esc_attr( $h_section ); ?>><?php esc_html_e( 'SNS / 外部プロフィール', 'kashiwazaki-seo-profilepage-maker' ); ?></<?php echo esc_attr( $h_section ); ?>>
		<?php
		// v0.0.25-beta: SNS は dashicons アイコンのみ表示、label 表示は設定で opt-in
		// v0.0.26-beta: ul/li を div + a 直接に変更 (シンプル化)
		$_ksppm_sns_show_label = ! empty( get_option( KSPPM_OPTION_KEY, array() )['sns_show_label'] );
		?>
		<div class="ksppm-sns-list<?php echo $_ksppm_sns_show_label ? ' ksppm-sns-list--with-label' : ' ksppm-sns-list--icon-only'; ?>">
			<?php foreach ( $data['same_as'] as $row ) : if ( empty( $row['url'] ) ) continue;
				$icon_class = KSPPM_Frontend::sns_icon_class( $row['url'] );
				$label_text = ! empty( $row['label'] ) ? $row['label'] : $row['url']; // F15: label キー欠落レガシーデータの PHP8 警告回避
				?>
				<a href="<?php echo esc_url( $row['url'] ); ?>" rel="me noopener" target="_blank" class="ksppm-sns-link" title="<?php echo esc_attr( $label_text ); ?>" aria-label="<?php echo esc_attr( $label_text ); ?>">
					<span class="dashicons <?php echo esc_attr( $icon_class ); ?>" aria-hidden="true"></span>
					<?php if ( $_ksppm_sns_show_label ) : ?>
						<span class="ksppm-sns-label"><?php echo esc_html( $label_text ); ?></span>
					<?php endif; ?>
				</a>
			<?php endforeach; ?>
		</div>
	</section>
	<?php endif; ?>

	<?php
	// 経歴・実績系セクション (共通レンダー)
	$render_repeater = function ( $title, $id, $items, $columns ) use ( $h_section ) {
		if ( empty( $items ) ) return;
		// (v0.0.22-beta): url 列除外 + 名称セル anchor 化
		// v0.0.24-beta: 全行で空の列 (image 等) は表ごと非表示 (ユーザー指示: 設定されてない場合は項目を出さない)
		$display_columns = array_values( array_filter( $columns, function ( $c ) use ( $items ) {
			if ( $c['key'] === 'url' ) return false; // url は常に anchor に変換 (表示列ではない)
			foreach ( $items as $row ) {
				if ( ! empty( $row[ $c['key'] ] ) ) return true; // 1 行でも値あれば列表示
			}
			return false; // 全行空 → 列ごと非表示
		} ) );
		// anchor_key は image を除く最初の text 列
		$anchor_candidates = array_values( array_filter( $display_columns, function ( $c ) { return $c['key'] !== 'image'; } ) );
		$anchor_key = ! empty( $anchor_candidates ) ? $anchor_candidates[0]['key'] : 'name';
		?>
		<section class="ksppm-section" id="<?php echo esc_attr( $id ); ?>">
			<<?php echo esc_attr( $h_section ); ?>><?php echo esc_html( $title ); ?></<?php echo esc_attr( $h_section ); ?>>
			<table class="ksppm-table">
				<thead><tr><?php foreach ( $display_columns as $c ) : ?><th><?php echo esc_html( $c['label'] ); ?></th><?php endforeach; ?></tr></thead>
				<tbody>
				<?php foreach ( $items as $row ) : $row_url = $row['url'] ?? ''; ?>
					<tr>
						<?php foreach ( $display_columns as $c ) : $v = $row[ $c['key'] ] ?? ''; ?>
							<td>
								<?php if ( $c['key'] === 'image' && $v ) : ?>
									<?php echo wp_get_attachment_image( absint( $v ), 'thumbnail' ); ?>
								<?php elseif ( $c['key'] === $anchor_key && $row_url !== '' ) : ?>
									<?php
									// name 空 + url あり の場合は URL ホスト名を fallback anchor text に
									$anchor_text = $v !== '' ? $v : ( wp_parse_url( $row_url, PHP_URL_HOST ) ?: $row_url );
									?>
									<a href="<?php echo esc_url( $row_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $anchor_text ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $v ); ?>
								<?php endif; ?>
							</td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</section>
		<?php
	};

	if ( $show( 'career' ) ) :
		// 在任期間: 開始日と終了日を 1 列にまとめて出す (現職で終了日が空の行は「開始日 ～ 現在」)。
		$with_period = function ( $rows, $is_current ) {
			if ( empty( $rows ) || ! is_array( $rows ) ) return array();
			foreach ( $rows as $i => $row ) {
				if ( is_array( $row ) ) {
					$rows[ $i ]['period'] = KSPPM_Frontend::format_period( $row['start_date'] ?? '', $row['end_date'] ?? '', $is_current );
				}
			}
			return $rows;
		};
		$render_repeater( __( '現職・所属', 'kashiwazaki-seo-profilepage-maker' ), 'ksppm-career', $with_period( $data['works_for'] ?? array(), true ), array(
			array( 'key' => 'name', 'label' => __( '組織名', 'kashiwazaki-seo-profilepage-maker' ) ),
			array( 'key' => 'role', 'label' => __( '役職', 'kashiwazaki-seo-profilepage-maker' ) ),
			array( 'key' => 'period', 'label' => __( '在任期間', 'kashiwazaki-seo-profilepage-maker' ) ),
			array( 'key' => 'url', 'label' => __( 'URL', 'kashiwazaki-seo-profilepage-maker' ) ),
		) );
		$render_repeater( __( '以前の経歴', 'kashiwazaki-seo-profilepage-maker' ), 'ksppm-past', $with_period( $data['past_career'] ?? array(), false ), array(
			array( 'key' => 'name', 'label' => __( '組織名', 'kashiwazaki-seo-profilepage-maker' ) ),
			array( 'key' => 'role', 'label' => __( '役職', 'kashiwazaki-seo-profilepage-maker' ) ),
			array( 'key' => 'period', 'label' => __( '在任期間', 'kashiwazaki-seo-profilepage-maker' ) ),
		) );
		$render_repeater( __( '学歴', 'kashiwazaki-seo-profilepage-maker' ), 'ksppm-alumni', $data['alumni_of'] ?? array(), array(
			array( 'key' => 'name', 'label' => __( '学校名', 'kashiwazaki-seo-profilepage-maker' ) ),
			array( 'key' => 'degree', 'label' => __( '学位', 'kashiwazaki-seo-profilepage-maker' ) ),
			array( 'key' => 'graduated_at', 'label' => __( '卒業', 'kashiwazaki-seo-profilepage-maker' ) ),
			array( 'key' => 'url', 'label' => __( 'URL', 'kashiwazaki-seo-profilepage-maker' ) ),
		) );
		$render_repeater( __( '資格・認定', 'kashiwazaki-seo-profilepage-maker' ), 'ksppm-cred', $data['credentials'] ?? array(), array(
			array( 'key' => 'name', 'label' => __( '資格名', 'kashiwazaki-seo-profilepage-maker' ) ),
			array( 'key' => 'issuer', 'label' => __( '発行機関', 'kashiwazaki-seo-profilepage-maker' ) ),
			array( 'key' => 'issued_at', 'label' => __( '取得', 'kashiwazaki-seo-profilepage-maker' ) ),
			array( 'key' => 'expires_at', 'label' => __( '有効期限', 'kashiwazaki-seo-profilepage-maker' ) ),
		) );
		$render_repeater( __( '受賞・表彰', 'kashiwazaki-seo-profilepage-maker' ), 'ksppm-awards', $data['awards'] ?? array(), array(
			array( 'key' => 'name', 'label' => __( '賞名', 'kashiwazaki-seo-profilepage-maker' ) ),
			array( 'key' => 'issuer', 'label' => __( '授与機関', 'kashiwazaki-seo-profilepage-maker' ) ),
			array( 'key' => 'date', 'label' => __( '受賞日', 'kashiwazaki-seo-profilepage-maker' ) ),
		) );
	endif;

	if ( $show( 'expertise' ) ) :
		$has_knows_about = ! empty( $data['knows_about'] );
		$has_knows_language = ! empty( $data['knows_language'] );
		// (v0.0.21-beta): integrate_skills_into_hobbies=ON 時は専門性セクションでの skills 重複表示を抑止
		$_ksppm_integrate = ! empty( get_option( KSPPM_OPTION_KEY, array() )['integrate_skills_into_hobbies'] );
		$has_skills = ! empty( $data['skills'] ) && ! $_ksppm_integrate;
		if ( $has_knows_about || $has_knows_language || $has_skills ) :
		?>
		<section class="ksppm-section ksppm-section-expertise" id="ksppm-expertise">
			<<?php echo esc_attr( $h_section ); ?>><?php esc_html_e( '専門性・知識', 'kashiwazaki-seo-profilepage-maker' ); ?></<?php echo esc_attr( $h_section ); ?>>

			<?php if ( $has_knows_about ) : ?>
				<div class="ksppm-expertise-block">
					<<?php echo esc_attr( $h_subsection ); ?>><?php esc_html_e( '専門分野', 'kashiwazaki-seo-profilepage-maker' ); ?></<?php echo esc_attr( $h_subsection ); ?>>
					<ul class="ksppm-knows-about">
						<?php foreach ( $data['knows_about'] as $k ) : ?>
							<li>
								<?php if ( ! empty( $k['url'] ) ) : ?>
									<a href="<?php echo esc_url( $k['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $k['topic'] ?? '' ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $k['topic'] ?? '' ); ?>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( $has_knows_language ) : ?>
				<div class="ksppm-expertise-block">
					<<?php echo esc_attr( $h_subsection ); ?>><?php esc_html_e( '対応言語', 'kashiwazaki-seo-profilepage-maker' ); ?></<?php echo esc_attr( $h_subsection ); ?>>
					<ul class="ksppm-knows-language">
						<?php foreach ( $data['knows_language'] as $k ) : ?>
							<li>
								<?php echo esc_html( $k['name'] ?? '' ); ?>
								<?php if ( ! empty( $k['code'] ) ) : ?>
									<code>(<?php echo esc_html( $k['code'] ); ?>)</code>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( $has_skills ) : ?>
				<div class="ksppm-expertise-block">
					<<?php echo esc_attr( $h_subsection ); ?>><?php esc_html_e( 'スキル', 'kashiwazaki-seo-profilepage-maker' ); ?></<?php echo esc_attr( $h_subsection ); ?>>
					<ul class="ksppm-skills">
						<?php foreach ( $data['skills'] as $s ) : ?>
							<li>
								<strong><?php echo esc_html( $s['name'] ?? '' ); ?></strong>
								<?php if ( ! empty( $s['level'] ) ) : ?>
									<span class="ksppm-skill-level"> — <?php echo esc_html( $s['level'] ); ?></span>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
		</section>
		<?php endif;
	endif;

	if ( $show( 'works' ) || $section === 'books' ) :
		// 書籍カードグリッド
		if ( ! empty( $data['books'] ) ) :
		?>
		<section class="ksppm-section ksppm-section-books" id="ksppm-books">
			<<?php echo esc_attr( $h_section ); ?>><?php esc_html_e( '書籍', 'kashiwazaki-seo-profilepage-maker' ); ?></<?php echo esc_attr( $h_section ); ?>>
			<div class="ksppm-book-grid">
				<?php foreach ( $data['books'] as $b ) : ?>
					<div class="ksppm-book-card">
						<?php if ( ! empty( $b['image'] ) ) : ?>
							<div class="ksppm-book-cover"><?php echo wp_get_attachment_image( absint( $b['image'] ), 'medium' ); ?></div>
						<?php endif; ?>
						<<?php echo esc_attr( $h_subsection ); ?> class="ksppm-book-title"><?php echo esc_html( $b['name'] ); ?></<?php echo esc_attr( $h_subsection ); ?>>
						<?php if ( ! empty( $b['publisher'] ) ) : ?><p class="ksppm-book-pub"><?php echo esc_html( $b['publisher'] ); ?></p><?php endif; ?>
						<?php if ( ! empty( $b['date'] ) ) : ?><p class="ksppm-book-date"><?php echo esc_html( $b['date'] ); ?></p><?php endif; ?>
						<?php if ( ! empty( $b['isbn'] ) ) : ?><p class="ksppm-book-isbn">ISBN: <?php echo esc_html( $b['isbn'] ); ?></p><?php endif; ?>
						<?php if ( ! empty( $b['url'] ) ) : ?><p><a href="<?php echo esc_url( $b['url'] ); ?>" target="_blank" rel="noopener" class="ksppm-btn"><?php esc_html_e( '詳細', 'kashiwazaki-seo-profilepage-maker' ); ?></a></p><?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
		<?php endif;
	endif;

	if ( $show( 'works' ) ) :
		$render_repeater( __( '学術論文', 'kashiwazaki-seo-profilepage-maker' ), 'ksppm-articles', $data['articles'] ?? array(), array(
			array( 'key' => 'image', 'label' => __( '論文サムネイル', 'kashiwazaki-seo-profilepage-maker' ) ),
			array( 'key' => 'name', 'label' => __( 'タイトル', 'kashiwazaki-seo-profilepage-maker' ) ),
			array( 'key' => 'publisher', 'label' => __( '出版誌', 'kashiwazaki-seo-profilepage-maker' ) ),
			array( 'key' => 'doi', 'label' => __( 'DOI', 'kashiwazaki-seo-profilepage-maker' ) ),
			array( 'key' => 'date', 'label' => __( '発行日', 'kashiwazaki-seo-profilepage-maker' ) ),
			array( 'key' => 'url', 'label' => __( 'URL', 'kashiwazaki-seo-profilepage-maker' ) ),
		) );
		$render_repeater( __( '特許', 'kashiwazaki-seo-profilepage-maker' ), 'ksppm-patents', $data['patents'] ?? array(), array(
			array( 'key' => 'image', 'label' => __( '特許図面', 'kashiwazaki-seo-profilepage-maker' ) ),
			array( 'key' => 'name', 'label' => __( '特許名', 'kashiwazaki-seo-profilepage-maker' ) ),
			array( 'key' => 'patent_no', 'label' => __( '特許番号', 'kashiwazaki-seo-profilepage-maker' ) ),
			array( 'key' => 'date', 'label' => __( '日付', 'kashiwazaki-seo-profilepage-maker' ) ),
			array( 'key' => 'url', 'label' => __( 'URL', 'kashiwazaki-seo-profilepage-maker' ) ),
		) );
		$render_repeater( __( '商標登録', 'kashiwazaki-seo-profilepage-maker' ), 'ksppm-trademarks', $data['trademarks'] ?? array(), array(
			array( 'key' => 'name', 'label' => __( '商標名', 'kashiwazaki-seo-profilepage-maker' ) ),
			array( 'key' => 'reg_no', 'label' => __( '登録番号', 'kashiwazaki-seo-profilepage-maker' ) ),
			array( 'key' => 'classes', 'label' => __( '区分', 'kashiwazaki-seo-profilepage-maker' ) ),
		) );
		$render_repeater( __( '取材・執筆・監修', 'kashiwazaki-seo-profilepage-maker' ), 'ksppm-media', $data['media_mentions'] ?? array(), array(
			// v0.0.23-beta: publisher は法人名 = メディア運営会社
			array( 'key' => 'image', 'label' => __( 'サムネイル', 'kashiwazaki-seo-profilepage-maker' ) ),
			array( 'key' => 'title', 'label' => __( 'タイトル / メディア名', 'kashiwazaki-seo-profilepage-maker' ) ),
			array( 'key' => 'publisher', 'label' => __( '発行元 (法人名)', 'kashiwazaki-seo-profilepage-maker' ) ),
			array( 'key' => 'author', 'label' => __( '著者 / 執筆者', 'kashiwazaki-seo-profilepage-maker' ) ),
			array( 'key' => 'type', 'label' => __( '種別', 'kashiwazaki-seo-profilepage-maker' ) ),
			array( 'key' => 'date', 'label' => __( '掲載日', 'kashiwazaki-seo-profilepage-maker' ) ),
			array( 'key' => 'url', 'label' => __( 'URL', 'kashiwazaki-seo-profilepage-maker' ) ),
		) );
		$render_repeater( __( '講演・セミナー', 'kashiwazaki-seo-profilepage-maker' ), 'ksppm-speeches', $data['speeches'] ?? array(), array(
			array( 'key' => 'image', 'label' => __( 'イベント画像', 'kashiwazaki-seo-profilepage-maker' ) ),
			array( 'key' => 'name', 'label' => __( 'イベント名', 'kashiwazaki-seo-profilepage-maker' ) ),
			array( 'key' => 'organizer', 'label' => __( '主催', 'kashiwazaki-seo-profilepage-maker' ) ),
			array( 'key' => 'date', 'label' => __( '開催日', 'kashiwazaki-seo-profilepage-maker' ) ),
			array( 'key' => 'location', 'label' => __( '会場', 'kashiwazaki-seo-profilepage-maker' ) ),
		) );
	endif;

	if ( $show( 'tools' ) ) :
		// (custom blocks): 任意名称ブロックを描画。移行ブロックは旧 anchor id (ksppm-tools /
		// ksppm-partner-tools) を保持するため既存リンク/目次互換。新規ブロックは ksppm-block-<uid>。
		$ksppm_blocks = $data['custom_blocks'] ?? array();
		if ( is_array( $ksppm_blocks ) ) :
			foreach ( $ksppm_blocks as $block ) :
				if ( ! is_array( $block ) ) continue;
				$b_items = ( isset( $block['items'] ) && is_array( $block['items'] ) ) ? $block['items'] : array();
				if ( empty( $b_items ) ) continue;
				$b_id    = ( isset( $block['id'] ) && $block['id'] !== '' ) ? (string) $block['id'] : '';
				$b_title = isset( $block['title'] ) ? (string) $block['title'] : '';
				?>
				<section class="ksppm-section ksppm-section-block"<?php echo $b_id !== '' ? ' id="' . esc_attr( $b_id ) . '"' : ''; ?>>
					<?php if ( $b_title !== '' ) : ?>
						<<?php echo esc_attr( $h_section ); ?>><?php echo esc_html( $b_title ); ?></<?php echo esc_attr( $h_section ); ?>>
					<?php endif; ?>
					<div class="ksppm-block-cards">
						<?php foreach ( $b_items as $it ) :
							if ( ! is_array( $it ) ) continue;
							$bi_heading = isset( $it['heading'] ) ? (string) $it['heading'] : '';
							$bi_desc    = isset( $it['description'] ) ? (string) $it['description'] : '';
							$bi_url     = isset( $it['url'] ) ? (string) $it['url'] : '';
							$bi_img     = isset( $it['image'] ) ? absint( $it['image'] ) : 0;
							if ( $bi_heading === '' && $bi_desc === '' && $bi_url === '' && $bi_img === 0 ) continue;
							?>
							<div class="ksppm-block-card">
								<?php if ( $bi_img ) : ?>
									<div class="ksppm-block-card-img"><?php echo wp_get_attachment_image( $bi_img, 'medium' ); ?></div>
								<?php endif; ?>
								<div class="ksppm-block-card-body">
									<?php if ( $bi_heading !== '' ) : ?>
										<p class="ksppm-block-card-heading"><?php if ( $bi_url !== '' ) : ?><a href="<?php echo esc_url( $bi_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $bi_heading ); ?></a><?php else : echo esc_html( $bi_heading ); endif; ?></p>
									<?php elseif ( $bi_url !== '' ) : ?>
										<p class="ksppm-block-card-heading"><a href="<?php echo esc_url( $bi_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( wp_parse_url( $bi_url, PHP_URL_HOST ) ?: $bi_url ); ?></a></p>
									<?php endif; ?>
									<?php if ( $bi_desc !== '' ) : ?>
										<p class="ksppm-block-card-desc"><?php echo nl2br( esc_html( $bi_desc ) ); ?></p>
									<?php endif; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</section>
				<?php
			endforeach;
		endif;
	endif;

	if ( $show( 'contact' ) && ( ! empty( $data['contact_text'] ) || ! empty( $data['contact_form_url'] ) ) ) : ?>
		<section class="ksppm-section ksppm-section-contact" id="ksppm-contact">
			<<?php echo esc_attr( $h_section ); ?>><?php esc_html_e( 'お問い合わせ', 'kashiwazaki-seo-profilepage-maker' ); ?></<?php echo esc_attr( $h_section ); ?>>
			<?php if ( ! empty( $data['contact_text'] ) ) : ?>
				<?php
				$contact_text = $data['contact_text'];
				// (v0.0.15-beta): filter 化で多言語対応 + 任意化
				$contact_form_anchor = apply_filters( 'ksppm_contact_form_link_anchor', __( 'フォーム', 'kashiwazaki-seo-profilepage-maker' ) );
				if ( ! empty( $data['contact_form_url'] ) && ! empty( $contact_form_anchor ) && false === strpos( $contact_text, 'href' ) && false !== strpos( $contact_text, $contact_form_anchor ) ) {
					$contact_text = str_replace(
						$contact_form_anchor,
						'<a href="' . esc_url( $data['contact_form_url'] ) . '">' . esc_html( $contact_form_anchor ) . '</a>',
						$contact_text
					);
				}
				?>
				<p><?php echo wp_kses_post( $contact_text ); ?></p>
			<?php elseif ( ! empty( $data['contact_form_url'] ) ) : ?>
				<p><a href="<?php echo esc_url( $data['contact_form_url'] ); ?>"><?php esc_html_e( 'お問い合わせフォーム', 'kashiwazaki-seo-profilepage-maker' ); ?></a></p>
			<?php endif; ?>
		</section>
	<?php endif;

	if ( $show( 'media' ) ) :
		if ( ! empty( $data['video_channels'] ) ) : ?>
			<section class="ksppm-section" id="ksppm-videos">
				<<?php echo esc_attr( $h_section ); ?>><?php esc_html_e( '動画配信', 'kashiwazaki-seo-profilepage-maker' ); ?></<?php echo esc_attr( $h_section ); ?>>
				<ul class="ksppm-video-list">
					<?php foreach ( $data['video_channels'] as $v ) :
						if ( empty( $v['url'] ) ) continue;
						// (v0.0.21-beta): name 優先表示 + platform 補助 + URL は label 化 (T20)
						$ch_name  = isset( $v['name'] ) && $v['name'] !== '' ? $v['name'] : '';
						$platform = isset( $v['platform'] ) && $v['platform'] !== '' ? $v['platform'] : '';
						$ch_display_name = $ch_name !== '' ? $ch_name : ( $platform !== '' ? $platform : __( '動画チャンネル', 'kashiwazaki-seo-profilepage-maker' ) ); // F16: 人物 $display_name の shadow 回避
						$suffix       = ( $ch_name !== '' && $platform !== '' ) ? ' (' . $platform . ')' : '';
						?>
						<li>
							<a href="<?php echo esc_url( $v['url'] ); ?>" target="_blank" rel="noopener" class="ksppm-video-link"><?php echo esc_html( $ch_display_name ); ?></a><?php if ( $suffix !== '' ) : ?><span class="ksppm-video-platform"><?php echo esc_html( $suffix ); ?></span><?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif;
	endif;
	?>

	<footer class="ksppm-meta-footer">
		<?php
		// (v0.0.21-beta): 趣味・特技 セクションに skills を統合表示
		// opt-in 設定: ksppm_settings['integrate_skills_into_hobbies'] (default 0、既存配布完全互換)
		$_ksppm_settings = get_option( KSPPM_OPTION_KEY, array() );
		$integrate = ! empty( $_ksppm_settings['integrate_skills_into_hobbies'] );
		$has_hobbies = ! empty( $data['hobbies'] );
		$has_skills_for_hobbies = $integrate && ! empty( $data['skills'] );
		// F9: ショートコードで section を限定 (例 section="books") した部分表示では footer の
		// 趣味・特技 / 最終更新 を出さない (全体表示 section='all' のときのみ)。
		if ( $section === 'all' && ( $has_hobbies || $has_skills_for_hobbies ) ) : ?>
			<section class="ksppm-section ksppm-section-hobbies" id="ksppm-hobbies">
				<<?php echo esc_attr( $h_section ); ?>><?php esc_html_e( '趣味・特技', 'kashiwazaki-seo-profilepage-maker' ); ?></<?php echo esc_attr( $h_section ); ?>>
				<?php if ( $has_skills_for_hobbies ) : ?>
					<ul class="ksppm-hobbies-skills">
						<?php foreach ( $data['skills'] as $s ) : ?>
							<li><?php echo esc_html( is_array( $s ) ? ( $s['name'] ?? '' ) : (string) $s ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php if ( $has_hobbies ) : ?>
					<p class="ksppm-hobbies-text"><?php echo KSPPM_Frontend::format_text_with_links( $data['hobbies'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 関数内で esc_html / esc_url 済み ?></p>
				<?php endif; ?>
			</section>
		<?php endif; ?>
		<?php if ( $section === 'all' ) : ?>
			<p class="ksppm-updated"><?php printf( esc_html__( '最終更新: %s', 'kashiwazaki-seo-profilepage-maker' ), esc_html( get_the_modified_date( '', $post_id ) ) ); ?></p>
		<?php endif; ?>
	</footer>
</div>
