<?php
// Shared helpers for the main site's small PHP endpoints (contact form, page-view beacon). 2026-11-18.
// Writes go to the ADMIN database (same server), so the admin app shows them.
// Config (never in git): ~/.coralpetal/admin-config-dev.json (dev.coralpetal.ai) or admin-config.json (prod),
// written by scripts/deploy_admin.sh. SMTP: ~/.coralpetal/academy-smtp.php (Proton SMTP token, academy@).
// This file is blocked from the web by api/.htaccess.
declare(strict_types=1);

function cpx_env(): string {
	return preg_match( '/^dev[.-]/', $_SERVER['HTTP_HOST'] ?? '' ) ? 'dev' : 'prod';
}

function cpx_home(): string {
	$home = getenv( 'HOME' );
	if ( ! $home && function_exists( 'posix_getpwuid' ) ) { $home = posix_getpwuid( posix_geteuid() )['dir'] ?? ''; }
	return rtrim( (string) $home, '/' );
}

function cpx_db(): ?PDO {
	static $pdo = null;
	if ( $pdo ) { return $pdo; }
	$file = cpx_home() . '/.coralpetal/' . ( cpx_env() === 'dev' ? 'admin-config-dev.json' : 'admin-config.json' );
	$cfg = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null;
	$d = $cfg['db'] ?? null;
	if ( ! $d ) { return null; }
	try {
		$pdo = new PDO( "mysql:host={$d['host']};dbname={$d['name']};charset=utf8mb4", $d['user'], $d['pass'],
			array( PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC ) );
	} catch ( Throwable $e ) {
		error_log( 'site api: db ' . $e->getMessage() );
		return null;
	}
	return $pdo;
}

/** Same definitions as admin/lib/schema.php (either side may create them first). */
function cpx_tables( PDO $db ): void {
	$db->exec( "CREATE TABLE IF NOT EXISTS contact_messages (
		id INT AUTO_INCREMENT PRIMARY KEY, created_at DATETIME NOT NULL, env VARCHAR(8) NOT NULL, topic VARCHAR(30) NOT NULL,
		name VARCHAR(120) NOT NULL, email VARCHAR(190) NOT NULL, company VARCHAR(160) NULL, message TEXT NOT NULL,
		status VARCHAR(20) NOT NULL DEFAULT 'New', mailed TINYINT(1) NOT NULL DEFAULT 0, ip VARCHAR(45) NULL,
		notes TEXT NULL, updated_by VARCHAR(120) NULL, updated_at DATETIME NULL, INDEX (status), INDEX (created_at)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4" );
	$db->exec( "CREATE TABLE IF NOT EXISTS pageviews (
		id BIGINT AUTO_INCREMENT PRIMARY KEY, at DATETIME NOT NULL, day DATE NOT NULL, site VARCHAR(20) NOT NULL,
		path VARCHAR(255) NOT NULL, ref_host VARCHAR(120) NULL, visitor CHAR(16) NOT NULL, device VARCHAR(10) NOT NULL,
		INDEX (day), INDEX (site, day), INDEX (visitor, day)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4" );
}

/**
 * Send one plain-text mail through Proton SMTP (STARTTLS, AUTH LOGIN). Returns true on success.
 * No library: the main site is static, so this small client keeps it dependency-free.
 */
function cpx_mail( string $to, string $subject, string $body, string $reply_to = '' ): bool {
	$f = cpx_home() . '/.coralpetal/academy-smtp.php';
	$c = is_readable( $f ) ? include $f : null;
	if ( ! is_array( $c ) || empty( $c['user'] ) || empty( $c['pass'] ) ) { error_log( 'site api: smtp not configured' ); return false; }
	$s = @stream_socket_client( 'tcp://smtp.protonmail.ch:587', $en, $es, 15 );
	if ( ! $s ) { error_log( "site api: smtp connect $es" ); return false; }
	stream_set_timeout( $s, 20 );
	$read = function () use ( $s ): string { $r = ''; while ( ( $l = fgets( $s, 515 ) ) !== false ) { $r .= $l; if ( isset( $l[3] ) && $l[3] === ' ' ) { break; } } return $r; };
	$cmd = function ( string $line, string $want ) use ( $s, $read ): bool {
		if ( $line !== '' ) { fwrite( $s, $line . "\r\n" ); }
		$r = $read();
		if ( strpos( $r, $want ) !== 0 ) { error_log( 'site api: smtp ' . ( $line ? strtok( $line, ' ' ) : 'greet' ) . ' → ' . trim( $r ) ); return false; }
		return true;
	};
	$from = $c['user'];
	$ok = $cmd( '', '220' ) && $cmd( 'EHLO coralpetal.ai', '250' ) && $cmd( 'STARTTLS', '220' )
		&& stream_socket_enable_crypto( $s, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT )
		&& $cmd( 'EHLO coralpetal.ai', '250' ) && $cmd( 'AUTH LOGIN', '334' ) && $cmd( base64_encode( $from ), '334' )
		&& $cmd( base64_encode( $c['pass'] ), '235' ) && $cmd( "MAIL FROM:<$from>", '250' ) && $cmd( "RCPT TO:<$to>", '250' ) && $cmd( 'DATA', '354' );
	if ( $ok ) {
		$clean = fn( string $v ): string => str_replace( array( "\r", "\n" ), ' ', $v );
		$hdr = 'From: Coral Petal AI Website <' . $from . ">\r\nTo: <$to>\r\n"
			. ( $reply_to ? 'Reply-To: ' . $clean( $reply_to ) . "\r\n" : '' )
			. 'Subject: =?UTF-8?B?' . base64_encode( $clean( $subject ) ) . "?=\r\nDate: " . date( 'r' ) . "\r\n"
			. 'Message-ID: <' . bin2hex( random_bytes( 8 ) ) . "@coralpetal.ai>\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n";
		fwrite( $s, $hdr . "\r\n" . chunk_split( base64_encode( $body ) ) . ".\r\n" );
		$ok = $cmd( '', '250' );
		fwrite( $s, "QUIT\r\n" );
	}
	fclose( $s );
	return $ok;
}
