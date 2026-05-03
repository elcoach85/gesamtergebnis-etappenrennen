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

register_shutdown_function( 'geg_capture_shutdown_error' );
register_activation_hook( __FILE__, 'geg_handle_activation' );

if ( is_admin() ) {
	add_action( 'admin_menu', 'geg_register_tools_pages' );
	add_action( 'admin_notices', 'geg_render_admin_notices' );
}

/**
 * Handle plugin activation diagnostics.
 */
function geg_handle_activation() {
	$checks = geg_collect_environment_checks();

	geg_write_log(
		'info',
		'Plugin activation started.',
		array(
			'php_version' => PHP_VERSION,
			'is_admin'    => is_admin(),
			'checks'      => $checks,
		)
	);

	if ( ! empty( $checks['warnings'] ) ) {
		set_transient( 'geg_admin_notices', $checks['warnings'], MINUTE_IN_SECONDS * 10 );
		geg_write_log( 'warning', 'Plugin activation completed with warnings.', array( 'warnings' => $checks['warnings'] ) );
		return;
	}

	delete_transient( 'geg_admin_notices' );
	geg_write_log( 'info', 'Plugin activation completed successfully.' );
}

/**
 * Render deferred notices gathered during activation.
 */
function geg_render_admin_notices() {
	$messages = get_transient( 'geg_admin_notices' );

	if ( empty( $messages ) || ! is_array( $messages ) ) {
		return;
	}

	delete_transient( 'geg_admin_notices' );

	foreach ( $messages as $message ) {
		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html( $message )
		);
	}
}

/**
 * Collect environment checks relevant for activation and execution.
 *
 * @return array{warnings:array<int,string>,tool_dir:string,main_ingest_exists:bool,resultsfiles_exists:bool,proc_open:bool,shell_exec:bool}
 */
function geg_collect_environment_checks() {
	$tool_dir            = realpath( GESAMTERGEBNIS_TOOL_DIR );
	$main_ingest_path    = GESAMTERGEBNIS_TOOL_DIR . 'main_ingest.py';
	$resultsfiles_path   = GESAMTERGEBNIS_TOOL_DIR . 'make_resultsfiles.py';
	$warnings            = array();
	$main_ingest_exists  = file_exists( $main_ingest_path );
	$resultsfiles_exists = file_exists( $resultsfiles_path );
	$proc_open_exists    = function_exists( 'proc_open' );
	$shell_exec_exists   = function_exists( 'shell_exec' );

	if ( false === $tool_dir ) {
		$warnings[] = 'Tool-Verzeichnis konnte nicht aufgeloest werden: ' . GESAMTERGEBNIS_TOOL_DIR;
	}

	if ( ! $main_ingest_exists ) {
		$warnings[] = 'main_ingest.py wurde nicht gefunden: ' . $main_ingest_path;
	}

	if ( ! $resultsfiles_exists ) {
		$warnings[] = 'make_resultsfiles.py wurde nicht gefunden: ' . $resultsfiles_path;
	}

	if ( ! $proc_open_exists && ! $shell_exec_exists ) {
		$warnings[] = 'Weder proc_open noch shell_exec stehen zur Verfuegung. Python-Skripte koennen so nicht gestartet werden.';
	}

	return array(
		'warnings'            => $warnings,
		'tool_dir'            => false === $tool_dir ? GESAMTERGEBNIS_TOOL_DIR : $tool_dir,
		'main_ingest_exists'  => $main_ingest_exists,
		'resultsfiles_exists' => $resultsfiles_exists,
		'proc_open'           => $proc_open_exists,
		'shell_exec'          => $shell_exec_exists,
	);
}

/**
 * Capture fatal shutdown errors for activation debugging.
 */
function geg_capture_shutdown_error() {
	$error = error_get_last();

	if ( empty( $error ) || ! is_array( $error ) ) {
		return;
	}

	$fatal_types = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR );

	if ( ! in_array( $error['type'], $fatal_types, true ) ) {
		return;
	}

	geg_write_log(
		'critical',
		'Fatal PHP shutdown error captured.',
		array(
			'type'    => $error['type'],
			'message' => $error['message'],
			'file'    => $error['file'],
			'line'    => $error['line'],
			'uri'     => isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '',
		)
	);
}

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
		if ( function_exists( 'wp_mkdir_p' ) ) {
			wp_mkdir_p( GESAMTERGEBNIS_LOG_DIR );
		} else {
			@mkdir( GESAMTERGEBNIS_LOG_DIR, 0755, true );
		}
	}

	$timestamp = gmdate( 'Y-m-d H:i:s' );
	$context_s = '';

	if ( ! empty( $context ) ) {
		$context_s = ' ' . wp_json_encode( $context );
	}

	$line = sprintf( "[%s] [%s] %s%s\n", $timestamp, strtoupper( (string) $level ), $message, $context_s );

	@file_put_contents( GESAMTERGEBNIS_LOG_FILE, $line, FILE_APPEND );
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