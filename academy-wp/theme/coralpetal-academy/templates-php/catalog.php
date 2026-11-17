<?php
/**
 * Course catalog (/courses/ and /course-category/<slug>/): courses grouped by section
 * (Tutor course categories, ordered by term meta cp_order). Rohit 2026-11-18.
 */
defined( 'ABSPATH' ) || exit;

$cp_sections = get_terms( array(
	'taxonomy'   => 'course-category',
	'hide_empty' => true,
	'meta_key'   => 'cp_order',
	'orderby'    => 'meta_value_num',
	'order'      => 'ASC',
) );
if ( is_wp_error( $cp_sections ) ) {
	$cp_sections = array();
}
$cp_current = is_tax( 'course-category' ) ? get_queried_object() : null;
if ( $cp_current ) {
	$cp_sections = array( $cp_current );
}
$cp_tints = array(
	'statistics'       => 'amber',
	'machine-learning' => 'blue',
	'deep-learning'    => 'violet',
	'generative-ai'    => 'coral',
	'agentic-ai'       => 'green',
	'cloud-computing'  => 'teal',
);
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'cp-catalog' ); ?>>
<?php wp_body_open(); ?>
<div class="wp-site-blocks">
<?php block_template_part( 'header' ); ?>
<main class="cp-cat">
	<section class="cp-cat-hero">
		<p class="cp-chip"><?php echo $cp_current ? 'Course catalog' : 'Coral Petal Academy'; ?></p>
		<h1><?php echo $cp_current ? esc_html( $cp_current->name ) : 'Course catalog'; ?></h1>
		<p class="cp-lede"><?php echo $cp_current ? 'Courses in this section.' : 'Six sections, from statistics to agents in production. Start anywhere; the Signature Course brings it all together.'; ?></p>
		<?php if ( ! $cp_current && $cp_sections ) : ?>
		<nav class="cp-cat-jump" aria-label="Sections">
			<?php foreach ( $cp_sections as $s ) : ?>
				<a href="#<?php echo esc_attr( $s->slug ); ?>"><i class="dot-<?php echo esc_attr( $cp_tints[ $s->slug ] ?? 'coral' ); ?>"></i><?php echo esc_html( $s->name ); ?></a>
			<?php endforeach; ?>
		</nav>
		<?php endif; ?>
	</section>

	<?php foreach ( $cp_sections as $s ) :
		$q = new WP_Query( array(
			'post_type'      => 'courses',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
			'tax_query'      => array( array( 'taxonomy' => 'course-category', 'field' => 'term_id', 'terms' => $s->term_id ) ),
		) );
		if ( ! $q->have_posts() ) { continue; }
		$tint = $cp_tints[ $s->slug ] ?? 'coral';
		?>
	<section class="cp-cat-sec" id="<?php echo esc_attr( $s->slug ); ?>">
		<div class="cp-cat-head">
			<span class="cp-ico is-<?php echo esc_attr( $tint ); ?>"><i class="dot-<?php echo esc_attr( $tint ); ?>"></i></span>
			<h2><?php echo esc_html( $s->name ); ?></h2>
			<small><?php echo (int) $q->found_posts; ?> <?php echo 1 === (int) $q->found_posts ? 'course' : 'courses'; ?></small>
		</div>
		<div class="cp-cat-grid">
		<?php while ( $q->have_posts() ) : $q->the_post();
			$sig = ( 'gen-agentic-ai' === get_post_field( 'post_name' ) ); ?>
			<a class="cp-cat-card<?php echo $sig ? ' is-sig' : ''; ?>" href="<?php the_permalink(); ?>">
				<span class="cp-cat-thumb"><?php if ( has_post_thumbnail() ) { the_post_thumbnail( 'medium_large', array( 'alt' => '' ) ); } ?></span>
				<span class="cp-cat-body">
					<span class="cp-cat-tags">
						<?php if ( $sig ) : ?><span class="pill-s p-coral">Signature Course</span><?php else : ?><span class="pill-s p-grey">Fundamentals</span><?php endif; ?>
					</span>
					<b><?php the_title(); ?></b>
					<span class="cp-cat-ex"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22 ) ); ?></span>
					<span class="cp-go">View course →</span>
				</span>
			</a>
		<?php endwhile; wp_reset_postdata(); ?>
		</div>
	</section>
	<?php endforeach; ?>
</main>
<?php block_template_part( 'footer' ); ?>
</div>
<?php wp_footer(); ?>
</body>
</html>
