<?php
/**
 * Coral Petal Academy child theme.
 */
add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'cp-inter', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Newsreader:opsz,wght@6..72,400;6..72,500&display=swap', array(), null );
	wp_enqueue_style( 'coralpetal-academy', get_stylesheet_uri(), array(), wp_get_theme()->get( 'Version' ) );
}, 20 );

add_action( 'wp_head', function () {
	echo '<meta name="theme-color" content="#FBFBFB">' . "\n";
	echo '<link rel="icon" href="' . esc_url( get_stylesheet_directory_uri() . '/assets/logo.svg' ) . '" type="image/svg+xml">' . "\n";
} );

// Light reading surface on Tutor LMS pages (see style.css).
add_filter( 'body_class', function ( $classes ) {
	if ( is_singular( 'courses' ) || is_post_type_archive( 'courses' ) || is_page( array( 'dashboard', 'student-registration' ) ) ) {
		$classes[] = 'cp-light';
	}
	return $classes;
} );

// Course catalog grouped by section (Tutor course categories) on /courses/ and section archives.
add_filter( 'template_include', function ( $template ) {
	if ( is_post_type_archive( 'courses' ) || is_tax( 'course-category' ) ) {
		$t = get_stylesheet_directory() . '/templates-php/catalog.php';
		if ( file_exists( $t ) ) {
			return $t;
		}
	}
	return $template;
}, 99 );

// Course pages: "Course Content" and "Your learning path" collapse into expand cards (Rohit 2026-11-18).
add_action( 'wp_footer', function () {
	if ( ! is_singular( 'courses' ) ) {
		return;
	}
	?>
<script>
(function () {
	var ICONS = {
		list: '<svg viewBox="0 0 24 24"><path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4.5" cy="6" r="1.5"/><circle cx="4.5" cy="12" r="1.5"/><circle cx="4.5" cy="18" r="1.5"/></svg>',
		path: '<svg viewBox="0 0 24 24"><circle cx="5" cy="18" r="2"/><circle cx="19" cy="6" r="2"/><path d="M7 18h6a4 4 0 0 0 0-8h-2a4 4 0 0 1 0-8h6"/></svg>'
	};
	function makeToggle(title, sub, icon, body, replace) {
		var b = document.createElement('button');
		b.type = 'button';
		b.className = 'cp-cc-toggle';
		b.setAttribute('aria-expanded', 'false');
		b.innerHTML = '<span class="cp-cc-ico">' + ICONS[icon] + '</span><span class="cp-cc-txt"><b>' + title + '</b><small>' + sub + '</small></span><span class="cp-cc-act"><span>Expand</span></span>';
		replace.replaceWith(b);
		body.classList.add('cp-cc-body');
		body.hidden = true;
		b.addEventListener('click', function () {
			var open = b.getAttribute('aria-expanded') === 'true';
			b.setAttribute('aria-expanded', open ? 'false' : 'true');
			b.querySelector('.cp-cc-act span').textContent = open ? 'Expand' : 'Collapse';
			body.hidden = open;
		});
	}
	var t = document.querySelector('.tutor-course-content-title');
	var acc = t && t.parentNode.querySelector('.tutor-accordion');
	if (t && acc) {
		var n = acc.querySelectorAll('.tutor-accordion-item').length;
		makeToggle('Course Content', n + ' modules · see every lesson', 'list', acc, t);
	}
	document.querySelectorAll('.cpc-path').forEach(function (ol) {
		var h = ol.previousElementSibling;
		if (!h || !h.classList.contains('cpc-h2')) { return; }
		var k = ol.querySelectorAll('li').length;
		makeToggle(h.textContent, k + ' steps · what you learn and build in each', 'path', ol, h);
	});
})();
</script>
	<?php
} );

// Log in and sign-up pages: brand panel beside the Tutor form (Rohit 2026-11-18).
function cp_auth_side( $is_reg ) {
	$logo = esc_url( get_stylesheet_directory_uri() . '/assets/logo.svg' );
	if ( $is_reg ) {
		$kick = 'Join the Academy';
		$h    = 'Registration to Coral Petal AI<sup class="tm">™</sup> Academy';
		$p    = 'Create your free account to enrol in courses, track your progress and earn certificates.';
		$li   = array( 'Fundamentals to the Signature Course', 'Hands-on builds in every module', 'Live classes and bootcamps', 'We’ll email you a link to verify your address' );
	} else {
		$kick = 'Welcome back';
		$h    = 'Log in to Coral Petal AI<sup class="tm">™</sup> Academy';
		$p    = 'Pick up where you left off: your courses, progress and certificates are waiting.';
		$li   = array( 'Continue your courses', 'See your progress', 'Download certificates' );
	}
	$items = '';
	foreach ( $li as $x ) {
		$items .= '<li>' . $x . '</li>';
	}
	return '<aside class="cp-auth-side"><img src="' . $logo . '" alt="" width="56" height="48"><p class="cp-chip cp-chip-coral">' . $kick . '</p><h1>' . $h . '</h1><p class="cp-auth-p">' . $p . '</p><ul class="cp-auth-list">' . $items . '</ul></aside>';
}
function cp_is_login_screen() {
	return is_page( 'dashboard' ) && ! is_user_logged_in();
}
add_filter( 'the_content', function ( $content ) {
	if ( ! is_page( 'student-registration' ) ) {
		return $content;
	}
	return '<div class="cp-auth">' . cp_auth_side( true ) . '<div class="cp-auth-form">' . $content . '</div></div>';
}, 20 );
// Tutor renders its log-in screen outside the_content: wrap it in the browser.
add_action( 'wp_footer', function () {
	if ( ! cp_is_login_screen() ) {
		return;
	}
	$notice = '';
	if ( isset( $_GET['cp_check_email'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$notice = '<div class="cp-notice"><b>Check your inbox.</b> We sent you a link to verify your email. Click it, then log in here.</div>';
	} elseif ( isset( $_GET['cp_verified'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$notice = '<div class="cp-notice ok"><b>Email verified.</b> You can log in now.</div>';
	} elseif ( isset( $_GET['cp_verify_failed'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$notice = '<div class="cp-notice bad"><b>That link didn’t work.</b> It may have been used already. Try logging in, or sign up again.</div>';
	}
	?>
<template id="cp-auth-side"><?php echo cp_auth_side( false ); // phpcs:ignore ?></template>
<template id="cp-auth-notice"><?php echo $notice; // phpcs:ignore ?></template>
<script>
(function () {
	var form = document.querySelector('.tutor-login-wrap') || document.querySelector('.tutor-login-form-wrapper');
	if (!form) { return; }
	var wrap = document.createElement('div'); wrap.className = 'cp-auth';
	var side = document.getElementById('cp-auth-side').content.cloneNode(true);
	var right = document.createElement('div'); right.className = 'cp-auth-form';
	form.parentNode.insertBefore(wrap, form);
	wrap.appendChild(side); wrap.appendChild(right); right.appendChild(form);
	var n = document.getElementById('cp-auth-notice').content.cloneNode(true);
	if (n.childNodes.length) { wrap.parentNode.insertBefore(n, wrap); }
	document.body.classList.add('cp-auth-page');
})();
</script>
	<?php
} );
add_filter( 'body_class', function ( $c ) {
	if ( cp_is_login_screen() || is_page( 'student-registration' ) ) {
		$c[] = 'cp-auth-page';
	}
	return $c;
} );
// One h1 per page: drop the (hidden) page-title block where the page draws its own heading.
add_filter( 'render_block_core/post-title', function ( $html ) {
	if ( is_front_page() || is_page( array( 'student-registration', 'dashboard' ) ) ) {
		return '';
	}
	return $html;
} );
// No "Free" labels anywhere (Rohit 2026-11-18): blank Tutor's strings instead of hiding them.
add_filter( 'gettext', function ( $translation, $text, $domain ) {
	if ( 'tutor' === $domain && in_array( $text, array( 'Free', 'Free access this course', 'Free access' ), true ) ) {
		return '';
	}
	return $translation;
}, 10, 3 );
// Meta descriptions for the Academy (home, catalog, sections, courses).
add_action( 'wp_head', function () {
	$d = '';
	if ( is_front_page() ) {
		$d = 'Coral Petal AI Academy: learn AI the way enterprises build it, from statistics and machine learning to agents in production.';
	} elseif ( is_post_type_archive( 'courses' ) ) {
		$d = 'Browse Coral Petal AI Academy courses in six sections: Statistics, Machine Learning, Deep Learning, Generative AI, Agentic AI and Cloud Computing.';
	} elseif ( is_tax( 'course-category' ) ) {
		$t = get_queried_object();
		$d = $t->description ? $t->description : 'Courses in ' . $t->name . ' at Coral Petal AI Academy.';
	} elseif ( is_singular( 'courses' ) ) {
		$d = get_the_excerpt();
	} elseif ( is_page( 'student-registration' ) ) {
		$d = 'Create your Coral Petal AI Academy account.';
	} elseif ( is_page( 'dashboard' ) ) {
		$d = 'Log in to Coral Petal AI Academy.';
	}
	if ( $d ) {
		echo '<meta name="description" content="' . esc_attr( wp_strip_all_tags( $d ) ) . '">' . "\n";
	}
}, 1 );
// Alt text on the course banner (Tutor prints the featured image with an empty alt).
add_filter( 'wp_get_attachment_image_attributes', function ( $attr, $attachment ) {
	if ( empty( $attr['alt'] ) ) {
		$attr['alt'] = get_the_title( $attachment->post_parent ) ?: 'Course image';
	}
	return $attr;
}, 10, 2 );
// Tutor prints the course banner as a bare <img>: give it alt text.
add_action( 'template_redirect', function () {
	if ( ! is_singular( 'courses' ) ) {
		return;
	}
	$title = esc_attr( get_the_title() );
	ob_start( function ( $html ) use ( $title ) {
		return preg_replace( '#<img src="([^"]+-thumbnail-[0-9a-f]+[^"]*)"\s*/?>#', '<img src="$1" alt="' . $title . '" />', $html );
	} );
}, 20 );
// Mobile: sticky Sign up / Courses bar for visitors (Rohit 2026-11-18).
add_action( 'wp_footer', function () {
	if ( is_user_logged_in() ) {
		return;
	}
	echo '<nav class="cp-mobile-cta" aria-label="Quick actions"><a class="a2" href="/courses/">Courses</a><a class="a1" href="/student-registration/">Sign up free</a></nav>';
} );
// Link previews and icons.
add_action( 'wp_head', function () {
	$img = esc_url( get_stylesheet_directory_uri() . '/assets/og-academy.png' );
	$t   = esc_attr( wp_get_document_title() );
	echo '<meta property="og:site_name" content="Coral Petal AI Academy">' . "\n";
	echo '<meta property="og:title" content="' . $t . '">' . "\n";
	echo '<meta property="og:image" content="' . $img . '">' . "\n";
	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	echo '<link rel="apple-touch-icon" href="' . esc_url( get_stylesheet_directory_uri() . '/assets/apple-touch-icon.png' ) . '">' . "\n";
}, 2 );

/* ==== Student dashboard after log-in (Rohit 2026-11-18: same design as the other sites) ====
   Tutor LMS (free) renders /dashboard/ and keeps the data (enrolments, progress, quiz attempts,
   reviews). The theme only dresses it: site header + footer around it (Tutor options set by
   remote_setup.sh), a welcome hero on the dashboard home, brand tokens in style.css. */
function cp_dash_is_home() {
	global $wp_query;
	return is_page( 'dashboard' ) && is_user_logged_in() && empty( $wp_query->query_vars['tutor_dashboard_page'] );
}
add_filter( 'body_class', function ( $c ) {
	if ( is_page( 'dashboard' ) && is_user_logged_in() ) {
		$c[] = 'cp-dash';
	}
	return $c;
} );
add_action( 'tutor_before_dashboard_content', function () {
	if ( ! cp_dash_is_home() ) {
		return;
	}
	$u    = wp_get_current_user();
	$name = $u->first_name ? $u->first_name : $u->display_name;
	$n    = 0;
	if ( function_exists( 'tutor_utils' ) ) {
		$ids = tutor_utils()->get_enrolled_courses_ids_by_user( $u->ID );
		$n   = is_array( $ids ) ? count( $ids ) : 0;
	}
	$sub = $n
		? sprintf( 'You are enrolled in %d course%s. Pick up where you left off, or explore the catalog.', $n, 1 === $n ? '' : 's' )
		: 'You are all set. Start with a fundamentals course or go straight to the Signature Course.';
	?>
<section class="cp-dash-hero" aria-label="Welcome">
	<p class="cp-chip cp-chip-coral">Your learning</p>
	<h1>Welcome back, <?php echo esc_html( $name ); ?></h1>
	<p><?php echo esc_html( $sub ); ?></p>
	<div class="cp-dash-actions">
		<a class="cp-dash-btn cp-dash-btn-ink" href="/courses/">Browse courses</a>
		<a class="cp-dash-btn cp-dash-btn-line" href="/courses/gen-agentic-ai/">Signature Course</a>
	</div>
</section>
	<?php
}, 1 );
// Header for signed-in students: "Log in" becomes "My dashboard", "Sign up" becomes "Log out".
add_filter( 'render_block_data', function ( $block ) {
	if ( 'core/navigation-link' !== ( $block['blockName'] ?? '' ) || ! is_user_logged_in() ) {
		return $block;
	}
	$url = $block['attrs']['url'] ?? '';
	if ( '/dashboard/' === $url ) {
		$block['attrs']['label'] = 'My dashboard';
	} elseif ( '/student-registration/' === $url ) {
		$block['attrs']['label'] = 'Log out';
		$block['attrs']['url']   = wp_logout_url( home_url( '/' ) );
	}
	return $block;
} );
// Signed-in students who open the sign-up page go to their dashboard.
add_action( 'template_redirect', function () {
	if ( is_page( 'student-registration' ) && is_user_logged_in() && ! current_user_can( 'manage_options' ) ) {
		wp_safe_redirect( home_url( '/dashboard/' ) );
		exit;
	}
}, 5 );
