<?php
// Coral Petal AI Admin — front controller. Routes: login, logout, password, dashboard, tasks, legal, roadmap, users.
declare(strict_types=1);
require __DIR__ . '/lib/app.php';
require __DIR__ . '/lib/schema.php';
require __DIR__ . '/lib/kpi.php';

cp_start();
try { cp_migrate(); } catch ( Throwable $e ) { error_log( 'admin migrate: ' . $e->getMessage() ); cp_fail( 'The admin database is not reachable yet.' ); }

$p = preg_replace( '/[^a-z]/', '', (string) ( $_GET['p'] ?? '' ) ) ?: 'dashboard';
$post = ( $_SERVER['REQUEST_METHOD'] ?? '' ) === 'POST';
if ( $post ) { cp_check_csrf(); }

const TASK_STATUS = array( 'Backlog', 'Ready', 'In progress', 'Blocked', 'Done' );
const TASK_AREAS = array( 'Website', 'Academy', 'Admin', 'Engineering', 'Legal', 'Marketing', 'Finance', 'Content' );
const PRIORITIES = array( 'High', 'Medium', 'Low' );
const LEGAL_STATUS = array( 'Not started', 'In progress', 'Waiting', 'Done' );
const ROAD_STATUS = array( 'Planned', 'In progress', 'Done' );

function opts( array $list, string $cur ): string {
	$o = '';
	foreach ( $list as $v ) { $o .= '<option' . ( $v === $cur ? ' selected' : '' ) . '>' . h( $v ) . '</option>'; }
	return $o;
}
function slug( string $s ): string { return strtolower( preg_replace( '/[^a-z]+/i', '-', $s ) ); }

// ---------------- Login / logout ----------------
if ( $p === 'login' ) {
	$err = '';
	if ( $post ) {
		$err = cp_login( strtolower( trim( (string) ( $_POST['email'] ?? '' ) ) ), (string) ( $_POST['password'] ?? '' ) ) ?? '';
		if ( ! $err ) { header( 'Location: /dashboard' ); exit; }
	}
	cp_head( 'Sign in', null );
	echo '<section class="login"><div class="card coral"><div class="lbrand"><img src="/assets/logo.svg" alt="" width="40" height="40"><span>Coral <span class="acc">Petal AI</span><sup>™</sup></span></div><h1>Sign in</h1><p class="muted">Authorised users only.</p>'
		. ( $err ? '<div class="flash bad">' . h( $err ) . '</div>' : '' )
		. '<form method="post" class="stack">' . cp_csrf_field()
		. '<label>Email<input type="email" name="email" required autocomplete="username"></label>'
		. '<label>Password<input type="password" name="password" required autocomplete="current-password"></label>'
		. '<button class="btn">Sign in</button></form></div></section>';
	cp_foot();
	exit;
}
if ( $p === 'logout' && $post ) { session_unset(); session_destroy(); header( 'Location: /login' ); exit; }

$me = cp_require_login();

// ---------------- Password ----------------
if ( $p === 'password' ) {
	$err = '';
	if ( $post ) {
		$cur = (string) ( $_POST['current'] ?? '' ); $new = (string) ( $_POST['new'] ?? '' );
		if ( ! password_verify( $cur, $me['pass_hash'] ) ) { $err = 'Your current password is not correct.'; }
		elseif ( strlen( $new ) < 12 ) { $err = 'Use at least 12 characters.'; }
		elseif ( $new !== ( $_POST['again'] ?? '' ) ) { $err = 'The two new passwords do not match.'; }
		else {
			cp_db()->prepare( 'UPDATE users SET pass_hash = ?, must_change = 0 WHERE id = ?' )->execute( array( password_hash( $new, PASSWORD_DEFAULT ), $me['id'] ) );
			session_regenerate_id( true );
			cp_flash( 'Password updated.' ); header( 'Location: /dashboard' ); exit;
		}
	}
	cp_head( 'Password', $me );
	echo '<section class="login"><div class="card coral pwbox">'
		. '<div class="lbrand"><img src="/assets/logo.svg" alt="" width="36" height="36"><span>Coral <span class="acc">Petal AI</span><sup>™</sup></span></div>'
		. '<h1>' . ( $me['must_change'] ? 'Set your password' : 'Change password' ) . '</h1>'
		. '<p class="muted">' . ( $me['must_change'] ? 'Welcome. For security, replace the starting password with your own before you continue.' : 'Choose a new password for your admin account.' ) . '</p>'
		. '<ul class="pwrules"><li>At least 12 characters</li><li>Not used anywhere else</li><li>Save it in your password manager</li></ul>'
		. ( $err ? '<div class="flash bad">' . h( $err ) . '</div>' : '' )
		. '<form method="post" class="stack" data-pwmatch>' . cp_csrf_field()
		. '<label>' . ( $me['must_change'] ? 'Starting password' : 'Current password' ) . '<input type="password" name="current" required autocomplete="current-password"></label>'
		. '<label>New password<input type="password" name="new" required minlength="12" autocomplete="new-password" data-pw1></label>'
		. '<label>Confirm new password<input type="password" name="again" required minlength="12" autocomplete="new-password" data-pw2></label>'
		. '<p class="pwmsg" aria-live="polite"></p>'
		. '<button class="btn">Save password</button></form></div></section>';
	cp_foot();
	exit;
}

// ---------------- Profile ----------------
if ( $p === 'profile' ) {
	if ( $post ) {
		$name = mb_substr( trim( (string) ( $_POST['name'] ?? '' ) ), 0, 120 );
		if ( $name !== '' ) { cp_db()->prepare( 'UPDATE users SET name = ? WHERE id = ?' )->execute( array( $name, $me['id'] ) ); cp_flash( 'Profile saved.' ); }
		header( 'Location: /profile' ); exit;
	}
	cp_head( 'Profile', $me );
	echo cp_flash() . '<section class="login"><div class="card coral pwbox"><div class="lbrand"><span class="av big">' . h( strtoupper( mb_substr( $me['name'], 0, 1 ) ) ) . '</span><span>Profile</span></div>'
		. '<form method="post" class="stack">' . cp_csrf_field()
		. '<label>Display name<input name="name" required maxlength="120" value="' . h( $me['name'] ) . '"></label>'
		. '<label>Email<input value="' . h( $me['email'] ) . '" readonly></label>'
		. '<div class="kv"><span>Access</span><b>' . ( cp_is_super( $me ) ? 'Main account (manages users)' : 'Admin' ) . '</b></div>'
		. '<div class="kv"><span>Last sign-in</span><b>' . h( cp_time( $me['last_login'], $me ) ) . '</b></div>'
		. '<div class="kv"><span>Member since</span><b>' . h( cp_time( $me['created_at'], $me ) ) . '</b></div>'
		. '<button class="btn">Save profile</button></form></div></section>';
	cp_foot();
	exit;
}

// ---------------- Settings ----------------
if ( $p === 'settings' ) {
	$zones = array( 'Asia/Kolkata' => 'India (IST)', 'UTC' => 'UTC', 'Asia/Dubai' => 'Dubai (GST)', 'Europe/London' => 'London', 'America/New_York' => 'New York', 'Asia/Singapore' => 'Singapore' );
	if ( $post ) {
		$tz = (string) ( $_POST['timezone'] ?? '' );
		if ( isset( $zones[ $tz ] ) ) { cp_db()->prepare( 'UPDATE users SET timezone = ? WHERE id = ?' )->execute( array( $tz, $me['id'] ) ); cp_flash( 'Settings saved.' ); }
		header( 'Location: /settings' ); exit;
	}
	cp_head( 'Settings', $me );
	$o = '';
	foreach ( $zones as $k => $v ) { $o .= '<option value="' . h( $k ) . '"' . ( ( $me['timezone'] ?? 'Asia/Kolkata' ) === $k ? ' selected' : '' ) . '>' . h( $v ) . '</option>'; }
	echo cp_flash() . '<section class="login"><div class="card coral pwbox"><div class="lbrand"><img src="/assets/logo.svg" alt="" width="36" height="36"><span>Settings</span></div>'
		. '<form method="post" class="stack">' . cp_csrf_field()
		. '<label>Time zone for dates and times<select name="timezone">' . $o . '</select></label>'
		. '<p class="muted small">Used for "last updated" and sign-in times across the admin site.</p>'
		. '<button class="btn">Save settings</button></form></div></section>';
	cp_foot();
	exit;
}

// ---------------- Dashboard ----------------
if ( $p === 'dashboard' ) {
	$k = cp_kpis();
	$db = cp_db();
	$tc = array_column( $db->query( 'SELECT status, COUNT(*) n FROM tasks GROUP BY status' )->fetchAll(), 'n', 'status' );
	$open = array_sum( $tc ) - (int) ( $tc['Done'] ?? 0 );
	$lg = $db->query( "SELECT SUM(status = 'Done') d, COUNT(*) n FROM legal_steps" )->fetch();
	cp_head( 'Dashboard', $me, 'dashboard' );
	echo cp_flash() . '<div class="hero"><div><p class="chip">Business overview</p><h1>Good to see you, ' . h( explode( ' ', $me['name'] )[0] ) . '.</h1><p class="muted">Academy numbers are live from ' . ( cp_env() === 'dev' ? 'dev-academy' : 'the Academy' ) . ', year to date ' . gmdate( 'Y' ) . '.</p></div></div>';
	if ( ! $k['ok'] ) { echo '<div class="flash bad">The Academy database could not be read, so course numbers are hidden.</div>'; }
	$money = $k['orders_available'] ? '$' . number_format( $k['revenue_ytd'], 2 ) : '—';
	echo '<div class="kpis">'
		. '<div class="card coral kpi"><small>Students</small><b>' . number_format( $k['students'] ) . '</b><span>unique learners enrolled</span></div>'
		. '<div class="card coral kpi"><small>Enrollments YTD</small><b>' . number_format( $k['enrol_ytd'] ) . '</b><span>' . number_format( $k['enrol_month'] ) . ' this month · ' . number_format( $k['enrol_total'] ) . ' all time</span></div>'
		. '<div class="card coral kpi"><small>Courses sold YTD</small><b>' . ( $k['orders_available'] ? number_format( $k['orders_ytd'] ) : '—' ) . '</b><span>' . ( $k['orders_available'] ? 'paid, completed orders' : 'payments not set up yet' ) . '</span></div>'
		. '<div class="card coral kpi"><small>Revenue YTD</small><b>' . $money . '</b><span>' . ( $k['orders_available'] ? 'from course sales' : 'shows once payments go live' ) . '</span></div>'
		. '</div>';
	// Monthly enrollments chart (SVG bars).
	$max = max( 1, max( $k['monthly'] ?: array( 0 ) ) );
	$bars = ''; $i = 0; $n = count( $k['monthly'] );
	foreach ( $k['monthly'] as $ym => $v ) {
		$hgt = (int) round( 120 * $v / $max ); $x = 10 + $i * 50;
		$bars .= '<g><rect x="' . $x . '" y="' . ( 140 - $hgt ) . '" width="30" height="' . max( 2, $hgt ) . '" rx="6" class="bar"><title>' . h( $ym ) . ': ' . $v . '</title></rect>'
			. '<text x="' . ( $x + 15 ) . '" y="158" text-anchor="middle">' . h( gmdate( 'M', strtotime( $ym . '-01' ) ) ) . '</text>'
			. ( $v ? '<text x="' . ( $x + 15 ) . '" y="' . ( 134 - $hgt ) . '" text-anchor="middle" class="val">' . $v . '</text>' : '' ) . '</g>';
		$i++;
	}
	echo '<div class="grid2"><section class="card"><h2>Enrollments, last 12 months</h2><svg class="chart" viewBox="0 0 ' . ( 20 + $n * 50 ) . ' 165" role="img" aria-label="Monthly enrollments">' . $bars . '</svg></section>';
	echo '<section class="card"><h2>Work in flight</h2><div class="minis">'
		. '<a class="mini" href="/tasks"><b>' . $open . '</b><span>open tasks</span></a>'
		. '<a class="mini" href="/tasks?status=In+progress"><b>' . (int) ( $tc['In progress'] ?? 0 ) . '</b><span>in progress</span></a>'
		. '<a class="mini" href="/tasks?status=Blocked"><b>' . (int) ( $tc['Blocked'] ?? 0 ) . '</b><span>blocked</span></a>'
		. '<a class="mini" href="/legal"><b>' . (int) $lg['d'] . '/' . (int) $lg['n'] . '</b><span>legal steps done</span></a></div>'
		. '<div class="bar-track"><i style="width:' . ( $lg['n'] ? round( 100 * $lg['d'] / $lg['n'] ) : 0 ) . '%"></i></div></section></div>';
	echo '<section class="card"><h2>Courses</h2><div class="tablewrap"><table><thead><tr><th>#</th><th>Course</th><th>Status</th><th>Price</th><th>Enrolled</th></tr></thead><tbody>';
	foreach ( $k['courses'] as $i => $c ) {
		$price = $c['sale'] !== null && $c['sale'] !== '' ? '$' . h( $c['sale'] ) . ' <s>$' . h( $c['price'] ) . '</s>' : ( $c['price'] ? '$' . h( $c['price'] ) : '—' );
		echo '<tr><td>' . ( $i + 1 ) . '</td><td>' . h( $c['title'] ) . '</td><td><span class="pill ' . ( $c['status'] === 'publish' ? 'ok' : 'grey' ) . '">' . ( $c['status'] === 'publish' ? 'Published' : 'Draft' ) . '</span></td><td>' . $price . '</td><td>' . (int) $c['enrolled'] . '</td></tr>';
	}
	if ( ! $k['courses'] ) { echo '<tr><td colspan="5" class="muted">No courses found.</td></tr>'; }
	echo '</tbody></table></div></section>';
	cp_foot();
	exit;
}

// ---------------- Tasks ----------------
if ( $p === 'tasks' ) {
	$db = cp_db();
	if ( $post ) {
		$a = (string) ( $_POST['action'] ?? '' );
		$f = fn( $k, $max = 200 ) => mb_substr( trim( (string) ( $_POST[ $k ] ?? '' ) ), 0, $max );
		$status = in_array( $f( 'status' ), TASK_STATUS, true ) ? $f( 'status' ) : 'Backlog';
		$area = in_array( $f( 'area' ), TASK_AREAS, true ) ? $f( 'area' ) : 'Website';
		$prio = in_array( $f( 'priority' ), PRIORITIES, true ) ? $f( 'priority' ) : 'Medium';
		$date = fn( $k ) => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $f( $k ) ) ? $f( $k ) : null;
		if ( $a === 'add' && $f( 'title' ) !== '' ) {
			$db->prepare( 'INSERT INTO tasks (title, area, status, priority, owner, target_date, notes, updated_by, updated_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())' )
				->execute( array( $f( 'title' ), $area, $status, $prio, $f( 'owner', 80 ) ?: null, $date( 'target_date' ), $f( 'notes', 2000 ) ?: null, $me['name'] ) );
			cp_flash( 'Task added.' );
		} elseif ( $a === 'save' ) {
			$shipped = $status === 'Done' ? ( $date( 'shipped_date' ) ?? gmdate( 'Y-m-d' ) ) : null;
			$db->prepare( 'UPDATE tasks SET title = ?, area = ?, status = ?, priority = ?, target_date = ?, shipped_date = ?, notes = ?, updated_by = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?' )
				->execute( array( $f( 'title' ), $area, $status, $prio, $date( 'target_date' ), $shipped, $f( 'notes', 2000 ) ?: null, $me['name'], (int) $_POST['id'] ) );
			cp_flash( 'Task saved.' );
		}
		header( 'Location: /tasks' . ( isset( $_GET['status'] ) ? '?status=' . urlencode( (string) $_GET['status'] ) : '' ) ); exit;
	}
	$filter = in_array( $_GET['status'] ?? '', TASK_STATUS, true ) ? $_GET['status'] : '';
	$sql = 'SELECT * FROM tasks' . ( $filter ? ' WHERE status = ?' : '' ) . " ORDER BY FIELD(status,'In progress','Blocked','Ready','Backlog','Done'), FIELD(priority,'High','Medium','Low'), target_date IS NULL, target_date";
	$st = $db->prepare( $sql ); $st->execute( $filter ? array( $filter ) : array() );
	$rows = $st->fetchAll();
	cp_head( 'Tasks', $me, 'tasks' );
	echo cp_flash() . '<div class="hero"><div><p class="chip">Tasks</p><h1>What we are shipping</h1></div></div><div class="filters"><a href="/tasks"' . ( $filter ? '' : ' class="on"' ) . '>All</a>';
	foreach ( TASK_STATUS as $s ) { echo '<a href="/tasks?status=' . urlencode( $s ) . '"' . ( $filter === $s ? ' class="on"' : '' ) . '>' . h( $s ) . '</a>'; }
	echo '</div><details class="card add"><summary>+ Add a task</summary><form method="post" class="taskform">' . cp_csrf_field() . '<input type="hidden" name="action" value="add">'
		. '<label class="wide">Title<input name="title" required maxlength="200"></label>'
		. '<label>Area<select name="area">' . opts( TASK_AREAS, 'Website' ) . '</select></label><label>Status<select name="status">' . opts( TASK_STATUS, 'Backlog' ) . '</select></label>'
		. '<label>Priority<select name="priority">' . opts( PRIORITIES, 'Medium' ) . '</select></label>'
		. '<label>Target date<input type="date" name="target_date"></label><label class="wide">Notes<textarea name="notes" rows="2"></textarea></label><button class="btn">Add task</button></form></details>';
	echo '<div class="tasks">';
	foreach ( $rows as $i => $t ) {
		echo '<details class="card task st-' . slug( $t['status'] ) . '"><summary><span class="n">' . ( $i + 1 ) . '</span><span class="tt"><b>' . h( $t['title'] ) . '</b><small>' . h( $t['area'] ) . ( $t['target_date'] ? ' · target ' . h( $t['target_date'] ) : '' ) . ( $t['shipped_date'] ? ' · shipped ' . h( $t['shipped_date'] ) : '' ) . '</small></span>'
			. '<span class="pill p-' . slug( $t['priority'] ) . '">' . h( $t['priority'] ) . '</span><span class="pill s-' . slug( $t['status'] ) . '">' . h( $t['status'] ) . '</span></summary>'
			. '<form method="post" class="taskform">' . cp_csrf_field() . '<input type="hidden" name="action" value="save"><input type="hidden" name="id" value="' . (int) $t['id'] . '">'
			. '<label class="wide">Title<input name="title" required maxlength="200" value="' . h( $t['title'] ) . '"></label>'
			. '<label>Area<select name="area">' . opts( TASK_AREAS, $t['area'] ) . '</select></label><label>Status<select name="status">' . opts( TASK_STATUS, $t['status'] ) . '</select></label>'
			. '<label>Priority<select name="priority">' . opts( PRIORITIES, $t['priority'] ) . '</select></label>'
			. '<label>Target date<input type="date" name="target_date" value="' . h( $t['target_date'] ) . '"></label><label>Shipped date<input type="date" name="shipped_date" value="' . h( $t['shipped_date'] ) . '"></label>'
			. '<label class="wide">Notes<textarea name="notes" rows="3">' . h( $t['notes'] ) . '</textarea></label>'
			. '<p class="muted small wide">Last updated ' . h( cp_time( $t['updated_at'], $me ) ) . '</p><button class="btn">Save</button></form></details>';
	}
	if ( ! $rows ) { echo '<p class="muted">No tasks here.</p>'; }
	echo '</div>';
	cp_foot();
	exit;
}

// ---------------- Legal ----------------
if ( $p === 'legal' ) {
	$db = cp_db();
	if ( $post ) {
		$s = in_array( $_POST['status'] ?? '', LEGAL_STATUS, true ) ? $_POST['status'] : 'Not started';
		$db->prepare( 'UPDATE legal_steps SET status = ?, notes = ?, updated_by = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?' )
			->execute( array( $s, mb_substr( trim( (string) ( $_POST['notes'] ?? '' ) ), 0, 2000 ) ?: null, $me['name'], (int) $_POST['id'] ) );
		cp_flash( 'Step updated.' ); header( 'Location: /legal' ); exit;
	}
	$rows = $db->query( 'SELECT * FROM legal_steps ORDER BY track DESC, sort' )->fetchAll();
	cp_head( 'Legal', $me, 'legal' );
	echo cp_flash() . '<div class="hero"><div><p class="chip">Legal</p><h1>Trademarks and company registration</h1><p class="muted">Guidance only, not legal advice. Confirm filings with a trademark attorney or a practising CA/CS.</p></div></div>';
	$tracks = array();
	foreach ( $rows as $r ) { $tracks[ $r['track'] ][] = $r; }
	foreach ( $tracks as $track => $steps ) {
		$done = count( array_filter( $steps, fn( $x ) => $x['status'] === 'Done' ) );
		echo '<section class="card coral legal"><div class="lh"><h2>' . h( $track ) . '</h2><span class="muted">' . $done . ' of ' . count( $steps ) . ' done</span></div><div class="bar-track"><i style="width:' . round( 100 * $done / max( 1, count( $steps ) ) ) . '%"></i></div><ol class="steps">';
		foreach ( $steps as $i => $s ) {
			echo '<li class="ls-' . slug( $s['status'] ) . '"><details><summary><span class="dot">' . ( $s['status'] === 'Done' ? '✓' : ( $i + 1 ) ) . '</span><span class="tt"><b>' . h( $s['step'] ) . '</b><small>' . h( $s['due_hint'] ) . '</small></span><span class="pill s-' . slug( $s['status'] ) . '">' . h( $s['status'] ) . '</span></summary>'
				. '<form method="post" class="inline">' . cp_csrf_field() . '<input type="hidden" name="id" value="' . (int) $s['id'] . '"><select name="status">' . opts( LEGAL_STATUS, $s['status'] ) . '</select>'
				. '<input name="notes" placeholder="Notes" value="' . h( $s['notes'] ) . '"><button class="btn sm">Save</button></form></details></li>';
		}
		echo '</ol></section>';
	}
	cp_foot();
	exit;
}

// ---------------- Roadmap ----------------
if ( $p === 'roadmap' ) {
	$db = cp_db();
	if ( $post ) {
		$s = in_array( $_POST['status'] ?? '', ROAD_STATUS, true ) ? $_POST['status'] : 'Planned';
		$db->prepare( 'UPDATE roadmap SET status = ?, items = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?' )
			->execute( array( $s, mb_substr( trim( (string) ( $_POST['items'] ?? '' ) ), 0, 2000 ), (int) $_POST['id'] ) );
		cp_flash( 'Roadmap updated.' ); header( 'Location: /roadmap' ); exit;
	}
	$rows = $db->query( 'SELECT * FROM roadmap ORDER BY sort' )->fetchAll();
	$min = strtotime( min( array_column( $rows, 'start_date' ) ?: array( gmdate( 'Y-m-d' ) ) ) );
	$max = strtotime( max( array_column( $rows, 'end_date' ) ?: array( gmdate( 'Y-m-d' ) ) ) );
	$span = max( 1, $max - $min ); $now = time();
	cp_head( 'Roadmap', $me, 'roadmap' );
	echo cp_flash() . '<div class="hero"><div><p class="chip">Roadmap</p><h1>From foundation to scale</h1></div></div><section class="card gantt">';
	// Year/quarter ruler
	echo '<div class="ruler">';
	for ( $t = strtotime( gmdate( 'Y-01-01', $min ) ); $t <= $max; $t = strtotime( '+3 months', $t ) ) {
		if ( $t < $min ) { continue; }
		$l = 100 * ( $t - $min ) / $span; $q = (int) ceil( (int) gmdate( 'n', $t ) / 3 );
		echo '<span style="left:' . round( $l, 2 ) . '%">Q' . $q . ' ' . gmdate( 'Y', $t ) . '</span>';
	}
	echo '</div>';
	if ( $now >= $min && $now <= $max ) { echo '<div class="today" style="left:calc(220px + (100% - 220px) * ' . round( ( $now - $min ) / $span, 4 ) . ')"><span>Today</span></div>'; }
	foreach ( $rows as $r ) {
		$l = 100 * ( strtotime( $r['start_date'] ) - $min ) / $span; $w = 100 * ( strtotime( $r['end_date'] ) - strtotime( $r['start_date'] ) ) / $span;
		echo '<div class="grow"><div class="gl"><b>' . h( $r['phase'] ) . '</b><small>' . h( $r['window_label'] ) . '</small></div><div class="gtrack"><div class="gbar r-' . slug( $r['status'] ) . '" style="left:' . round( $l, 2 ) . '%;width:' . max( 2, round( $w, 2 ) ) . '%"><span>' . h( $r['status'] ) . '</span></div></div></div>';
	}
	echo '</section><div class="phases">';
	foreach ( $rows as $i => $r ) {
		echo '<details class="card coral phase r-' . slug( $r['status'] ) . '"><summary><span class="n">' . ( $i + 1 ) . '</span><span class="tt"><b>' . h( $r['phase'] ) . '</b><small>' . h( $r['window_label'] ) . '</small></span><span class="pill s-' . slug( $r['status'] ) . '">' . h( $r['status'] ) . '</span></summary>'
			. '<ul class="chips">';
		foreach ( array_filter( array_map( 'trim', explode( '·', (string) $r['items'] ) ) ) as $it ) { echo '<li>' . h( $it ) . '</li>'; }
		echo '</ul><form method="post" class="inline">' . cp_csrf_field() . '<input type="hidden" name="id" value="' . (int) $r['id'] . '"><select name="status">' . opts( ROAD_STATUS, $r['status'] ) . '</select>'
			. '<input name="items" value="' . h( $r['items'] ) . '" title="Separate items with ·"><button class="btn sm">Save</button></form></details>';
	}
	echo '</div>';
	cp_foot();
	exit;
}

// ---------------- Users (owner only) ----------------
if ( $p === 'users' && cp_is_super( $me ) ) {
	$db = cp_db(); $newpass = '';
	if ( $post ) {
		$a = (string) ( $_POST['action'] ?? '' );
		if ( $a === 'add' ) {
			$email = strtolower( trim( (string) ( $_POST['email'] ?? '' ) ) );
			if ( filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
				$newpass = rtrim( strtr( base64_encode( random_bytes( 12 ) ), '+/', 'Kp' ), '=' );
				try {
					$db->prepare( 'INSERT INTO users (email, name, role, pass_hash, must_change, created_at) VALUES (?, ?, ?, ?, 1, UTC_TIMESTAMP())' )
						->execute( array( $email, mb_substr( trim( (string) ( $_POST['name'] ?? $email ) ), 0, 120 ), 'admin', password_hash( $newpass, PASSWORD_DEFAULT ) ) );
				} catch ( Throwable $e ) { $newpass = ''; cp_flash( 'That email already has an account.' ); }
			}
		} elseif ( $a === 'toggle' && (int) $_POST['id'] !== (int) $me['id'] ) {
			$db->prepare( 'UPDATE users SET active = 1 - active WHERE id = ?' )->execute( array( (int) $_POST['id'] ) );
			cp_flash( 'User updated.' ); header( 'Location: /users' ); exit;
		}
	}
	$rows = $db->query( 'SELECT id, email, name, role, active, last_login, must_change FROM users ORDER BY id' )->fetchAll();
	cp_head( 'Users', $me, 'users' );
	echo cp_flash() . '<div class="hero"><div><p class="chip">Users</p><h1>Who can sign in</h1></div></div>';
	if ( $newpass ) { echo '<div class="flash">User added. Starting password (shown once, they must change it at first sign-in): <code>' . h( $newpass ) . '</code></div>'; }
	echo '<section class="card"><div class="tablewrap"><table><thead><tr><th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Last sign-in</th><th>Status</th><th></th></tr></thead><tbody>';
	foreach ( $rows as $i => $u ) {
		echo '<tr><td>' . ( $i + 1 ) . '</td><td>' . h( $u['name'] ) . '</td><td>' . h( $u['email'] ) . '</td><td>' . h( $u['role'] ) . '</td><td>' . ( $u['last_login'] ? h( cp_time( $u['last_login'], $me ) ) : 'never' ) . '</td><td><span class="pill ' . ( $u['active'] ? 'ok' : 'grey' ) . '">' . ( $u['active'] ? 'Active' : 'Disabled' ) . '</span></td><td>'
			. ( (int) $u['id'] !== (int) $me['id'] ? '<form method="post">' . cp_csrf_field() . '<input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="' . (int) $u['id'] . '"><button class="link">' . ( $u['active'] ? 'Disable' : 'Enable' ) . '</button></form>' : '' ) . '</td></tr>';
	}
	echo '</tbody></table></div></section><section class="card"><h2>Add a user</h2><form method="post" class="taskform">' . cp_csrf_field() . '<input type="hidden" name="action" value="add">'
		. '<label>Name<input name="name" required maxlength="120"></label><label>Email<input type="email" name="email" required></label><button class="btn">Add user</button></form></section>';
	cp_foot();
	exit;
}

http_response_code( 404 );
cp_head( 'Not found', $me );
echo '<section class="narrow"><h1>Page not found</h1><p><a href="/dashboard">Back to the dashboard</a></p></section>';
cp_foot();
