<?php
// Runs on the server (scripts/academy_wp_dev.sh): reads "user\npass\n" from stdin and writes
// ~/.coralpetal/academy-smtp.php (mode 600, outside every docroot). Prints nothing secret.
umask( 077 );
$lines = explode( "\n", stream_get_contents( STDIN ) );
$user  = trim( $lines[0] ?? '' );
$pass  = trim( $lines[1] ?? '' );
if ( '' === $user || '' === $pass ) {
	fwrite( STDERR, "smtp: empty input, nothing stored\n" );
	exit( 1 );
}
$dir = getenv( 'HOME' ) . '/.coralpetal';
if ( ! is_dir( $dir ) ) {
	mkdir( $dir, 0700, true );
}
$file = $dir . '/academy-smtp.php';
file_put_contents( $file, '<?php return ' . var_export( array( 'user' => $user, 'pass' => $pass ), true ) . ";\n" );
chmod( $file, 0600 );
echo "smtp credentials stored for {$user}\n";
