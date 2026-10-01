<?php
/**
 * Plugin Name:       AI Editorial Engine
 * Plugin URI:        https://github.com/ai-editorial-engine
 * Description:       محرك تحريري ذكي يستخدم Gemini وClaude وChatGPT لجلب المحتوى وإعادة صياغته بأسلوب عامية مصرية صحفية.
 * Version:           1.0.2
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            AI Editorial Engine
 * Text Domain:       ai-editorial-engine
 * Domain Path:       /languages
 * License:           GPL v2 or later
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

// Constants
define( 'AEE_VERSION',    '1.0.2' );
define( 'AEE_DB_VERSION', '1.0' );
define( 'AEE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AEE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AEE_PLUGIN_FILE', __FILE__ );

// Core includes
require_once AEE_PLUGIN_DIR . 'includes/class-aee-loader.php';
require_once AEE_PLUGIN_DIR . 'includes/class-aee-activator.php';
require_once AEE_PLUGIN_DIR . 'includes/class-aee-deactivator.php';

// API clients
require_once AEE_PLUGIN_DIR . 'includes/class-aee-gemini.php';
require_once AEE_PLUGIN_DIR . 'includes/class-aee-claude.php';
require_once AEE_PLUGIN_DIR . 'includes/class-aee-openai.php';

// Feature modules
require_once AEE_PLUGIN_DIR . 'includes/class-aee-translator.php';
require_once AEE_PLUGIN_DIR . 'includes/class-aee-content-fetcher.php';
require_once AEE_PLUGIN_DIR . 'includes/class-aee-ai-orchestrator.php';
require_once AEE_PLUGIN_DIR . 'includes/class-aee-egyptian-style.php';
require_once AEE_PLUGIN_DIR . 'includes/class-aee-brand-enforcer.php';
require_once AEE_PLUGIN_DIR . 'includes/class-aee-self-learner.php';
require_once AEE_PLUGIN_DIR . 'includes/class-aee-automation.php';

// Admin & public
require_once AEE_PLUGIN_DIR . 'admin/class-aee-admin.php';
require_once AEE_PLUGIN_DIR . 'public/class-aee-public.php';
require_once AEE_PLUGIN_DIR . 'api/class-aee-rest-api.php';

// Plugin Update Checker (GitHub)
require_once AEE_PLUGIN_DIR . 'includes/plugin-update-checker/plugin-update-checker.php';
if ( class_exists( '\\YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory' ) ) {
    $aeeUpdateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/kollectivco/kposts/',
        __FILE__,
        'ai-editorial-engine'
    );
    // Set the branch that contains the stable release
    $aeeUpdateChecker->setBranch('main');
}

/**
 * Activation hook.
 */
function activate_aee() {
    AEE_Activator::activate();
    AEE_Content_Fetcher::schedule_cron();
}

/**
 * Deactivation hook.
 */
function deactivate_aee() {
    AEE_Deactivator::deactivate();
}

register_activation_hook( AEE_PLUGIN_FILE, 'activate_aee' );
register_deactivation_hook( AEE_PLUGIN_FILE, 'deactivate_aee' );

/**
 * Main plugin bootstrap.
 */
function run_aee() {
    // Admin panel
    $admin = new AEE_Admin();
    $admin->init();

    // Public features
    $public = new AEE_Public();
    $public->init();

    // REST API
    $api = new AEE_REST_API();
    $api->init();

    // Cron automation
    AEE_Automation::register_cron_jobs();

    // Self-learning hooks
    $self_learner = new AEE_Self_Learner();
    $self_learner->init();
}
add_action( 'plugins_loaded', 'run_aee' );

/**
 * Helper: Encrypt API key for storage.
 */
function aee_encrypt( string $value ): string {
    if ( empty( $value ) ) return '';
    $key = wp_salt( 'auth' );
    $iv  = openssl_random_pseudo_bytes( 16 );
    $enc = openssl_encrypt( $value, 'AES-256-CBC', $key, 0, $iv );
    return base64_encode( $iv . $enc );
}

/**
 * Helper: Decrypt API key from storage.
 */
function aee_decrypt( string $value ): string {
    if ( empty( $value ) ) return '';
    try {
        $key  = wp_salt( 'auth' );
        $raw  = base64_decode( $value );
        $iv   = substr( $raw, 0, 16 );
        $enc  = substr( $raw, 16 );
        $dec  = openssl_decrypt( $enc, 'AES-256-CBC', $key, 0, $iv );
        return $dec !== false ? $dec : $value;
    } catch ( \Exception $e ) {
        return $value;
    }
}

/**
 * Helper: Get plugin setting.
 */
function aee_get_setting( string $key, $default = '' ) {
    $settings = get_option( 'aee_settings', [] );
    return $settings[ $key ] ?? $default;
}

/**
 * Helper: Get decrypted API key.
 */
function aee_get_api_key( string $key ): string {
    return aee_decrypt( aee_get_setting( $key, '' ) );
}
