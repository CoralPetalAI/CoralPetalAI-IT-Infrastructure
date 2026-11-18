<?php
// Business KPIs read READ-ONLY from the Academy's WordPress / Tutor LMS database (same server).
// The connection details come from that site's wp-config.php (path in the admin config); nothing is written.
declare(strict_types=1);

function cp_academy_db(): ?array {
	$path = cp_config()['academy_wp_config'] ?? '';
	if ( ! $path || ! is_readable( $path ) ) { return null; }
	$src = (string) file_get_contents( $path );
	$get = function ( string $k ) use ( $src ): string {
		return preg_match( "/define\(\s*['\"]" . $k . "['\"]\s*,\s*['\"]([^'\"]*)['\"]\s*\)/", $src, $m ) ? $m[1] : '';
	};
	$prefix = preg_match( '/\$table_prefix\s*=\s*[\'"]([A-Za-z0-9_]+)[\'"]/', $src, $m ) ? $m[1] : 'wp_';
	try {
		$pdo = new PDO( 'mysql:host=' . ( $get( 'DB_HOST' ) ?: 'localhost' ) . ';dbname=' . $get( 'DB_NAME' ) . ';charset=utf8mb4', $get( 'DB_USER' ), $get( 'DB_PASSWORD' ),
			array( PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC ) );
	} catch ( Throwable $e ) {
		error_log( 'admin: academy db ' . $e->getMessage() );
		return null;
	}
	return array( $pdo, $prefix );
}

function cp_table_exists( PDO $db, string $t ): bool {
	$st = $db->prepare( 'SHOW TABLES LIKE ?' );
	$st->execute( array( $t ) );
	return (bool) $st->fetchColumn();
}

/** All dashboard numbers. Revenue only counts paid, completed Tutor orders. */
function cp_kpis(): array {
	$out = array( 'ok' => false, 'students' => 0, 'enrol_total' => 0, 'enrol_ytd' => 0, 'enrol_month' => 0,
		'orders_ytd' => 0, 'revenue_ytd' => 0.0, 'currency' => 'USD', 'courses' => array(), 'monthly' => array(), 'orders_available' => false );
	$a = cp_academy_db();
	if ( ! $a ) { return $out; }
	list( $db, $p ) = $a;
	$out['ok'] = true;
	$year = gmdate( 'Y' );

	$out['students'] = (int) $db->query( "SELECT COUNT(DISTINCT post_author) FROM {$p}posts WHERE post_type = 'tutor_enrolled' AND post_status = 'completed'" )->fetchColumn();
	$out['enrol_total'] = (int) $db->query( "SELECT COUNT(*) FROM {$p}posts WHERE post_type = 'tutor_enrolled' AND post_status = 'completed'" )->fetchColumn();
	$st = $db->prepare( "SELECT COUNT(*) FROM {$p}posts WHERE post_type = 'tutor_enrolled' AND post_status = 'completed' AND YEAR(post_date_gmt) = ?" );
	$st->execute( array( $year ) );
	$out['enrol_ytd'] = (int) $st->fetchColumn();
	$out['enrol_month'] = (int) $db->query( "SELECT COUNT(*) FROM {$p}posts WHERE post_type = 'tutor_enrolled' AND post_status = 'completed' AND post_date_gmt >= DATE_FORMAT(UTC_TIMESTAMP(), '%Y-%m-01')" )->fetchColumn();

	// Last 12 months of enrollments.
	$rows = $db->query( "SELECT DATE_FORMAT(post_date_gmt, '%Y-%m') ym, COUNT(*) n FROM {$p}posts WHERE post_type = 'tutor_enrolled' AND post_status = 'completed'
		AND post_date_gmt >= DATE_SUB(DATE_FORMAT(UTC_TIMESTAMP(), '%Y-%m-01'), INTERVAL 11 MONTH) GROUP BY ym" )->fetchAll();
	$by = array_column( $rows, 'n', 'ym' );
	for ( $i = 11; $i >= 0; $i-- ) {
		$ym = gmdate( 'Y-m', strtotime( gmdate( 'Y-m-01' ) . " -$i month" ) );
		$out['monthly'][ $ym ] = (int) ( $by[ $ym ] ?? 0 );
	}

	// Per course: published courses with enrollment counts and price.
	$out['courses'] = $db->query( "SELECT c.ID id, c.post_title title, c.post_status status,
		(SELECT COUNT(*) FROM {$p}posts e WHERE e.post_type = 'tutor_enrolled' AND e.post_status = 'completed' AND e.post_parent = c.ID) enrolled,
		(SELECT meta_value FROM {$p}postmeta m WHERE m.post_id = c.ID AND m.meta_key = 'tutor_course_sale_price' LIMIT 1) sale,
		(SELECT meta_value FROM {$p}postmeta m WHERE m.post_id = c.ID AND m.meta_key = 'tutor_course_price' LIMIT 1) price
		FROM {$p}posts c WHERE c.post_type = 'courses' AND c.post_status IN ('publish','draft') ORDER BY enrolled DESC, c.post_title" )->fetchAll();

	// Sales from Tutor's native eCommerce (Tutor 3+), when the table exists.
	if ( cp_table_exists( $db, "{$p}tutor_orders" ) ) {
		$out['orders_available'] = true;
		$st = $db->prepare( "SELECT COUNT(*) n, COALESCE(SUM(total_price), 0) s FROM {$p}tutor_orders
			WHERE payment_status = 'paid' AND order_status = 'completed' AND YEAR(created_at_gmt) = ?" );
		$st->execute( array( $year ) );
		$r = $st->fetch();
		$out['orders_ytd'] = (int) $r['n'];
		$out['revenue_ytd'] = (float) $r['s'];
	}
	return $out;
}
