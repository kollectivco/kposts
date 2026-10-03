<?php
/**
 * Plugin Name: Kontentainment AI Writer
 * Plugin URI:  https://kontentainment.com
 * Description: AI article rewriter with multi-provider support (Claude, ChatGPT, Gemini, DeepSeek, Mistral, Qwen), 12-source news feed, source-accurate rewriting, images, SEO, and Egyptian Arabic style.
 * Version:     6.4.0
 * Author:      Kontentainment
 * License:     GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'KAW_VERSION', '6.4.0' );
define( 'KAW_PATH', plugin_dir_path( __FILE__ ) );
define( 'KAW_URL',  plugin_dir_url( __FILE__ ) );

// ── Source registry ─────────────────────────────────────────────────────────
function kaw_sources() {
    return [
        'billboard' => [
            'label' => 'Billboard Arabia',
            'base'  => 'https://www.billboardarabia.com',
            'feed'  => 'https://www.billboardarabia.com/',
            'host'  => 'billboardarabia.com',
            'link'  => '//a[contains(@href,"/موسيقى/") or contains(@href,"/أخبار/") or contains(@href,"%D9%85%D9%88%D8%B3%D9%8A%D9%82%D9%89") or contains(@href,"-80") or contains(@href,"-81")]',
        ],
        'scene' => [
            'label' => 'SceneNow',
            'base'  => 'https://scenenow.com',
            'feed'  => 'https://scenenow.com/Buzz',
            'host'  => 'scenenow.com',
            'link'  => '//a[contains(@href,"/Buzz/") or contains(@href,"/Noise/") or contains(@href,"/Film/") or contains(@href,"/ArtsAndCulture/") or contains(@href,"/Business/")]',
        ],
        'hia' => [
            'label' => 'Hia Magazine',
            'base'  => 'https://www.hiamag.com',
            'feed'  => 'https://www.hiamag.com/',
            'host'  => 'hiamag.com',
            'link'  => '//h2/a | //h3/a | //a[contains(@class,"title")]',
        ],
        'list' => [
            'label' => 'List Magazine',
            'base'  => 'https://www.listmag.com',
            'feed'  => 'https://www.listmag.com/en',
            'host'  => 'listmag.com',
            'link'  => '//h2/a | //h3/a | //h4/a | //article//a | //a[contains(@class,"title")] | //a[contains(@class,"card")]',
        ],
        'scoop' => [
            'label' => 'Scoop Empire',
            'base'  => 'https://scoopempire.com',
            'feed'  => 'https://scoopempire.com/category/news-politics/',
            'host'  => 'scoopempire.com',
            'link'  => '//h2/a | //h3/a',
        ],
        'lovin_ar' => [
            'label' => 'Lovin Cairo (AR)',
            'base'  => 'https://lovin.co',
            'feed'  => 'https://lovin.co/cairo/ar/',
            'host'  => 'lovin.co',
            'link'  => '//h2/a | //h3/a | //h4/a | //article//a | //a[contains(@class,"title")] | //a[contains(@class,"card")]',
        ],
        'lovin_en' => [
            'label' => 'Lovin Cairo (EN)',
            'base'  => 'https://lovin.co',
            'feed'  => 'https://lovin.co/cairo/en/',
            'host'  => 'lovin.co',
            'link'  => '//h2/a | //h3/a | //h4/a | //article//a | //a[contains(@class,"title")] | //a[contains(@class,"card")]',
        ],
        'filgoal' => [
            'label' => 'FilGoal',
            'base'  => 'https://www.filgoal.com',
            'feed'  => 'https://www.filgoal.com/',
            'host'  => 'filgoal.com',
            'link'  => '//h2/a | //h3/a | //a[contains(@href,"/articles/")] | //a[contains(@href,"/news/")]',
        ],
        'filfan' => [
            'label' => 'FilFan',
            'base'  => 'https://www.filfan.com',
            'feed'  => 'https://www.filfan.com/',
            'host'  => 'filfan.com',
            'link'  => '//h2/a | //h3/a | //a[contains(@href,"/news/")] | //a[contains(@href,"/article")]',
        ],
        'elfasla' => [
            'label' => 'ElFasla',
            'base'  => 'https://elfaslaonline.com',
            'feed'  => 'https://elfaslaonline.com/Buzz',
            'host'  => 'elfaslaonline.com',
            'link'  => '//a[contains(@href,"/Buzz/") or contains(@href,"/In-Depth/") or contains(@href,"/Interviews/") or contains(@href,"/ArtsAndCulture/") or contains(@href,"/Listicles/") or contains(@href,"/Events/")]',
        ],
        'cairo360' => [
            'label' => 'Cairo 360',
            'base'  => 'https://cairo360.com',
            'feed'  => 'https://cairo360.com/features/',
            'host'  => 'cairo360.com',
            'link'  => '//h2/a | //h3/a | //a[contains(@href,"/article/")] | //a[contains(@class,"title")]',
        ],
        'theglocal' => [
            'label' => 'The Glocal',
            'base'  => 'https://theglocal.com',
            'feed'  => 'https://theglocal.com/',
            'host'  => 'theglocal.com',
            'link'  => '//h2/a | //h3/a | //h4/a | //article//a | //a[contains(@class,"title")] | //a[contains(@class,"card")]',
        ],
    ];
}

function kaw_allowed_hosts() {
    $hosts = [];
    foreach ( kaw_sources() as $s ) $hosts[] = $s['host'];
    return array_unique( $hosts );
}

// ── AI providers registry ────────────────────────────────────────────────────
// key => [ label, endpoint, default_model, option_key (for API key), format ]
// format: 'anthropic' | 'openai' (OpenAI-compatible chat/completions)
function kaw_providers() {
    return [
        'claude' => [
            'label'   => 'Claude (Anthropic)',
            'endpoint'=> 'https://api.anthropic.com/v1/messages',
            'model'   => 'claude-sonnet-4-20250514',
            'optkey'  => 'kaw_key_claude',
            'format'  => 'anthropic',
        ],
        'openai' => [
            'label'   => 'ChatGPT (OpenAI)',
            'endpoint'=> 'https://api.openai.com/v1/chat/completions',
            'model'   => 'gpt-4o',
            'optkey'  => 'kaw_key_openai',
            'format'  => 'openai',
        ],
        'gemini' => [
            'label'   => 'Gemini (Google)',
            'endpoint'=> 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
            'model'   => 'gemini-3.8-flash',
            'optkey'  => 'kaw_key_gemini',
            'format'  => 'openai',
        ],
        'deepseek' => [
            'label'   => 'DeepSeek',
            'endpoint'=> 'https://api.deepseek.com/chat/completions',
            'model'   => 'deepseek-chat',
            'optkey'  => 'kaw_key_deepseek',
            'format'  => 'openai',
        ],
        'mistral' => [
            'label'   => 'Mistral',
            'endpoint'=> 'https://api.mistral.ai/v1/chat/completions',
            'model'   => 'mistral-large-latest',
            'optkey'  => 'kaw_key_mistral',
            'format'  => 'openai',
        ],
        'qwen' => [
            'label'   => 'Qwen (Alibaba)',
            'endpoint'=> 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/chat/completions',
            'model'   => 'qwen-max',
            'optkey'  => 'kaw_key_qwen',
            'format'  => 'openai',
        ],
        'poe' => [
            'label'   => 'Poe',
            'endpoint'=> 'https://api.poe.com/v1/responses',
            'model'   => 'Claude-Sonnet-4.5',
            'optkey'  => 'kaw_key_poe',
            'format'  => 'poe',
        ],
    ];
}

function kaw_saved_provider_model( $provider_key, $provider ) {
    $option_name = $provider['optkey'] . '_model';
    $model = get_option($option_name, $provider['model']);

    // Gemini 2.0 Flash was shut down; replace only this known retired default.
    if ( $provider_key === 'gemini' && $model === 'gemini-2.0-flash' ) {
        $model = $provider['model'];
        update_option($option_name, $model);
    }

    return $model;
}

// Resolve the active provider with key + model filled in
function kaw_active_provider() {
    $providers = kaw_providers();
    $key = get_option('kaw_default_provider', 'claude');
    if ( ! isset($providers[$key]) ) $key = 'claude';
    $p = $providers[$key];
    $p['key']   = get_option($p['optkey'], '');
    if ( $key === 'claude' && empty($p['key']) ) $p['key'] = get_option('kaw_api_key', '');
    $p['model'] = kaw_saved_provider_model($key, $p);
    $p['pkey']  = $key;
    return $p;
}

function kaw_has_any_key() {
    $ap = kaw_active_provider();
    return ! empty($ap['key']);
}

// ── Admin menu ────────────────────────────────────────────────────────────────

add_action( 'admin_menu', function () {
    add_menu_page( 'AI Writer', 'AI Writer', 'edit_posts', 'kaw-writer', 'kaw_render_writer_page', 'dashicons-edit-large', 6 );
    add_submenu_page( 'kaw-writer', 'Write Article', 'Write Article', 'edit_posts',     'kaw-writer',   'kaw_render_writer_page' );
    add_submenu_page( 'kaw-writer', 'News Feed',     'News Feed',     'edit_posts',     'kaw-newsfeed', 'kaw_render_newsfeed_page' );
    add_submenu_page( 'kaw-writer', 'Settings',      'Settings',      'manage_options', 'kaw-settings', 'kaw_render_settings_page' );
});

// ── Settings ──────────────────────────────────────────────────────────────────

add_action( 'admin_init', function () {
    register_setting( 'kaw_settings_group', 'kaw_api_key', [ 'sanitize_callback' => 'sanitize_text_field' ] ); // legacy
    register_setting( 'kaw_settings_group', 'kaw_default_provider', [ 'sanitize_callback' => 'sanitize_text_field' ] );
    foreach ( kaw_providers() as $pkey => $p ) {
        register_setting( 'kaw_settings_group', $p['optkey'], [ 'sanitize_callback' => 'sanitize_text_field' ] );
        register_setting( 'kaw_settings_group', $p['optkey'] . '_model', [ 'sanitize_callback' => 'sanitize_text_field' ] );
    }
});

function kaw_render_settings_page() {
    $providers = kaw_providers();
    $default   = get_option('kaw_default_provider', 'claude');
    // Migrate legacy Anthropic key if present and Claude key empty
    if ( empty(get_option('kaw_key_claude')) && ! empty(get_option('kaw_api_key')) ) {
        update_option('kaw_key_claude', get_option('kaw_api_key'));
    }
    $docs = [
        'claude'   => 'console.anthropic.com',
        'openai'   => 'platform.openai.com/api-keys',
        'gemini'   => 'aistudio.google.com/apikey',
        'deepseek' => 'platform.deepseek.com',
        'mistral'  => 'console.mistral.ai',
        'qwen'     => 'modelstudio.console.alibabacloud.com',
        'poe'      => 'poe.com/api_key',
    ];
    ?>
    <div class="wrap">
        <h1>AI Writer — Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'kaw_settings_group' ); ?>

            <h2 style="margin-top:1.5rem;">Default AI Provider</h2>
            <p class="description">The provider used to write articles. You only need to fill the API key for the provider(s) you want to use.</p>
            <table class="form-table">
                <tr>
                    <th><label for="kaw_default_provider">Provider</label></th>
                    <td>
                        <select id="kaw_default_provider" name="kaw_default_provider" class="regular-text">
                            <?php foreach ( $providers as $pkey => $p ) : ?>
                            <option value="<?php echo esc_attr($pkey); ?>" <?php selected($default, $pkey); ?>><?php echo esc_html($p['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>

            <h2 style="margin-top:2rem;">API Keys & Models</h2>
            <table class="form-table">
                <?php foreach ( $providers as $pkey => $p ) :
                    $key   = get_option($p['optkey'], '');
                    $model = kaw_saved_provider_model($pkey, $p);
                ?>
                <tr>
                    <th>
                        <label for="<?php echo esc_attr($p['optkey']); ?>"><?php echo esc_html($p['label']); ?></label>
                        <?php if ( !empty($key) ) : ?><br><span style="color:#46834e;font-size:11px;">● connected</span><?php endif; ?>
                    </th>
                    <td>
                        <input type="password" id="<?php echo esc_attr($p['optkey']); ?>" name="<?php echo esc_attr($p['optkey']); ?>"
                               value="<?php echo esc_attr($key); ?>" class="regular-text" autocomplete="off"
                               placeholder="API key" />
                        <br>
                        <input type="text" name="<?php echo esc_attr($p['optkey']); ?>_model"
                               value="<?php echo esc_attr($model); ?>" class="regular-text" style="margin-top:6px;"
                               placeholder="model id" />
                        <p class="description">Key: <a href="https://<?php echo esc_attr($docs[$pkey] ?? ''); ?>" target="_blank"><?php echo esc_html($docs[$pkey] ?? ''); ?></a> · Default model: <code><?php echo esc_html($p['model']); ?></code></p>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>

            <?php submit_button( 'Save Settings' ); ?>
        </form>
    </div>
<?php }

// ── Enqueue assets ────────────────────────────────────────────────────────────

add_action( 'admin_enqueue_scripts', function ( $hook ) {
    $pages = [ 'toplevel_page_kaw-writer', 'ai-writer_page_kaw-newsfeed', 'post.php', 'post-new.php' ];
    if ( ! in_array( $hook, $pages ) ) return;
    wp_enqueue_style(  'kaw-style',  KAW_URL . 'css/writer.css', [], KAW_VERSION );
    wp_enqueue_script( 'kaw-script', KAW_URL . 'js/writer.js',  [ 'jquery' ], KAW_VERSION, true );
    wp_localize_script( 'kaw-script', 'KAW', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'kaw_nonce' ),
        'has_key'  => kaw_has_any_key(),
        'page'     => strpos( $hook, 'newsfeed' ) !== false ? 'newsfeed' : 'writer',
    ]);
});

// ── Shared UI partials ─────────────────────────────────────────────────────────

// Article types: key => [ emoji, label, Arabic descriptor for the prompt ]
function kaw_types() {
    return [
        'News'      => [ '📰', 'أخبار',       'خبر صحفي مباشر — الأهم في الأول، بأسلوب سريع وواضح' ],
        'Feed'      => [ '⚡', 'الفيد',        'بوست قصير وسريع للفيد، نبرة كاجوال وجذابة' ],
        'Reviews'   => [ '⭐', 'مراجعات',     'مراجعة نقدية لعمل فني (ألبوم/أغنية/فيلم/مسلسل) برأي واضح وتقييم مدعوم بأمثلة' ],
        'Coverage'  => [ '🎤', 'تغطية',       'تغطية حدث أو حفلة أو مهرجان — أجواء وتفاصيل ولحظات مهمة' ],
        'Travel'    => [ '✈️', 'سفر',          'مقال سفر — أماكن وتجارب وتوصيات بأسلوب حي' ],
        'Cinema'    => [ '🎬', 'سيما',         'مقال سينمائي عن فيلم أو مسلسل أو صناعة السينما' ],
        'Watch'     => [ '👀', 'شوف',          'توصية بمحتوى يتفرّج عليه — ليه يستاهل، باختصار ومن غير حرق' ],
        'ArtsCult'  => [ '🎨', 'فن وثقافة',   'مقال فن وثقافة — معارض، فنون بصرية، أدب، ظواهر ثقافية' ],
        'Features'  => [ '📝', 'فيتشرز',       'مقال معمّق طويل (feature) بزوايا متعددة وتحليل' ],
        'Lists'     => [ '📋', 'ليستات',       'قائمة مرقّمة (Listicle) بعناصر واضحة وفقرة قصيرة لكل عنصر' ],
        'Mazzika'   => [ '🎵', 'مزيكا',        'مقال موسيقي عن إصدار أو فنان أو مشهد موسيقي' ],
    ];
}

// Tones: key => [ English label, Arabic descriptor for the prompt ]
function kaw_tones() {
    return [
        'Hype'        => [ 'Hype',        'حماسي ومشحون بالطاقة، يخلّي القارئ متشوّق' ],
        'Critical'    => [ 'Critical',    'نقدي ومتوازن، بيوزن الإيجابيات والسلبيات بصراحة' ],
        'Chill'       => [ 'Chill',       'هادي ومريح، نبرة كاجوال خفيفة' ],
        'Sarcastic'   => [ 'Sarcastic',   'ساخر وخفيف الظل، بسخرية ذكية من غير ابتذال أو إهانة' ],
        'Emotional'   => [ 'Emotional',   'عاطفي ومؤثر، بيلمس مشاعر القارئ من غير مبالغة' ],
        'Informative' => [ 'Informative', 'معلوماتي وواضح، بيوصّل المعلومة بدقة وترتيب' ],
        'Bold'        => [ 'Bold',        'جريء وصريح، بيقول رأيه بوضوح ومن غير لف ودوران' ],
    ];
}

function kaw_type_grid( $gridId, $active ) { ?>
    <div class="kaw-type-grid" id="<?php echo $gridId; ?>">
        <?php foreach ( kaw_types() as $key => $t ) : ?>
        <button class="kaw-type-btn<?php echo $key === $active ? ' active' : ''; ?>" data-type="<?php echo esc_attr($key); ?>"><?php echo $t[0] . ' ' . esc_html($t[1]); ?></button>
        <?php endforeach; ?>
    </div>
<?php }

function kaw_tone_row( $rowId, $active ) { ?>
    <div class="kaw-tone-row" id="<?php echo $rowId; ?>">
        <?php foreach ( kaw_tones() as $key => $t ) : ?>
        <span class="kaw-chip<?php echo $key === $active ? ' active' : ''; ?>" data-tone="<?php echo esc_attr($key); ?>"><?php echo esc_html($t[0]); ?></span>
        <?php endforeach; ?>
    </div>
<?php }

function kaw_lang_field( $prefix ) { ?>
    <div class="kaw-label" style="margin-top:14px;margin-bottom:6px;">Language</div>
    <div class="kaw-tone-row" id="<?php echo $prefix; ?>-lang-row">
        <span class="kaw-chip active" data-lang="Arabic">عربي</span>
        <span class="kaw-chip" data-lang="English">English</span>
    </div>
<?php }

// ── Writer page ───────────────────────────────────────────────────────────────

function kaw_render_writer_page() {
    $has_key = kaw_has_any_key();
    ?>
    <div class="wrap kaw-wrap">
        <div class="kaw-header">
            <span class="kaw-logo">Kontentainment</span>
            <span class="kaw-badge">AI Writer</span>
            <a href="<?php echo admin_url('admin.php?page=kaw-newsfeed'); ?>" class="kaw-nav-link">News Feed →</a>
            <span class="kaw-provider-badge" title="Active AI provider"><?php echo esc_html( kaw_active_provider()['label'] ); ?></span>
        </div>

        <?php if ( ! $has_key ) : ?>
        <div class="kaw-notice">⚠️ No API key. <a href="<?php echo admin_url('admin.php?page=kaw-settings'); ?>">Add your Anthropic API key</a> to get started.</div>
        <?php endif; ?>

        <div class="kaw-layout">
            <div class="kaw-panel kaw-controls">

                <div class="kaw-field">
                    <label class="kaw-label">Article type</label>
                    <?php kaw_type_grid('kaw-type-grid', 'News'); ?>
                </div>

                <div class="kaw-field">
                    <label class="kaw-label" for="kaw-subject">Subject / Headline</label>
                    <input type="text" id="kaw-subject" class="kaw-input" placeholder="e.g. Marwan Pablo's new EP '5 NZAM'" />
                </div>

                <div class="kaw-field">
                    <label class="kaw-label" for="kaw-notes">Key points / angle <span class="kaw-optional">(optional)</span></label>
                    <textarea id="kaw-notes" class="kaw-input kaw-textarea" placeholder="e.g. focus on production style, compare to older work..."></textarea>
                </div>

                <div class="kaw-field">
                    <label class="kaw-label">Tone</label>
                    <?php kaw_tone_row('kaw-tone-row', 'Informative'); ?>
                    <?php kaw_lang_field('kaw'); ?>
                </div>

                <div class="kaw-field">
                    <label class="kaw-label">Word count: <strong id="kaw-wc-display">350</strong></label>
                    <input type="range" id="kaw-wordcount" min="150" max="5000" step="50" value="350" class="kaw-range" />
                </div>

                <div class="kaw-field">
                    <label class="kaw-checkbox-row">
                        <input type="checkbox" id="kaw-seo-toggle" checked /> Generate SEO (title, meta, tags)
                    </label>
                </div>

                <button id="kaw-generate-btn" class="kaw-generate-btn" <?php echo $has_key ? '' : 'disabled'; ?>>
                    Write article
                </button>
            </div>

            <div class="kaw-panel kaw-output-panel">
                <div class="kaw-output-header">
                    <span class="kaw-label" style="margin:0;">Generated article</span>
                    <div class="kaw-output-actions">
                        <span id="kaw-wc-count" class="kaw-wc-count"></span>
                        <button id="kaw-copy-btn" class="kaw-action-btn" style="display:none;">Copy</button>
                        <button id="kaw-insert-btn" class="kaw-action-btn kaw-insert-btn" style="display:none;">Insert to new post ↗</button>
                    </div>
                </div>
                <div id="kaw-seo-box" class="kaw-seo-box" style="display:none;"></div>
                <div id="kaw-output" class="kaw-output kaw-empty">
                    <span class="kaw-placeholder">Your article will appear here...</span>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// ── News Feed page ────────────────────────────────────────────────────────────

function kaw_render_newsfeed_page() {
    $has_key = kaw_has_any_key();
    ?>
    <div class="wrap kaw-wrap">
        <div class="kaw-header">
            <span class="kaw-logo">Kontentainment</span>
            <span class="kaw-badge">News Feed</span>
            <a href="<?php echo admin_url('admin.php?page=kaw-writer'); ?>" class="kaw-nav-link">← Write Article</a>
            <span class="kaw-provider-badge" title="Active AI provider"><?php echo esc_html( kaw_active_provider()['label'] ); ?></span>
        </div>

        <?php if ( ! $has_key ) : ?>
        <div class="kaw-notice">⚠️ No API key. <a href="<?php echo admin_url('admin.php?page=kaw-settings'); ?>">Add your Anthropic API key</a> to write articles.</div>
        <?php endif; ?>

        <div class="kaw-feed-layout">

            <div class="kaw-panel kaw-feed-panel">
                <div class="kaw-source-row">
                    <button class="kaw-src-btn active" data-src="all">All</button>
                    <?php foreach ( kaw_sources() as $skey => $sval ) : ?>
                    <button class="kaw-src-btn" data-src="<?php echo esc_attr($skey); ?>"><?php echo esc_html($sval['label']); ?></button>
                    <?php endforeach; ?>
                    <button class="kaw-refresh-btn" id="kaw-refresh-btn">↻ Refresh</button>
                </div>
                <div id="kaw-news-list" class="kaw-news-list">
                    <div class="kaw-skeleton"></div><div class="kaw-skeleton"></div>
                    <div class="kaw-skeleton"></div><div class="kaw-skeleton"></div>
                    <div class="kaw-skeleton"></div>
                </div>
            </div>

            <div class="kaw-feed-right">

                <div class="kaw-panel kaw-pick-prompt" id="kaw-pick-prompt">
                    <span class="kaw-placeholder">← Pick a story from the feed to write about it</span>
                </div>

                <div class="kaw-panel kaw-write-bar" id="kaw-write-bar" style="display:none;">
                    <div class="kaw-label" style="margin-bottom:6px;">Writing about</div>
                    <div class="kaw-selected-title" id="kaw-sel-title"></div>
                    <a class="kaw-src-link" id="kaw-sel-link" href="#" target="_blank">View original story ↗</a>

                    <div id="kaw-img-preview" class="kaw-img-preview" style="display:none;">
                        <img id="kaw-img-tag" src="" alt="" />
                        <label class="kaw-checkbox-row" style="margin-top:6px;">
                            <input type="checkbox" id="kaw-use-image" checked /> Use as featured image
                        </label>
                    </div>

                    <div class="kaw-fetch-status" id="kaw-fetch-status"></div>

                    <div class="kaw-label" style="margin-top:14px;margin-bottom:6px;">Article type</div>
                    <?php kaw_type_grid('kaw-feed-type-grid', 'News'); ?>

                    <div class="kaw-label" style="margin-top:14px;margin-bottom:6px;">Tone</div>
                    <?php kaw_tone_row('kaw-feed-tone-row', 'Informative'); ?>

                    <?php kaw_lang_field('kaw-feed'); ?>

                    <div class="kaw-label" style="margin-top:14px;margin-bottom:4px;">
                        Word count: <strong id="kaw-feed-wc-display">350</strong>
                    </div>
                    <input type="range" id="kaw-feed-wordcount" min="150" max="5000" step="50" value="350" class="kaw-range" style="margin-bottom:12px;" />

                    <label class="kaw-checkbox-row" style="margin-bottom:10px;">
                        <input type="checkbox" id="kaw-feed-seo-toggle" checked /> Generate SEO (title, meta, tags)
                    </label>

                    <textarea id="kaw-feed-notes" class="kaw-input kaw-textarea"
                              placeholder="Extra angle or instructions (optional)..."
                              style="margin-bottom:12px;min-height:60px;"></textarea>

                    <button id="kaw-feed-generate-btn" class="kaw-generate-btn" <?php echo $has_key ? '' : 'disabled'; ?>>
                        Write article
                    </button>
                </div>

                <div class="kaw-panel kaw-output-panel" id="kaw-feed-output-panel" style="display:none;">
                    <div class="kaw-output-header">
                        <span class="kaw-label" style="margin:0;">Generated article</span>
                        <div class="kaw-output-actions">
                            <span id="kaw-feed-wc-count" class="kaw-wc-count"></span>
                            <button id="kaw-feed-copy-btn" class="kaw-action-btn">Copy</button>
                            <button id="kaw-feed-insert-btn" class="kaw-action-btn kaw-insert-btn">Insert to new post ↗</button>
                        </div>
                    </div>
                    <div id="kaw-feed-seo-box" class="kaw-seo-box" style="display:none;"></div>
                    <div id="kaw-feed-output" class="kaw-output"></div>
                </div>

            </div>
        </div>
    </div>
    <?php
}

// ── AJAX: Fetch news list ─────────────────────────────────────────────────────

add_action( 'wp_ajax_kaw_fetch_news', 'kaw_ajax_fetch_news' );

function kaw_ajax_fetch_news() {
    check_ajax_referer( 'kaw_nonce', 'nonce' );
    if ( ! current_user_can('edit_posts') ) wp_send_json_error('Permission denied.');

    $registry  = kaw_sources();
    $requested = isset($_POST['sources']) ? array_map('sanitize_text_field', (array) $_POST['sources']) : array_keys($registry);
    $force     = ( $_POST['force'] ?? '' ) === '1';

    // Build the list of sources to fetch
    $targets = [];
    foreach ( $registry as $key => $src ) {
        if ( in_array($key, $requested, true) ) $targets[$key] = $src;
    }

    $all    = [];
    $to_net = [];

    // Serve from cache first (15 min) unless force-refresh
    foreach ( $targets as $key => $src ) {
        $cached = get_transient( 'kaw_news_' . $key );
        if ( ! $force && $cached !== false ) {
            $all = array_merge( $all, $cached );
        } else {
            $to_net[$key] = $src;
        }
    }

    // Fetch remaining sources in PARALLEL
    if ( ! empty($to_net) ) {
        $requests = [];
        foreach ( $to_net as $key => $src ) {
            $requests[$key] = [
                'url'     => $src['feed'],
                'type'    => 'GET',
                'headers' => [ 'User-Agent' => 'Mozilla/5.0 (compatible; KontentainmentBot/5.0)' ],
            ];
        }

        $options   = [ 'timeout' => 12 ];
        $responses = [];

        // WpOrg\Requests (WP 6.2+) or legacy Requests
        if ( class_exists('\WpOrg\Requests\Requests') ) {
            $responses = \WpOrg\Requests\Requests::request_multiple( $requests, $options );
        } elseif ( class_exists('Requests') ) {
            $responses = Requests::request_multiple( $requests, $options );
        } else {
            // Fallback: sequential with short timeout
            foreach ( $to_net as $key => $src ) {
                $r = wp_remote_get( $src['feed'], [ 'timeout' => 8, 'user-agent' => 'Mozilla/5.0 (compatible; KontentainmentBot/5.0)' ] );
                $responses[$key] = is_wp_error($r) ? null : (object) [ 'body' => wp_remote_retrieve_body($r) ];
            }
        }

        foreach ( $to_net as $key => $src ) {
            $resp = $responses[$key] ?? null;
            if ( ! $resp || empty($resp->body) ) { set_transient('kaw_news_' . $key, [], 300); continue; }
            $items = kaw_parse_news( $resp->body, $key, $src );
            set_transient( 'kaw_news_' . $key, $items, 900 ); // cache 15 min
            $all = array_merge( $all, $items );
        }
    }

    wp_send_json_success( $all );
}

function kaw_parse_news( $html, $source, $src ) {
    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ) );
    libxml_clear_errors();
    $xpath = new DOMXPath($doc);

    $nodes = $xpath->query( $src['link'] ?: '//h2/a | //h3/a' );
    $items = []; $seen = [];

    if ( $nodes ) foreach ( $nodes as $node ) {
        $title = trim( preg_replace('/\s+/', ' ', $node->textContent) );
        $href  = $node->getAttribute('href');
        if ( mb_strlen($title) < 12 || mb_strlen($title) > 200 ) continue;
        if ( empty($href) || $href === '#' ) continue;

        // Build absolute URL
        if ( strpos($href, 'http') === 0 ) {
            $url = $href;
        } elseif ( strpos($href, '//') === 0 ) {
            $url = 'https:' . $href;
        } else {
            $url = rtrim($src['base'], '/') . '/' . ltrim($href, '/');
        }

        // Skip obvious non-articles
        if ( preg_match('#/(tag|category|author|page|search|login|subscribe)/#i', $url) ) continue;

        $hash = md5($title);
        if ( isset($seen[$hash]) ) continue;
        $seen[$hash] = true;

        $items[] = [ 'title' => $title, 'url' => $url, 'source' => $source, 'label' => $src['label'] ];
        if ( count($items) >= 40 ) break;
    }
    return $items;
}

// ── AJAX: Fetch article content + image ───────────────────────────────────────

add_action( 'wp_ajax_kaw_fetch_article', 'kaw_ajax_fetch_article' );

function kaw_ajax_fetch_article() {
    check_ajax_referer( 'kaw_nonce', 'nonce' );
    if ( ! current_user_can('edit_posts') ) wp_send_json_error('Permission denied.');

    $url = esc_url_raw( $_POST['url'] ?? '' );
    if ( empty($url) ) wp_send_json_error('No URL provided.');

    $allowed = kaw_allowed_hosts();
    $host = preg_replace('/^www\./', '', parse_url($url, PHP_URL_HOST));
    if ( ! in_array($host, $allowed) ) wp_send_json_error('Domain not allowed.');

    $resp = wp_remote_get( $url, [ 'timeout' => 20, 'user-agent' => 'Mozilla/5.0 (compatible; KontentainmentBot/4.0)' ] );
    if ( is_wp_error($resp) ) wp_send_json_error( $resp->get_error_message() );

    $html = wp_remote_retrieve_body($resp);
    if ( empty($html) ) wp_send_json_error('Empty page.');

    $content = kaw_extract_article_content($html, $host);
    $image   = kaw_extract_og_image($html);

    if ( empty($content) || strlen($content) < 100 ) {
        wp_send_json_error('Could not extract article content.');
    }

    wp_send_json_success([
        'content' => $content,
        'length'  => strlen($content),
        'image'   => $image,
    ]);
}

function kaw_extract_og_image( $html ) {
    if ( preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m) ) {
        return esc_url_raw($m[1]);
    }
    if ( preg_match('/<meta[^>]+name=["\']twitter:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m) ) {
        return esc_url_raw($m[1]);
    }
    return '';
}

function kaw_extract_article_content( $html, $host ) {
    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadHTML( mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8') );
    libxml_clear_errors();
    $xpath = new DOMXPath($doc);

    $selectors = [
        '//*[contains(@class,"article-body")]',
        '//*[contains(@class,"entry-content")]',
        '//*[contains(@class,"post-content")]',
        '//*[contains(@class,"content-body")]',
        '//*[contains(@class,"article-content")]',
        '//article',
        '//*[@id="content"]',
    ];

    foreach ( $selectors as $sel ) {
        $nodes = $xpath->query($sel);
        if ( $nodes && $nodes->length > 0 ) {
            $text = kaw_node_to_text($nodes->item(0), $xpath);
            if ( strlen(trim($text)) > 150 ) return trim($text);
        }
    }

    $paras = $xpath->query('//p'); $text = '';
    foreach ( $paras as $p ) {
        $t = trim($p->textContent);
        if ( strlen($t) > 40 ) $text .= $t . "\n\n";
    }
    return trim($text);
}

function kaw_node_to_text( $node, $xpath ) {
    $remove = $xpath->query('.//nav | .//script | .//style | .//aside | .//*[contains(@class,"social")] | .//*[contains(@class,"share")] | .//*[contains(@class,"related")]', $node);
    if ( $remove ) foreach ( iterator_to_array($remove) as $n ) {
        if ( $n->parentNode ) $n->parentNode->removeChild($n);
    }
    $paras = $xpath->query('.//p | .//h2 | .//h3 | .//li', $node);
    $chunks = [];
    if ( $paras && $paras->length > 0 ) {
        foreach ( $paras as $p ) {
            $t = trim( preg_replace('/\s+/', ' ', $p->textContent) );
            if ( strlen($t) > 20 ) $chunks[] = $t;
        }
        return implode("\n\n", $chunks);
    }
    return trim( preg_replace('/\s+/', ' ', $node->textContent) );
}

// ── AJAX: Generate / Rewrite article ─────────────────────────────────────────

add_action( 'wp_ajax_kaw_generate', 'kaw_ajax_generate' );

function kaw_ajax_generate() {
    check_ajax_referer( 'kaw_nonce', 'nonce' );
    if ( ! current_user_can('edit_posts') ) wp_send_json_error('Permission denied.');

    $provider        = kaw_active_provider();
    $api_key         = $provider['key'];
    $type            = sanitize_text_field( $_POST['type']            ?? 'News' );
    $subject         = sanitize_text_field( $_POST['subject']         ?? '' );
    $notes           = sanitize_textarea_field( $_POST['notes']       ?? '' );
    $tone            = sanitize_text_field( $_POST['tone']            ?? 'Informative' );
    $lang            = sanitize_text_field( $_POST['lang']            ?? 'Arabic' );
    $wordcount       = intval( $_POST['wordcount']                    ?? 350 );
    $source_label    = sanitize_text_field( $_POST['source']         ?? '' );
    $article_content = sanitize_textarea_field( $_POST['article_content'] ?? '' );
    $want_seo        = ( $_POST['seo'] ?? '' ) === '1';

    if ( empty($api_key) )  wp_send_json_error('No API key for ' . $provider['label'] . '. Go to AI Writer → Settings.');
    if ( empty($subject) )  wp_send_json_error('Please enter a subject.');

    // Enforce the same bounds as the UI; AJAX input can be changed by the caller.
    $types = kaw_types();
    $tones = kaw_tones();
    if ( ! isset($types[$type]) ) $type = 'News';
    if ( ! isset($tones[$tone]) ) $tone = 'Informative';
    if ( ! in_array($lang, [ 'Arabic', 'English' ], true) ) $lang = 'Arabic';
    $wordcount = max(150, min(5000, $wordcount));

    $lang_instruction = $lang === 'English'
        ? "Write the entire article in English."
        : "اكتب المقال كامل باللهجة المصرية العامية بأسلوب صحفي مودرن — مش فصحى رسمية جامدة ومش لغة شارع مبتذلة، لكن نبرة مجلة ترفيه شبابية محترفة وكول، زي ما بيتكلم محرر شاطر فاهم في الموسيقى والسينما وبيكتب لجمهور شبابي مصري بيفهم في الكلتشر.

صوت Kontentainment (الأهم):
- اكتب كإنك صاحب فاهم بيحكي لصحابه، واثق ومطّلع، مش بتلقّن ولا بتتفلسف.
- خلي عندك وجهة نظر وشخصية في الكتابة — القارئ المفروض يحس إن وراها إنسان ليه ذوق ورأي، مش روبوت بيلخّص.
- الذكاء في التفاصيل: امسك التفصيلة اللي بتفرق وركّز عليها، مش بتسرد كل حاجة بالتساوي.
- كول بطبيعتك مش بالعافية: متستخدمش سلانج مفتعل ولا إيموچيز ولا علامات تعجب كتير عشان تبان شبابي.

مبادئ الكتابة:
- ابدأ بافتتاحية (hook) تشد القارئ من أول سطر — مفاجأة، سؤال، أو جملة جريئة. متبدأش بتمهيد ممل.
- جمل قصيرة ومتوسطة بإيقاع سريع. نوّع طول الجمل عشان الإيقاع ميبقاش ممل.
- فقرات قصيرة (2-4 جمل)، كل فقرة ليها فكرة واحدة واضحة.
- رأي واضح وجريء لما يلزم، مدعوم بمثال أو سبب — مش مجرد رأي معلّق في الهوا.
- اقفل المقال بجملة قوية تسيب أثر، مش تلخيص باهت لكل اللي فات.

ممنوعات الأسلوب:
- بلاش كليشيهات وعبارات مستهلكة زي 'يُذكر أن'، 'جدير بالذكر'، 'في خطوة هي الأولى من نوعها'، 'عالم لا يعرف المستحيل'.
- بلاش لغة إعلانات مفرغة أو مبالغات فاضية ('الأفضل على الإطلاق' من غير سبب).
- بلاش حشو أو جمل بتكرر نفس المعلومة بصيغة تانية.
- بلاش نبرة بيان صحفي رسمي — احنا مجلة ترفيه مش وكالة أنباء.

قواعد الأسماء (حسب الأشهر والمتعارف عليه):
- أسماء الفنانين المصريين/العرب اللي ليهم صيغة عربية مشهورة اكتبها بالعربي (مروان بابلو، ويجز، كايروكي، أبيوسف، مروان موسى).
- أسماء البراندات والمنصات والفعاليات والشركات سيبها بالإنجليزي زي ما هي (Spotify, Netflix, Coachella, Anghami, GITEX).
- أسماء الأغاني والأفلام سيبها بصيغتها الأصلية الأشهر اللي الجمهور بيعرفها بيها.
- القاعدة الأساسية: استخدم دايماً الصيغة اللي الجمهور المصري متعود يشوفها، مش ترجمة حرفية.";

    $system = "You are a writer for Kontentainment, an Egyptian entertainment magazine covering music, film, pop culture, and nightlife across the Arab world. Your voice is sharp, culturally informed, and cool — like a knowledgeable friend obsessed with music and film.

{$lang_instruction}

STRICT ACCURACY RULES:
- Only use facts and information present in the SOURCE CONTENT provided.
- Do NOT add facts, statistics, quotes, names, or details not in the source.
- Do NOT hallucinate or invent any information.
- Rewrite the content in Kontentainment's voice and style.
- Start with a strong headline on the first line and a concise subheadline on the second line. Put a blank line after the subheadline, then start the article body.
- Divide the body into clear, logical sections. Put a short, descriptive section heading on its own line before each major section, using exactly `## Heading` syntax. Do not add a heading to every paragraph.
- Use natural paragraph breaks. Do not use bold (**), and do not prefix the headline or subheadline with Markdown symbols.
- Be specific and direct. No filler.
- Match the target word count as closely as possible.";

    // Inject article-type and tone guidance from the registries
    if ( isset($types[$type]) ) {
        $system .= "\n\nنوع المقال المطلوب: {$types[$type][1]} — {$types[$type][2]}.";
    }
    if ( isset($tones[$tone]) ) {
        $system .= "\nالنبرة المطلوبة: {$tones[$tone][0]} — {$tones[$tone][1]}.";
    }

    if ( $want_seo ) {
        $seo_lang_note = $lang === 'Arabic'
            ? "For Arabic articles: write TITLE and META in Egyptian Arabic, write TAGS in Arabic (you may keep brand/event names in English), but ALWAYS write SLUG in English latin letters with hyphens (transliterate names if needed)."
            : "Write all SEO fields in English.";
        $system .= "\n\nAFTER the article, output an SEO block in EXACTLY this format (keep the field LABELS in English):\n{$seo_lang_note}\n[SEO]\nTITLE: <seo title under 60 chars>\nMETA: <meta description under 155 chars>\nSLUG: <url-slug-with-hyphens>\nTAGS: <comma, separated, tags>\n[/SEO]";
    }

    if ( ! empty($article_content) ) {
        $user = "Rewrite the following article in Kontentainment's style as a {$type} piece.\n"
              . "Tone: {$tone}\nTarget length: ~{$wordcount} words\n"
              . ( $notes ? "Editor notes: {$notes}\n" : '' )
              . "\n--- SOURCE CONTENT ---\n"
              . substr($article_content, 0, 8000)
              . "\n--- END SOURCE CONTENT ---\n\n"
              . "Rewrite strictly based on the source content above. Do not add information not in the source.";
    } else {
        $system .= "\n\nIMPORTANT: You only have the headline — do NOT invent details. Write concisely based strictly on the headline.";
        $user    = "Write a Kontentainment {$type} article based on this headline: \"{$subject}\"\n"
                 . "Tone: {$tone}\nTarget length: ~{$wordcount} words\n"
                 . ( $source_label ? "Source: {$source_label}\n" : '' )
                 . ( $notes ? "Notes: {$notes}" : '' );
    }

    // ── Dispatch to the selected provider ──
    $text = kaw_call_provider( $provider, $system, $user, 8192 );
    if ( is_wp_error($text) ) wp_send_json_error( $text->get_error_message() );
    if ( empty($text) ) wp_send_json_error('Empty response from ' . $provider['label'] . '.');

    // Split out SEO block
    $seo = null;
    if ( preg_match('/\[SEO\](.*?)\[\/SEO\]/s', $text, $m) ) {
        $seo_raw = trim($m[1]);
        $text    = trim( preg_replace('/\[SEO\].*?\[\/SEO\]/s', '', $text) );
        $seo = [
            'title' => kaw_seo_field($seo_raw, 'TITLE'),
            'meta'  => kaw_seo_field($seo_raw, 'META'),
            'slug'  => kaw_seo_field($seo_raw, 'SLUG'),
            'tags'  => kaw_seo_field($seo_raw, 'TAGS'),
        ];
    }

    wp_send_json_success([ 'article' => $text, 'seo' => $seo, 'provider' => $provider['label'] ]);
}

// Call any provider (anthropic or openai-compatible). Returns string or WP_Error.
function kaw_call_provider( $provider, $system, $user, $max_tokens = 8192 ) {
    $endpoint = $provider['endpoint'];
    $model    = $provider['model'];
    $api_key  = $provider['key'];

    if ( $provider['format'] === 'anthropic' ) {
        $resp = wp_remote_post( $endpoint, [
            'timeout' => 90,
            'headers' => [
                'Content-Type'      => 'application/json',
                'x-api-key'         => $api_key,
                'anthropic-version' => '2023-06-01',
            ],
            'body' => wp_json_encode([
                'model'      => $model,
                'max_tokens' => $max_tokens,
                'system'     => $system,
                'messages'   => [ [ 'role' => 'user', 'content' => $user ] ],
            ]),
        ]);
        $body = kaw_decode_provider_response( $resp, $provider );
        if ( is_wp_error($body) ) return $body;
        return $body['content'][0]['text'] ?? '';
    }

    // Poe (Responses API format)
    if ( $provider['format'] === 'poe' ) {
        $resp = wp_remote_post( $endpoint, [
            'timeout' => 90,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
                'Accept'        => 'application/json',
            ],
            'body' => wp_json_encode([
                'model'             => $model,
                'instructions'      => $system,
                'input'             => $user,
                'max_output_tokens' => $max_tokens,
            ]),
        ]);
        $body = kaw_decode_provider_response( $resp, $provider );
        if ( is_wp_error($body) ) return $body;
        // Extract text from output[].content[].text
        $out = '';
        if ( ! empty($body['output']) && is_array($body['output']) ) {
            foreach ( $body['output'] as $item ) {
                if ( empty($item['content']) || ! is_array($item['content']) ) continue;
                foreach ( $item['content'] as $block ) {
                    if ( ! empty($block['text']) ) $out .= $block['text'];
                }
            }
        }
        return $out;
    }

    // OpenAI-compatible (ChatGPT, Gemini, DeepSeek, Mistral, Qwen)
    $resp = wp_remote_post( $endpoint, [
        'timeout' => 90,
        'headers' => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ],
        'body' => wp_json_encode([
            'model'      => $model,
            'max_tokens' => $max_tokens,
            'messages'   => [
                [ 'role' => 'system', 'content' => $system ],
                [ 'role' => 'user',   'content' => $user ],
            ],
        ]),
    ]);
    $body = kaw_decode_provider_response( $resp, $provider );
    if ( is_wp_error($body) ) return $body;
    return $body['choices'][0]['message']['content'] ?? '';
}

// Validate HTTP status and JSON structure consistently across provider APIs.
function kaw_decode_provider_response( $response, $provider ) {
    if ( is_wp_error($response) ) return $response;

    $status = wp_remote_retrieve_response_code($response);
    $raw    = wp_remote_retrieve_body($response);
    $body   = json_decode($raw, true);

    if ( ! is_array($body) ) {
        if ( $status === 404 && ($provider['pkey'] ?? '') === 'gemini' ) {
            return new WP_Error('api_http', sprintf(
                'Gemini model "%s" or endpoint was not found. Check the model in AI Writer → Settings.',
                $provider['model']
            ));
        }
        return new WP_Error('api_response', $provider['label'] . ' returned an unreadable response.');
    }

    if ( $status < 200 || $status >= 300 || ! empty($body['error']) ) {
        $error = $body['error'] ?? [];
        $message = is_array($error) ? ($error['message'] ?? '') : (string) $error;
        if ( $message === '' && ! empty($body['message']) && is_string($body['message']) ) {
            $message = $body['message'];
        }
        if ( $message === '' && $status === 404 && ($provider['pkey'] ?? '') === 'gemini' ) {
            $message = sprintf(
                'Gemini model "%s" or endpoint was not found. Check the model in AI Writer → Settings.',
                $provider['model']
            );
        }
        if ( $message === '' ) $message = $provider['label'] . ' returned HTTP ' . (int) $status . '.';
        return new WP_Error('api_http', $message);
    }

    return $body;
}

function kaw_seo_field( $raw, $label ) {
    if ( preg_match('/' . $label . ':\s*(.+)/i', $raw, $m) ) return trim($m[1]);
    return '';
}

// ── AJAX: Create draft (with featured image + SEO) ────────────────────────────

add_action( 'wp_ajax_kaw_create_draft', 'kaw_ajax_create_draft' );

function kaw_ajax_create_draft() {
    check_ajax_referer( 'kaw_nonce', 'nonce' );
    if ( ! current_user_can('edit_posts') ) wp_send_json_error('Permission denied.');

    $content  = sanitize_textarea_field( $_POST['content'] ?? '' );
    $subject  = sanitize_text_field( $_POST['subject']     ?? 'AI Draft' );
    $image_url = esc_url_raw( $_POST['image_url']          ?? '' );
    $seo_title = sanitize_text_field( $_POST['seo_title']  ?? '' );
    $seo_meta  = sanitize_text_field( $_POST['seo_meta']   ?? '' );
    $seo_slug  = sanitize_title( $_POST['seo_slug']        ?? '' );
    $seo_tags  = sanitize_text_field( $_POST['seo_tags']   ?? '' );

    if ( empty($content) ) wp_send_json_error('No content to insert.');

    $lines = explode("\n", trim($content));
    $title = ! empty($lines[0]) ? wp_strip_all_tags($lines[0]) : $subject;
    $has_tagline = isset($lines[1], $lines[2]) && trim($lines[1]) !== '' && trim($lines[2]) === '';
    $tagline = $has_tagline ? sanitize_text_field($lines[1]) : '';
    $body  = implode("\n", array_slice($lines, $has_tagline ? 2 : 1));

    $postarr = [
        'post_title'   => $title,
        'post_content' => kaw_format_draft_body($body),
        'post_status'  => 'draft',
        'post_type'    => 'post',
    ];
    if ( $seo_slug ) $postarr['post_name'] = $seo_slug;

    $post_id = wp_insert_post($postarr);
    if ( is_wp_error($post_id) ) wp_send_json_error( $post_id->get_error_message() );

    // Foxiz reads the single-post tagline from the ruby_tagline post meta field.
    if ( $tagline ) update_post_meta($post_id, 'ruby_tagline', $tagline);

    // Tags
    if ( $seo_tags ) {
        $tags = array_map('trim', explode(',', $seo_tags));
        wp_set_post_tags($post_id, $tags, false);
    }

    // SEO meta (compatible with Yoast / Rank Math / generic)
    if ( $seo_title ) {
        update_post_meta($post_id, '_yoast_wpseo_title', $seo_title);
        update_post_meta($post_id, 'rank_math_title', $seo_title);
    }
    if ( $seo_meta ) {
        update_post_meta($post_id, '_yoast_wpseo_metadesc', $seo_meta);
        update_post_meta($post_id, 'rank_math_description', $seo_meta);
    }

    // Featured image — sideload from URL
    if ( $image_url ) {
        $att_id = kaw_sideload_image($image_url, $post_id);
        if ( $att_id && ! is_wp_error($att_id) ) set_post_thumbnail($post_id, $att_id);
    }

    wp_send_json_success([ 'edit_url' => get_edit_post_link($post_id, 'raw') ]);
}

function kaw_format_draft_body( $body ) {
    $lines = preg_split('/\r\n|\r|\n/', trim($body));
    $blocks = [];
    $paragraph = [];

    $flush_paragraph = function () use ( &$paragraph, &$blocks ) {
        if ( empty($paragraph) ) return;
        $blocks[] = wpautop( wp_kses_post( implode("\n", $paragraph) ) );
        $paragraph = [];
    };

    foreach ( $lines as $line ) {
        if ( preg_match('/^\s*##\s+(.+?)\s*$/u', $line, $matches) ) {
            $flush_paragraph();
            $blocks[] = '<h2>' . esc_html( trim($matches[1]) ) . '</h2>';
        } elseif ( trim($line) === '' ) {
            $flush_paragraph();
        } else {
            $paragraph[] = $line;
        }
    }

    $flush_paragraph();
    return implode("\n", $blocks);
}

function kaw_sideload_image( $url, $post_id ) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $tmp = download_url($url, 30);
    if ( is_wp_error($tmp) ) return $tmp;

    $file = [
        'name'     => basename( parse_url($url, PHP_URL_PATH) ) ?: 'featured.jpg',
        'tmp_name' => $tmp,
    ];
    $id = media_handle_sideload($file, $post_id);
    if ( is_wp_error($id) ) { @unlink($tmp); return $id; }
    return $id;
}

// ── GitHub updates ───────────────────────────────────────────────────────────
// Version checks follow the Version header in this plugin file on the main branch.
$kaw_update_checker_file = KAW_PATH . 'plugin-update-checker/plugin-update-checker.php';
if ( is_readable($kaw_update_checker_file) ) {
    require_once $kaw_update_checker_file;

    if ( class_exists('\YahnisElsts\PluginUpdateChecker\v5\PucFactory') ) {
        $kaw_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            'https://github.com/kollectivco/kposts/',
            __FILE__,
            'ai-editorial-engine'
        );
        $kaw_update_checker->setBranch('main');
    }
}
