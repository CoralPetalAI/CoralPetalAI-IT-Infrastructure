<?php
// Creates the admin tables if missing and seeds them ONCE (only when a table is empty). Never removes data.
declare(strict_types=1);

function cp_migrate(): void {
	$db = cp_db();
	$db->exec( "CREATE TABLE IF NOT EXISTS users (
		id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(190) NOT NULL UNIQUE, name VARCHAR(120) NOT NULL,
		role VARCHAR(20) NOT NULL DEFAULT 'admin', pass_hash VARCHAR(255) NOT NULL, must_change TINYINT(1) NOT NULL DEFAULT 1,
		active TINYINT(1) NOT NULL DEFAULT 1, last_login DATETIME NULL, created_at DATETIME NOT NULL
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4" );
	$db->exec( "CREATE TABLE IF NOT EXISTS login_attempts (
		id BIGINT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(190) NOT NULL, ip VARCHAR(45) NOT NULL, ok TINYINT(1) NOT NULL, at DATETIME NOT NULL,
		INDEX (email), INDEX (ip), INDEX (at)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4" );
	$db->exec( "CREATE TABLE IF NOT EXISTS tasks (
		id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(200) NOT NULL, area VARCHAR(40) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'Backlog',
		priority VARCHAR(10) NOT NULL DEFAULT 'Medium', owner VARCHAR(80) NULL, target_date DATE NULL, shipped_date DATE NULL, notes TEXT NULL,
		updated_by VARCHAR(120) NULL, updated_at DATETIME NOT NULL, created_at DATETIME NOT NULL, INDEX (status), INDEX (area)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4" );
	$db->exec( "CREATE TABLE IF NOT EXISTS legal_steps (
		id INT AUTO_INCREMENT PRIMARY KEY, track VARCHAR(40) NOT NULL, sort INT NOT NULL, step VARCHAR(240) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'Not started',
		due_hint VARCHAR(160) NULL, notes TEXT NULL, updated_by VARCHAR(120) NULL, updated_at DATETIME NOT NULL
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4" );
	$db->exec( "CREATE TABLE IF NOT EXISTS roadmap (
		id INT AUTO_INCREMENT PRIMARY KEY, sort INT NOT NULL, phase VARCHAR(120) NOT NULL, window_label VARCHAR(60) NOT NULL,
		start_date DATE NOT NULL, end_date DATE NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'Planned', items TEXT NULL, updated_at DATETIME NOT NULL
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4" );

	// v1.3.0: per-user time zone (Settings).
	$cols = array_column( $db->query( 'SHOW COLUMNS FROM users' )->fetchAll(), 'Field' );
	if ( ! in_array( 'timezone', $cols, true ) ) { $db->exec( "ALTER TABLE users ADD COLUMN timezone VARCHAR(40) NOT NULL DEFAULT 'Asia/Kolkata'" ); }

	// Users from config (first run only).
	if ( (int) $db->query( 'SELECT COUNT(*) FROM users' )->fetchColumn() === 0 ) {
		$ins = $db->prepare( 'INSERT INTO users (email, name, role, pass_hash, must_change, created_at) VALUES (?, ?, ?, ?, 1, UTC_TIMESTAMP())' );
		foreach ( cp_config()['users'] ?? array() as $u ) {
			if ( empty( $u['email'] ) || empty( $u['password'] ) ) { continue; }
			$ins->execute( array( strtolower( $u['email'] ), $u['name'] ?? $u['email'], $u['role'] ?? 'admin', password_hash( $u['password'], PASSWORD_DEFAULT ) ) );
		}
	}

	if ( (int) $db->query( 'SELECT COUNT(*) FROM tasks' )->fetchColumn() === 0 ) {
		$t = array(
			array( 'Release 1.0001: develop → main', 'Website', 'In progress', 'High', 'Rohit', '2026-11-20', 'PR #117; add secret ACADEMY_PROD_WP, merge, approve 3 prod deploys' ),
			array( 'Designed "back soon" page on all three sites', 'Website', 'Backlog', 'High', 'Claude', '2026-11-21', 'After Release 1.0001 is validated; private preview link' ),
			array( 'Services: Team enablement → Visit Academy link', 'Website', 'Ready', 'Low', 'Claude', null, 'Batch 14, PR #115, staged' ),
			array( 'Admin site (this app) on dev, then prod', 'Admin', 'In progress', 'High', 'Claude', '2026-11-25', 'dev-admin.coralpetal.ai first' ),
			array( 'Payments: Razorpay (or WooCommerce gateway) for course sales', 'Academy', 'Backlog', 'High', 'Rohit', null, 'Tutor free supports PayPal/manual or WooCommerce gateways' ),
			array( 'Buy Tutor LMS Pro (Individual lifetime)', 'Academy', 'Backlog', 'Medium', 'Rohit', '2027-04-30', '~1 month before course launch; ask Tutor if dev counts as a site' ),
			array( 'Signature Course videos + lessons', 'Academy', 'Backlog', 'High', 'Rohit', '2027-05-31', 'Videos in a separate workflow (4–6 months); Bunny Stream for protected video' ),
			array( 'Move academy-wp/ to CoralPetalAI GitHub repo', 'Engineering', 'Backlog', 'Low', 'Claude', null, 'gh login as CoralPetalAI first' ),
			array( 'Off-site backups to Azure Blob', 'Engineering', 'Backlog', 'Medium', 'Claude', null, 'Hostinger Business plan at launch for daily backups' ),
			array( 'Privacy policy, terms and refund pages', 'Legal', 'Backlog', 'High', 'Shalini', null, 'Needed before Razorpay and launch' ),
		);
		$ins = $db->prepare( 'INSERT INTO tasks (title, area, status, priority, owner, target_date, notes, updated_by, updated_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, "seed", UTC_TIMESTAMP(), UTC_TIMESTAMP())' );
		foreach ( $t as $r ) { $ins->execute( $r ); }
	}

	if ( (int) $db->query( 'SELECT COUNT(*) FROM legal_steps' )->fetchColumn() === 0 ) {
		$l = array(
			array( 'Trademark', 'TM search: "Coral Petal" clear in all classes', 'Done', '11 Nov 2026' ),
			array( 'Trademark', 'Class 3 DSC on USB token (valid to 12 Nov 2028)', 'Done', '13 Nov 2026' ),
			array( 'Trademark', 'IP India e-filing account (coralpetal)', 'Done', '13 Nov 2026' ),
			array( 'Trademark', 'TM-A word mark "Coral Petal AI", Cl 42 + 41 — App. 8120682', 'Done', 'Filed 14 Nov 2026' ),
			array( 'Trademark', 'TM-A logo (Petal Network) Cl 42 + 41 — App. 8120777', 'Done', 'Filed 14 Nov 2026' ),
			array( 'Trademark', 'Check e-status every 2 weeks', 'In progress', 'Tuesdays to 31 Mar 2027' ),
			array( 'Trademark', 'Reply to examination report', 'Not started', 'Within 30 days of the report' ),
			array( 'Trademark', 'Publication in Trade Marks Journal (4-month opposition)', 'Not started', 'After acceptance' ),
			array( 'Trademark', 'Registration certificate; switch ™ to ®', 'Not started', 'After opposition period' ),
			array( 'Trademark', 'Assign marks to the company (TM-P)', 'Not started', 'After incorporation' ),
			array( 'Trademark', 'Decide foreign filing (Paris priority)', 'Not started', 'Before 14 May 2027' ),
			array( 'Company', 'Name check on MCA: no "Coral Petal AI" company', 'Done', '11 Nov 2026' ),
			array( 'Company', 'Structure chosen: Private Limited', 'Done', '11 Nov 2026' ),
			array( 'Company', 'Confirm directors, shareholders and split in writing', 'Not started', '' ),
			array( 'Company', 'Class 3 DSC for each director without one', 'Not started', '' ),
			array( 'Company', 'Registered office proof + owner NOC + utility bill', 'Not started', '' ),
			array( 'Company', 'SPICe+ Part A: reserve "Coral Petal AI Private Limited"', 'Not started', 'Attach trademark owner NOC' ),
			array( 'Company', 'SPICe+ Part B, e-MoA, e-AoA, AGILE-PRO-S (CA/CS certified)', 'Not started', '' ),
			array( 'Company', 'Certificate of Incorporation', 'Not started', '1–3 weeks after filing' ),
			array( 'Company', 'Bank account + share capital', 'Not started', '' ),
			array( 'Company', 'INC-20A commencement of business', 'Not started', 'Within 180 days' ),
			array( 'Company', 'First auditor (ADT-1)', 'Not started', 'Within 30 days' ),
			array( 'Company', 'DPIIT Startup India + Udyam', 'Not started', 'Free' ),
			array( 'Company', 'GST registration', 'Not started', 'When turnover > ₹20 lakh or a client needs it' ),
			array( 'Company', "Founders' agreement + IP assignment + contracts", 'Not started', 'With a lawyer' ),
		);
		$ins = $db->prepare( 'INSERT INTO legal_steps (track, sort, step, status, due_hint, updated_by, updated_at) VALUES (?, ?, ?, ?, ?, "seed", UTC_TIMESTAMP())' );
		foreach ( $l as $i => $r ) { $ins->execute( array( $r[0], $i + 1, $r[1], $r[2], $r[3] ) ); }
	}

	if ( (int) $db->query( 'SELECT COUNT(*) FROM roadmap' )->fetchColumn() === 0 ) {
		$r = array(
			array( 'Foundation: brand, trademarks, websites', 'Oct–Dec 2026', '2026-10-01', '2026-12-31', 'In progress', 'Trademarks filed · Main site, Careers, Academy on dev · Release 1.0001' ),
			array( 'Payments + email', 'Dec 2026–Jan 2027', '2026-12-01', '2027-01-31', 'Planned', 'Razorpay · Proton SMTP · Policies pages' ),
			array( 'Signature Course production', 'Jan–May 2027', '2027-01-01', '2027-05-31', 'Planned', 'Videos · Lessons · Bunny Stream · Tutor Pro' ),
			array( 'Company incorporation', 'Dec 2026–Mar 2027', '2026-12-01', '2027-03-31', 'Planned', 'Pvt Ltd · DPIIT · TM-P assignment' ),
			array( 'Go-live', 'Jun 2027', '2027-06-01', '2027-06-30', 'Planned', 'Public launch of the Academy and consulting' ),
			array( 'Bootcamps + live sessions', 'Jul–Dec 2027', '2027-07-01', '2027-12-31', 'Planned', 'Zoom Pro · Recordings → lessons' ),
			array( 'Enterprise scale-up', '2028', '2028-01-01', '2028-12-31', 'Planned', 'Managed platform, SSO, SLA when a client asks' ),
		);
		$ins = $db->prepare( 'INSERT INTO roadmap (sort, phase, window_label, start_date, end_date, status, items, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())' );
		foreach ( $r as $i => $x ) { $ins->execute( array( $i + 1, $x[0], $x[1], $x[2], $x[3], $x[4], $x[5] ) ); }
	}
}
