<?php
/**
 * Generate deterministic WebP grid thumbnails from annual photo directories.
 *
 * Usage: php tools/generate-photo-thumbnails.php <source-root> <output-root> [max-edge] [quality]
 * Output: <output-root>/thumbs/<year>/<source-basename>.webp
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "This utility is CLI-only.\n" );
	exit( 2 );
}

if ( $argc < 3 ) {
	fwrite( STDERR, "Usage: php tools/generate-photo-thumbnails.php <source-root> <output-root> [max-edge] [quality]\n" );
	exit( 2 );
}
if ( ! extension_loaded( 'gd' ) || ! function_exists( 'imagewebp' ) ) {
	fwrite( STDERR, "PHP GD with WebP support is required.\n" );
	exit( 2 );
}

$source_root = realpath( $argv[1] );
$output_root = rtrim( $argv[2], DIRECTORY_SEPARATOR );
$max_edge    = isset( $argv[3] ) ? max( 320, min( 2400, (int) $argv[3] ) ) : 1000;
$quality     = isset( $argv[4] ) ? max( 50, min( 95, (int) $argv[4] ) ) : 82;
if ( ! $source_root || ! is_dir( $source_root ) ) {
	fwrite( STDERR, "Source directory does not exist.\n" );
	exit( 2 );
}
if ( ! is_dir( $output_root ) && ! mkdir( $output_root, 0755, true ) && ! is_dir( $output_root ) ) {
	fwrite( STDERR, "Unable to create output directory.\n" );
	exit( 2 );
}

$decoders = array(
	'jpg'  => 'imagecreatefromjpeg',
	'jpeg' => 'imagecreatefromjpeg',
	'png'  => 'imagecreatefrompng',
	'webp' => 'imagecreatefromwebp',
);
$written = 0;
$failed  = 0;
$bytes   = 0;

foreach ( new DirectoryIterator( $source_root ) as $year_directory ) {
	if ( ! $year_directory->isDir() || $year_directory->isDot() || ! preg_match( '/^[0-9]{4}$/', $year_directory->getFilename() ) ) {
		continue;
	}
	$year        = $year_directory->getFilename();
	$destination = $output_root . DIRECTORY_SEPARATOR . 'thumbs' . DIRECTORY_SEPARATOR . $year;
	if ( ! is_dir( $destination ) && ! mkdir( $destination, 0755, true ) && ! is_dir( $destination ) ) {
		fwrite( STDERR, "Unable to create {$destination}.\n" );
		$failed++;
		continue;
	}

	foreach ( new DirectoryIterator( $year_directory->getPathname() ) as $source_file ) {
		if ( ! $source_file->isFile() ) {
			continue;
		}
		$extension = strtolower( $source_file->getExtension() );
		if ( ! isset( $decoders[ $extension ] ) || ! function_exists( $decoders[ $extension ] ) ) {
			continue;
		}
		$source = @call_user_func( $decoders[ $extension ], $source_file->getPathname() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Invalid source files are reported below.
		if ( ! $source ) {
			fwrite( STDERR, "Unable to decode {$source_file->getPathname()}.\n" );
			$failed++;
			continue;
		}

		$source_width  = imagesx( $source );
		$source_height = imagesy( $source );
		$scale         = min( 1, $max_edge / max( $source_width, $source_height ) );
		$target_width  = max( 1, (int) round( $source_width * $scale ) );
		$target_height = max( 1, (int) round( $source_height * $scale ) );
		$thumbnail     = imagecreatetruecolor( $target_width, $target_height );
		imagealphablending( $thumbnail, false );
		imagesavealpha( $thumbnail, true );
		$transparent = imagecolorallocatealpha( $thumbnail, 0, 0, 0, 127 );
		imagefill( $thumbnail, 0, 0, $transparent );
		imagecopyresampled( $thumbnail, $source, 0, 0, 0, 0, $target_width, $target_height, $source_width, $source_height );

		$target = $destination . DIRECTORY_SEPARATOR . $source_file->getBasename( '.' . $source_file->getExtension() ) . '.webp';
		$temp   = $target . '.tmp';
		$ok     = imagewebp( $thumbnail, $temp, $quality ) && rename( $temp, $target );
		imagedestroy( $thumbnail );
		imagedestroy( $source );
		if ( ! $ok ) {
			@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Cleanup after a reported write failure.
			fwrite( STDERR, "Unable to write {$target}.\n" );
			$failed++;
			continue;
		}
		$written++;
		$bytes += filesize( $target );
	}
}

printf( "Generated %d thumbnails (%0.2f MiB), %d failures.\n", $written, $bytes / 1048576, $failed );
exit( $failed ? 1 : 0 );
