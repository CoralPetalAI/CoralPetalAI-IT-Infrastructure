<?php
// Coral Petal AI Admin — core: config, database, sessions, auth, CSRF, layout.
// Config (never in git): ~/.coralpetal/admin-config-dev.json or admin-config.json (prod), written by the deploy
// from secret ADMIN_CONFIG_DEV / ADMIN_CONFIG:
// { "db": {host,name,user,pass}, "academy_wp_config": "/home/…/public_html/(dev-)academy/wp-config.php",
//   "users": [ {"email","name","role":"owner|admin","password"} ]   ← first-run seed only, must change at first login }
declare(strict_types=1);

const CP_ADMIN_VERSION = '1.3.0';

function cp_env(): string {
	return preg_match( '/^dev-/', $_SERVER['HTTP_HOST'] ?? '' ) ? 'dev' : 'prod';
}

function cp_config(): array {
	static $cfg = null;
	if ( null !== $cfg ) { return $cfg; }
	$home = getenv( 'HOME' );
	if ( ! $home && function_exists( 'posix_getpwuid' ) ) { $home = posix_getpwuid( posix_geteuid() )['dir'] ?? ''; }
	$file = rtrim( (string) $home, '/' ) . '/.coralpetal/' . ( cp_env() === 'dev' ? 'admin-config-dev.json' : 'admin-config.json' );
	$cfg = is_readable( $file ) ? ( json_decode( (string) file_get_contents( $file ), true ) ?: array() ) : array();
	return $cfg;
}

function cp_db(): PDO {
	static $pdo = null;
	if ( $pdo ) { return $pdo; }
	$d = cp_config()['db'] ?? null;
	if ( ! $d ) { cp_fail( 'The admin site is not configured yet.' ); }
	$pdo = new PDO( "mysql:host={$d['host']};dbname={$d['name']};charset=utf8mb4", $d['user'], $d['pass'],
		array( PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC ) );
	return $pdo;
}

function cp_fail( string $msg, int $code = 503 ): void {
	http_response_code( $code );
	echo '<!doctype html><meta charset="utf-8"><title>Admin</title><p style="font-family:system-ui;padding:40px">' . htmlspecialchars( $msg ) . '</p>';
	exit;
}

function h( $s ): string { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }

/** A stored UTC datetime shown in the signed-in user's time zone (Settings). */
function cp_time( ?string $utc, ?array $u = null ): string {
	if ( ! $utc ) { return '—'; }
	$tz = $u['timezone'] ?? 'Asia/Kolkata';
	try { $d = new DateTime( $utc, new DateTimeZone( 'UTC' ) ); $d->setTimezone( new DateTimeZone( $tz ) ); return $d->format( 'j M Y, H:i' ) . ' ' . $d->format( 'T' ); }
	catch ( Throwable $e ) { return $utc . ' UTC'; }
}

// ---------- Session + security headers ----------
function cp_start(): void {
	header( 'X-Robots-Tag: noindex, nofollow' );
	header( 'X-Frame-Options: DENY' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Referrer-Policy: same-origin' );
	header( "Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; script-src 'self' 'unsafe-inline'; frame-ancestors 'none'" );
	header( 'Cache-Control: no-store' );
	session_name( 'cp_admin' );
	session_set_cookie_params( array( 'lifetime' => 0, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict' ) );
	session_start();
	if ( isset( $_SESSION['seen'] ) && time() - $_SESSION['seen'] > 8 * 3600 ) { session_unset(); } // 8 h idle timeout
	$_SESSION['seen'] = time();
}

function cp_csrf(): string {
	if ( empty( $_SESSION['csrf'] ) ) { $_SESSION['csrf'] = bin2hex( random_bytes( 32 ) ); }
	return $_SESSION['csrf'];
}
function cp_csrf_field(): string { return '<input type="hidden" name="csrf" value="' . h( cp_csrf() ) . '">'; }
function cp_check_csrf(): void {
	if ( ! hash_equals( cp_csrf(), (string) ( $_POST['csrf'] ?? '' ) ) ) { cp_fail( 'Your session expired. Please go back and try again.', 400 ); }
}

// ---------- Auth ----------
/** Only the main account manages users (Rohit 11/18). Config may override with "superadmin_email". */
function cp_is_super( ?array $u ): bool {
	$super = strtolower( (string) ( cp_config()['superadmin_email'] ?? 'rohit@coralpetal.ai' ) );
	return $u && strtolower( (string) $u['email'] ) === $super;
}
function cp_user(): ?array {
	if ( empty( $_SESSION['uid'] ) ) { return null; }
	$st = cp_db()->prepare( 'SELECT * FROM users WHERE id = ? AND active = 1' );
	$st->execute( array( $_SESSION['uid'] ) );
	return $st->fetch() ?: null;
}

function cp_require_login(): array {
	$u = cp_user();
	if ( ! $u ) { header( 'Location: /login' ); exit; }
	if ( $u['must_change'] && ( $_GET['p'] ?? '' ) !== 'password' ) { header( 'Location: /password' ); exit; }
	return $u;
}

/** Returns null on success, or an error message. 5 failed tries per email or IP → 15 min lockout. */
function cp_login( string $email, string $pass ): ?string {
	$db = cp_db();
	$ip = $_SERVER['REMOTE_ADDR'] ?? '';
	$st = $db->prepare( 'SELECT COUNT(*) FROM login_attempts WHERE (email = ? OR ip = ?) AND ok = 0 AND at > (UTC_TIMESTAMP() - INTERVAL 15 MINUTE)' );
	$st->execute( array( $email, $ip ) );
	if ( (int) $st->fetchColumn() >= 5 ) { return 'Too many attempts. Please wait 15 minutes and try again.'; }
	$st = $db->prepare( 'SELECT * FROM users WHERE email = ? AND active = 1' );
	$st->execute( array( $email ) );
	$u = $st->fetch();
	$ok = $u && password_verify( $pass, $u['pass_hash'] );
	$db->prepare( 'INSERT INTO login_attempts (email, ip, ok, at) VALUES (?, ?, ?, UTC_TIMESTAMP())' )->execute( array( $email, $ip, $ok ? 1 : 0 ) );
	if ( ! $ok ) { usleep( 400000 ); return 'Email or password is not correct.'; }
	session_regenerate_id( true );
	$_SESSION['uid'] = (int) $u['id'];
	$db->prepare( 'UPDATE users SET last_login = UTC_TIMESTAMP() WHERE id = ?' )->execute( array( $u['id'] ) );
	return null;
}

// ---------- Layout ----------
function cp_head( string $title, ?array $user, string $cur = '' ): void {
	$nav = array( 'dashboard' => 'Dashboard', 'tasks' => 'Tasks', 'legal' => 'Legal', 'roadmap' => 'Roadmap' );
	if ( cp_is_super( $user ) ) { $nav['users'] = 'Users'; }
	echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
		. '<meta name="robots" content="noindex, nofollow"><title>' . h( $title ) . ' · Coral Petal AI Admin</title>'
		. '<link rel="icon" href="/assets/logo.svg" type="image/svg+xml">'
		. '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
		. '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Newsreader:opsz,wght@6..72,400;6..72,500&display=swap" rel="stylesheet">'
		. '<link rel="stylesheet" href="/assets/admin.css?v=' . CP_ADMIN_VERSION . '"></head><body>';
	echo '<header class="top"><div class="wrap bar"><a class="brand" href="/"><img src="/assets/logo.svg" alt="" width="30" height="30"><span>Coral <span class="acc">Petal AI</span><sup>™</sup> <em>Admin</em></span>'
		. ( cp_env() === 'dev' ? '<span class="envtag">DEV</span>' : '' ) . '</a>';
	if ( $user ) {
		echo '<nav>';
		foreach ( $nav as $k => $v ) { echo '<a href="/' . $k . '"' . ( $k === $cur ? ' aria-current="page"' : '' ) . '>' . $v . '</a>'; }
		$ini = strtoupper( implode( '', array_map( fn( $w ) => mb_substr( $w, 0, 1 ), array_slice( explode( ' ', trim( $user['name'] ) ), 0, 2 ) ) ) );
		echo '</nav><div class="me"><details class="acct"><summary><span class="av">' . h( $ini ) . '</span><span class="nm">' . h( $user['name'] ) . '</span><svg width="12" height="12" viewBox="0 0 12 12" aria-hidden="true"><path d="M3 4.5 6 7.5 9 4.5" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg></summary>'
			. '<div class="menu"><a href="/profile">Profile</a><a href="/password">Change password</a><a href="/settings">Settings</a></div></details>'
			. '<form method="post" action="/logout">' . cp_csrf_field() . '<button class="link">Log out</button></form></div>';
	}
	echo '</div></header><main class="wrap">';
}

function cp_foot(): void {
	echo '</main><script src="/assets/pwmatch.js?v=' . CP_ADMIN_VERSION . '"></script><footer class="wrap foot">Coral Petal AI™ Admin · ' . h( cp_env() ) . ' · v' . CP_ADMIN_VERSION . '</footer></body></html>';
}

function cp_flash( ?string $msg = null ): string {
	if ( null !== $msg ) { $_SESSION['flash'] = $msg; return ''; }
	$m = $_SESSION['flash'] ?? ''; unset( $_SESSION['flash'] );
	return $m ? '<div class="flash">' . h( $m ) . '</div>' : '';
}
