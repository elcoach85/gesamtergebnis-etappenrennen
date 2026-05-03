<?php
/**
 * Plugin Name: Gesamtergebnis-Etappenrennen
 * Description: Erzeugt aus Tagesergebnissen nach den Wettkampfbestimmungen Straße von German Cycling eine Gesamtwertung.
 * Version: 0.0.1
 * Plugin URI: https://the-race-days-stuttgart.org
 * Author: Nino Häberlen
 * Author URI: https://the-race-days-stuttgart.org
 * Tested up to: 
 * Text Domain: gesamtergebnis-etappenrennen
 * Requires Pluging: 
 * License: GPLv3
 *
 */

defined( 'ABSPATH' ) or die( 'Are you ok?' );

defined( 'GESAMTERGEBNIS_PLUGIN_DIR' ) || define( 'GESAMTERGEBNIS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
defined( 'GESAMTERGEBNIS_TOOL_DIR' ) || define( 'GESAMTERGEBNIS_TOOL_DIR', GESAMTERGEBNIS_PLUGIN_DIR . 'lib/RaceDaysResults-php/02_Tool/' );
defined( 'GESAMTERGEBNIS_LOG_DIR' ) || define( 'GESAMTERGEBNIS_LOG_DIR', GESAMTERGEBNIS_PLUGIN_DIR . 'logs/' );
defined( 'GESAMTERGEBNIS_LOG_FILE' ) || define( 'GESAMTERGEBNIS_LOG_FILE', GESAMTERGEBNIS_LOG_DIR . 'gesamtergebnis.log' );

add_action( 'admin_menu', 'geg_register_tools_pages' );

/**
 * Register entries in the "Tools" menu.
 */
function geg_register_tools_pages() {
	add_management_page(
		'RaceDays: Ingest ausfuehren',
		'RaceDays Ingest',
		'manage_options',
		'geg-run-main-ingest',
		'geg_render_main_ingest_page'
	);

	add_management_page(
		'RaceDays: Ergebnisdateien erstellen',
		'RaceDays Resultsfiles',
		'manage_options',
		'geg-run-make-resultsfiles',
		'geg_render_make_resultsfiles_page'
	);
}

/**
 * Render page for main_ingest.py.
 */
function geg_render_main_ingest_page() {
	geg_render_runner_page(
		'Main Ingest ausfuehren',
		'main_ingest.py',
		'geg_run_main_ingest'
	);
}

/**
 * Render page for make_resultsfiles.py.
 */
function geg_render_make_resultsfiles_page() {
	geg_render_runner_page(
		'Make Resultsfiles ausfuehren',
		'make_resultsfiles.py',
		'geg_run_make_resultsfiles'
	);
}

/**
 * Shared renderer for tool pages.
 *
 * @param string $headline    Headline for the page.
 * @param string $script_name Python script file name.
 * @param string $action_name Nonce action identifier.
 */
function geg_render_runner_page( $headline, $script_name, $action_name ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Du hast keine Berechtigung fuer diese Aktion.', 'gesamtergebnis-etappenrennen' ) );
	}

	$result = null;

	if ( isset( $_POST['geg_run_script'] ) ) {
		check_admin_referer( $action_name );
		$result = geg_execute_python_script( $script_name );
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html( $headline ); ?></h1>
		<p>Script: <code><?php echo esc_html( $script_name ); ?></code></p>
		<p>Logfile: <code><?php echo esc_html( GESAMTERGEBNIS_LOG_FILE ); ?></code></p>

		<form method="post">
			<?php wp_nonce_field( $action_name ); ?>
			<?php submit_button( 'Script starten', 'primary', 'geg_run_script' ); ?>
		</form>

		<?php if ( is_array( $result ) ) : ?>
			<div class="notice <?php echo $result['success'] ? 'notice-success' : 'notice-error'; ?> is-dismissible">
				<p><?php echo esc_html( $result['message'] ); ?></p>
			</div>
			<?php if ( ! empty( $result['stdout'] ) ) : ?>
				<h2>STDOUT</h2>
				<pre style="max-height:320px;overflow:auto;background:#fff;padding:12px;border:1px solid #ccd0d4;"><?php echo esc_html( $result['stdout'] ); ?></pre>
			<?php endif; ?>
			<?php if ( ! empty( $result['stderr'] ) ) : ?>
				<h2>STDERR</h2>
				<pre style="max-height:320px;overflow:auto;background:#fff;padding:12px;border:1px solid #ccd0d4;"><?php echo esc_html( $result['stderr'] ); ?></pre>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Execute the selected Python script and return execution details.
 *
 * @param string $script_name Python file name in the tool folder.
 *
 * @return array{success:bool,message:string,stdout:string,stderr:string,exit_code:int|null}
 */
function geg_execute_python_script( $script_name ) {
	$script_path = realpath( GESAMTERGEBNIS_TOOL_DIR . $script_name );
	$tool_dir    = realpath( GESAMTERGEBNIS_TOOL_DIR );

	if ( false === $script_path || false === $tool_dir ) {
		geg_write_log( 'error', 'Tool directory or script path could not be resolved.', array( 'script' => $script_name ) );

		return array(
			'success'   => false,
			'message'   => 'Skriptpfad konnte nicht aufgeloest werden. Details im Logfile.',
			'stdout'    => '',
			'stderr'    => '',
			'exit_code' => null,
		);
	}

	if ( 0 !== strpos( $script_path, $tool_dir ) ) {
		geg_write_log( 'error', 'Resolved script path is outside expected tool directory.', array( 'script_path' => $script_path ) );

		return array(
			'success'   => false,
			'message'   => 'Ungueltiger Skriptpfad. Details im Logfile.',
			'stdout'    => '',
			'stderr'    => '',
			'exit_code' => null,
		);
	}

	$python_binary = apply_filters( 'geg_python_binary', '' );

	if ( empty( $python_binary ) ) {
		$python_binary = getenv( 'WP_GEG_PYTHON_BIN' );
	}

	if ( empty( $python_binary ) ) {
		$python_binary = 'python';
	}

	$command = escapeshellarg( $python_binary ) . ' ' . escapeshellarg( $script_path );
	$start   = microtime( true );
	$stdout  = '';
	$stderr  = '';
	$exit    = null;

	geg_write_log(
		'info',
		'Executing Python script.',
		array(
			'script'  => $script_name,
			'command' => $command,
			'cwd'     => $tool_dir,
		)
	);

	if ( function_exists( 'proc_open' ) ) {
		$descriptors = array(
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		);

		$process = proc_open( $command, $descriptors, $pipes, $tool_dir );

		if ( is_resource( $process ) ) {
			$stdout = stream_get_contents( $pipes[1] );
			$stderr = stream_get_contents( $pipes[2] );

			fclose( $pipes[1] );
			fclose( $pipes[2] );

			$exit = proc_close( $process );
		} else {
			$stderr = 'proc_open konnte den Prozess nicht starten.';
		}
	} else {
		$output = shell_exec( $command . ' 2>&1' );
		$stdout = is_string( $output ) ? $output : '';
		$stderr = 'proc_open ist deaktiviert; Rueckfall auf shell_exec ohne separaten Exit-Code.';
	}

	$duration_ms = (int) round( ( microtime( true ) - $start ) * 1000 );
	$success     = ( is_int( $exit ) ) ? ( 0 === $exit ) : false;

	geg_write_log(
		$success ? 'info' : 'error',
		'Python script execution finished.',
		array(
			'script'      => $script_name,
			'exit_code'   => $exit,
			'duration_ms' => $duration_ms,
			'stdout'      => geg_clip_log_text( $stdout ),
			'stderr'      => geg_clip_log_text( $stderr ),
		)
	);

	return array(
		'success'   => $success,
		'message'   => $success
			? sprintf( 'Skript erfolgreich ausgefuehrt (%d ms).', $duration_ms )
			: sprintf( 'Skript mit Fehler beendet (Exit-Code: %s, %d ms).', is_null( $exit ) ? 'n/a' : (string) $exit, $duration_ms ),
		'stdout'    => $stdout,
		'stderr'    => $stderr,
		'exit_code' => $exit,
	);
}

/**
 * Write an entry to the plugin log file.
 *
 * @param string $level   Severity level.
 * @param string $message Human-readable log message.
 * @param array  $context Structured context values.
 */
function geg_write_log( $level, $message, $context = array() ) {
	if ( ! is_dir( GESAMTERGEBNIS_LOG_DIR ) ) {
		wp_mkdir_p( GESAMTERGEBNIS_LOG_DIR );
	}

	$timestamp = gmdate( 'Y-m-d H:i:s' );
	$context_s = '';

	if ( ! empty( $context ) ) {
		$context_s = ' ' . wp_json_encode( $context );
	}

	$line = sprintf( "[%s] [%s] %s%s\n", $timestamp, strtoupper( (string) $level ), $message, $context_s );

	file_put_contents( GESAMTERGEBNIS_LOG_FILE, $line, FILE_APPEND );
}

/**
 * Keep log payloads readable and bounded.
 *
 * @param string $text Value to clip.
 *
 * @return string
 */
function geg_clip_log_text( $text ) {
	$text = (string) $text;

	if ( strlen( $text ) <= 4000 ) {
		return $text;
	}

	return substr( $text, 0, 4000 ) . "\n...[truncated]";
}