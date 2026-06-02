<?php
/**
 * Single template for ksppm_profile CPT (FRO-13)
 *
 * F09: 【重要】このファイルはプラグインからは読み込まれない参照用サンプルです。
 *   実際のフロント表示は the_content フィルタ経由の inject_content が担当します
 *   (KSPPM_Frontend)。WordPress の single_template フィルタは locate_template() で
 *   テーマディレクトリのみを探索し、プラグインの templates/ は検索対象外のため、
 *   本ファイルが直接ロードされることはありません (dead file)。
 *   テーマ側で single-ksppm_profile.php を自作する際の雛形としてのみ利用してください
 *   (テーマに置けば WordPress の template_hierarchy で有効化されます)。
 */
if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

while ( have_posts() ) :
	the_post();
	$post_id = get_the_ID();
	?>
	<article class="ksppm-article" id="post-<?php echo esc_attr( $post_id ); ?>">
		<?php echo KSPPM_Frontend::render( $post_id, array( 'section' => 'all' ) ); ?>
	</article>
	<?php
endwhile;

get_footer();
