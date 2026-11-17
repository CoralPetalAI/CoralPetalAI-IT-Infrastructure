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
add_filter( 'the_content', function ( $content ) {
	$is_login = is_page( 'dashboard' ) && ! is_user_logged_in();
	$is_reg   = is_page( 'student-registration' );
	if ( ! $is_login && ! $is_reg ) {
		return $content;
	}
	$logo = esc_url( get_stylesheet_directory_uri() . '/assets/logo.svg' );
	if ( $is_reg ) {
		$kick = 'Join the Academy';
		$h    = 'Registration to Coral Petal AI<sup class="tm">™</sup> Academy';
		$p    = 'Create your free account to enrol in courses, track your progress and earn certificates.';
		$li   = array( 'Fundamentals to the Signature Course', 'Hands-on builds in every module', 'Live classes and bootcamps', 'We’ll email you a link to verify your address' );
		$alt  = 'Already have an account? <a href="/dashboard/">Log in</a>';
	} else {
		$kick = 'Welcome back';
		$h    = 'Log in to Coral Petal AI<sup class="tm">™</sup> Academy';
		$p    = 'Pick up where you left off: your courses, progress and certificates are waiting.';
		$li   = array( 'Continue your courses', 'See your progress', 'Download certificates' );
		$alt  = 'New here? <a href="/student-registration/">Create an account</a>';
	}
	$items = '';
	foreach ( $li as $x ) {
		$items .= '<li>' . $x . '</li>';
	}
	$side = '<aside class="cp-auth-side"><img src="' . $logo . '" alt="" width="56" height="48"><p class="cp-chip cp-chip-coral">' . $kick . '</p><h1>' . $h . '</h1><p class="cp-auth-p">' . $p . '</p><ul class="cp-auth-list">' . $items . '</ul><p class="cp-auth-alt">' . $alt . '</p></aside>';
	return '<div class="cp-auth">' . $side . '<div class="cp-auth-form">' . $content . '</div></div>';
}, 20 );
add_filter( 'body_class', function ( $c ) {
	if ( ( is_page( 'dashboard' ) && ! is_user_logged_in() ) || is_page( 'student-registration' ) ) {
		$c[] = 'cp-auth-page';
	}
	return $c;
} );
