<?php
/**
 * Admin Controller — Fixed & Complete
 *
 * @package AI_Editorial_Engine
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class AEE_Admin {

    public function init(): void {
        add_action( 'admin_menu',             [ $this, 'register_menus' ] );
        add_action( 'admin_init',             [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts',  [ $this, 'enqueue_assets' ] );

        // AJAX handlers — all registered here
        $ajax_actions = [
            'aee_test_api',
            'aee_manual_fetch',
            'aee_process_item',
            'aee_save_source',
            'aee_delete_source',
            'aee_save_dict_entry',
            'aee_delete_dict_entry',
            'aee_rewrite_existing',
            'aee_get_queue_stats',
            'aee_trigger_learning',
            'aee_seed_dictionary',
            'aee_toggle_source',
        ];

        foreach ( $ajax_actions as $action ) {
            $method = 'ajax_' . str_replace( 'aee_', '', $action );
            if ( method_exists( $this, $method ) ) {
                add_action( 'wp_ajax_' . $action, [ $this, $method ] );
            }
        }
    }

    // =========================================================================
    // MENUS
    // =========================================================================

    public function register_menus(): void {
        add_menu_page(
            __( 'AI Editorial Engine', 'ai-editorial-engine' ),
            __( 'AI تحرير', 'ai-editorial-engine' ),
            'manage_options',
            'aee-dashboard',
            [ $this, 'page_dashboard' ],
            'dashicons-welcome-write-blog',
            30
        );

        $pages = [
            [ 'aee-dashboard',      'لوحة التحكم',  'page_dashboard' ],
            [ 'aee-sources',        'مصادر المحتوى', 'page_sources' ],
            [ 'aee-ai-settings',    'إعدادات AI',    'page_ai_settings' ],
            [ 'aee-style-settings', 'أسلوب المجلة',  'page_style_settings' ],
            [ 'aee-dictionary',     'معجم العامية',   'page_dictionary' ],
            [ 'aee-reports',        'التقارير',       'page_reports' ],
        ];

        foreach ( $pages as $page ) {
            add_submenu_page( 'aee-dashboard', $page[1], $page[1], 'manage_options', $page[0], [ $this, $page[2] ] );
        }
    }

    // =========================================================================
    // PAGE RENDERERS
    // =========================================================================

    public function page_dashboard():     void { $this->load_partial( 'aee-admin-dashboard' ); }
    public function page_sources():       void { $this->load_partial( 'aee-admin-sources' ); }
    public function page_ai_settings():   void { $this->load_partial( 'aee-admin-ai-settings' ); }
    public function page_style_settings():void { $this->load_partial( 'aee-admin-style-settings' ); }
    public function page_dictionary():    void { $this->load_partial( 'aee-admin-dictionary' ); }
    public function page_reports():       void { $this->load_partial( 'aee-admin-reports' ); }

    private function load_partial( string $name ): void {
        $file = AEE_PLUGIN_DIR . "admin/partials/{$name}.php";
        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }

    // =========================================================================
    // SETTINGS
    // =========================================================================

    public function register_settings(): void {
        register_setting( 'aee_settings_group', 'aee_settings', [ $this, 'sanitize_settings' ] );
    }

    public function sanitize_settings( array $input ): array {
        $existing  = get_option( 'aee_settings', [] );
        $sanitized = $existing; // start with existing so encrypted keys survive

        // API keys — only update if a real value was entered
        $api_keys = [ 'gemini_api_key', 'claude_api_key', 'openai_api_key', 'google_translate_key', 'deepl_key' ];
        foreach ( $api_keys as $key ) {
            $val = trim( $input[ $key ] ?? '' );
            // If empty or placeholder, keep existing
            if ( ! empty( $val ) && $val !== '••••••••' && $val !== str_repeat( '•', 8 ) ) {
                $sanitized[ $key ] = aee_encrypt( $val );
            }
        }

        // Text fields
        $text_fields = [
            'magazine_name', 'target_audience', 'brand_voice',
            'sample_articles', 'default_category', 'notify_email',
            'primary_ai_model', 'magazine_tone', 'banned_words',
        ];
        foreach ( $text_fields as $field ) {
            $sanitized[ $field ] = sanitize_textarea_field( $input[ $field ] ?? '' );
        }

        // Numeric
        $sanitized['article_min_length']  = max( 50,  intval( $input['article_min_length']  ?? 300 ) );
        $sanitized['article_max_length']  = max( 200, intval( $input['article_max_length']  ?? 1200 ) );
        $sanitized['daily_article_limit'] = max( 1,   intval( $input['daily_article_limit'] ?? 50 ) );
        $sanitized['colloquial_intensity']= min( 1.0, max( 0.0, floatval( $input['colloquial_intensity'] ?? 0.3 ) ) );

        // Boolean
        $sanitized['auto_publish'] = ! empty( $input['auto_publish'] ) ? 1 : 0;

        return $sanitized;
    }

    // =========================================================================
    // ASSETS
    // =========================================================================

    public function enqueue_assets( string $hook ): void {
        if ( strpos( $hook, 'aee-' ) === false && $hook !== 'toplevel_page_aee-dashboard' ) return;

        wp_enqueue_style(
            'aee-admin',
            AEE_PLUGIN_URL . 'admin/css/aee-admin.css',
            [],
            AEE_VERSION
        );

        wp_enqueue_script(
            'aee-admin',
            AEE_PLUGIN_URL . 'admin/js/aee-admin.js',
            [ 'jquery' ],
            AEE_VERSION,
            true
        );

        wp_localize_script( 'aee-admin', 'aeeData', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'aee_admin_nonce' ),
            'strings' => [
                'confirm_del' => __( 'هل أنت متأكد من الحذف؟ لا يمكن التراجع.', 'ai-editorial-engine' ),
            ],
        ] );
    }

    // =========================================================================
    // AJAX: TEST API
    // =========================================================================

    public function ajax_test_api(): void {
        $this->verify_nonce();

        $api = sanitize_text_field( $_POST['api'] ?? '' );
        $key = sanitize_text_field( $_POST['key'] ?? '' );

        if ( empty( $key ) ) {
            wp_send_json_error( [ 'message' => 'أدخل المفتاح أولاً' ] );
        }

        switch ( $api ) {
            case 'gemini':
                $result = ( new AEE_Gemini( $key ) )->test_connection();
                break;
            case 'claude':
                $result = ( new AEE_Claude( $key ) )->test_connection();
                break;
            case 'openai':
                $result = ( new AEE_OpenAI( $key ) )->test_connection();
                break;
            case 'google':
                $result = ( new AEE_Translator( $key ) )->test_connection();
                break;
            default:
                $result = 'API غير معروف';
                break;
        }

        if ( $result === true ) {
            wp_send_json_success( [ 'message' => 'الاتصال ناجح!' ] );
        } else {
            wp_send_json_error( [ 'message' => (string) $result ] );
        }
    }

    // =========================================================================
    // AJAX: MANUAL FETCH
    // =========================================================================

    public function ajax_manual_fetch(): void {
        $this->verify_nonce();

        $source_id = intval( $_POST['source_id'] ?? 0 );
        $fetcher   = new AEE_Content_Fetcher();

        if ( $source_id > 0 ) {
            global $wpdb;
            $source = $wpdb->get_row(
                $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}aee_sources WHERE id = %d", $source_id ),
                ARRAY_A
            );
            if ( ! $source ) {
                wp_send_json_error( [ 'message' => 'المصدر غير موجود' ] );
            }
            $count = $source['type'] === 'rss' ? $fetcher->fetch_from_rss( $source ) : 0;
        } else {
            // Fetch all active sources
            $fetcher->run_scheduled_fetch();
            $count = -1;
        }

        $msg = $count === -1
            ? __( 'تم تشغيل الجلب من كل المصادر النشطة', 'ai-editorial-engine' )
            : sprintf( __( 'تم جلب %d مقال جديد', 'ai-editorial-engine' ), $count );

        wp_send_json_success( [ 'message' => $msg, 'count' => $count ] );
    }

    // =========================================================================
    // AJAX: PROCESS SPECIFIC QUEUE ITEM
    // =========================================================================

    public function ajax_process_item(): void {
        $this->verify_nonce();

        $item_id = intval( $_POST['item_id'] ?? 0 );
        if ( ! $item_id ) {
            wp_send_json_error( [ 'message' => 'رقم العنصر مطلوب' ] );
        }

        global $wpdb;
        $item = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}aee_queue WHERE id = %d AND status IN ('pending','failed')",
                $item_id
            ),
            ARRAY_A
        );

        if ( ! $item ) {
            wp_send_json_error( [ 'message' => 'العنصر غير موجود أو تمت معالجته بالفعل' ] );
        }

        // Process this specific item inline (not async)
        $automation = new AEE_Automation();
        $automation->process_single_item_public( $item );

        // Check new status
        $new_status = $wpdb->get_var(
            $wpdb->prepare( "SELECT status FROM {$wpdb->prefix}aee_queue WHERE id = %d", $item_id )
        );

        if ( $new_status === 'ready' || $new_status === 'published' ) {
            wp_send_json_success( [ 'message' => '✅ تمت المعالجة بنجاح — المقال جاهز في المسودات' ] );
        } else {
            $error = $wpdb->get_var(
                $wpdb->prepare( "SELECT error_log FROM {$wpdb->prefix}aee_queue WHERE id = %d", $item_id )
            );
            wp_send_json_error( [ 'message' => '❌ فشل: ' . ( $error ?: 'خطأ غير معروف' ) ] );
        }
    }

    // =========================================================================
    // AJAX: SAVE SOURCE
    // =========================================================================

    public function ajax_save_source(): void {
        $this->verify_nonce();

        global $wpdb;
        $table = $wpdb->prefix . 'aee_sources';

        $data = [
            'name'               => sanitize_text_field( $_POST['name'] ?? '' ),
            'url'                => esc_url_raw( $_POST['url'] ?? '' ),
            'type'               => in_array( $_POST['type'] ?? 'rss', [ 'rss', 'scrape' ], true ) ? $_POST['type'] : 'rss',
            'fetch_interval'     => intval( $_POST['fetch_interval'] ?? 3600 ),
            'is_active'          => intval( $_POST['is_active'] ?? 1 ),
            'keywords_whitelist' => sanitize_textarea_field( $_POST['keywords_whitelist'] ?? '' ),
            'keywords_blacklist' => sanitize_textarea_field( $_POST['keywords_blacklist'] ?? '' ),
        ];

        if ( empty( $data['name'] ) || empty( $data['url'] ) ) {
            wp_send_json_error( [ 'message' => 'الاسم والرابط مطلوبان' ] );
        }

        $id = intval( $_POST['id'] ?? 0 );

        if ( $id > 0 ) {
            $result = $wpdb->update( $table, $data, [ 'id' => $id ] );
        } else {
            $result = $wpdb->insert( $table, $data );
            $id     = $wpdb->insert_id;
        }

        if ( $result === false ) {
            wp_send_json_error( [ 'message' => 'خطأ في قاعدة البيانات: ' . $wpdb->last_error ] );
        }

        wp_send_json_success( [ 'id' => $id, 'message' => 'تم الحفظ بنجاح' ] );
    }

    // =========================================================================
    // AJAX: DELETE SOURCE
    // =========================================================================

    public function ajax_delete_source(): void {
        $this->verify_nonce();

        global $wpdb;
        $id = intval( $_POST['id'] ?? 0 );

        if ( ! $id ) {
            wp_send_json_error( [ 'message' => 'رقم المصدر مطلوب' ] );
        }

        $wpdb->delete( $wpdb->prefix . 'aee_sources', [ 'id' => $id ], [ '%d' ] );
        wp_send_json_success( [ 'message' => 'تم الحذف' ] );
    }

    // =========================================================================
    // AJAX: TOGGLE SOURCE ACTIVE STATUS
    // =========================================================================

    public function ajax_toggle_source(): void {
        $this->verify_nonce();

        global $wpdb;
        $id = intval( $_POST['id'] ?? 0 );

        $current = intval( $wpdb->get_var(
            $wpdb->prepare( "SELECT is_active FROM {$wpdb->prefix}aee_sources WHERE id = %d", $id )
        ) );

        $new_status = $current ? 0 : 1;
        $wpdb->update( $wpdb->prefix . 'aee_sources', [ 'is_active' => $new_status ], [ 'id' => $id ] );

        wp_send_json_success( [
            'is_active' => $new_status,
            'message'   => $new_status ? 'تم تفعيل المصدر' : 'تم إيقاف المصدر',
        ] );
    }

    // =========================================================================
    // AJAX: SAVE DICTIONARY ENTRY
    // =========================================================================

    public function ajax_save_dict_entry(): void {
        $this->verify_nonce();

        global $wpdb;
        $table = $wpdb->prefix . 'aee_dictionary';

        $data = [
            'formal_word'     => sanitize_text_field( $_POST['formal'] ?? '' ),
            'colloquial_word' => sanitize_text_field( $_POST['colloquial'] ?? '' ),
            'context'         => sanitize_text_field( $_POST['context'] ?? 'general' ),
            'is_active'       => 1,
        ];

        if ( empty( $data['formal_word'] ) || empty( $data['colloquial_word'] ) ) {
            wp_send_json_error( [ 'message' => 'الكلمة الفصحى والعامية مطلوبتان' ] );
        }

        $id = intval( $_POST['id'] ?? 0 );

        if ( $id > 0 ) {
            $wpdb->update( $table, $data, [ 'id' => $id ] );
        } else {
            // Check for duplicate
            $exists = $wpdb->get_var(
                $wpdb->prepare( "SELECT id FROM {$table} WHERE formal_word = %s", $data['formal_word'] )
            );
            if ( $exists ) {
                wp_send_json_error( [ 'message' => 'الكلمة موجودة بالفعل في المعجم' ] );
            }
            $wpdb->insert( $table, $data );
            $id = $wpdb->insert_id;
        }

        wp_send_json_success( [ 'id' => $id, 'message' => 'تم الحفظ' ] );
    }

    // =========================================================================
    // AJAX: DELETE DICTIONARY ENTRY
    // =========================================================================

    public function ajax_delete_dict_entry(): void {
        $this->verify_nonce();

        global $wpdb;
        $id = intval( $_POST['id'] ?? 0 );

        if ( ! $id ) {
            wp_send_json_error( [ 'message' => 'رقم الكلمة مطلوب' ] );
        }

        $wpdb->delete( $wpdb->prefix . 'aee_dictionary', [ 'id' => $id ], [ '%d' ] );
        wp_send_json_success( [ 'message' => 'تم الحذف' ] );
    }

    // =========================================================================
    // AJAX: REWRITE EXISTING POST
    // =========================================================================

    public function ajax_rewrite_existing(): void {
        $this->verify_nonce();

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => 'ليس لديك صلاحية' ] );
        }

        $post_id = intval( $_POST['post_id'] ?? 0 );
        $post    = get_post( $post_id );

        if ( ! $post ) {
            wp_send_json_error( [ 'message' => 'المقال غير موجود' ] );
        }

        if ( empty( trim( $post->post_content ) ) ) {
            wp_send_json_error( [ 'message' => 'المقال فارغ' ] );
        }

        $orchestrator = new AEE_AI_Orchestrator();
        $style        = new AEE_Egyptian_Style();
        $brand        = new AEE_Brand_Enforcer();
        $intensity    = floatval( aee_get_setting( 'colloquial_intensity', 0.3 ) );

        $result = $orchestrator->process_article( $post->post_content );

        if ( ! empty( $result['error'] ) && empty( $result['content'] ) ) {
            wp_send_json_error( [ 'message' => 'فشل AI: ' . $result['error'] ] );
        }

        $final = $brand->enforce( $style->apply_style( $result['content'], $intensity ) );

        // Save original before overwriting
        update_post_meta( $post_id, '_aee_original_before_rewrite', $post->post_content );
        update_post_meta( $post_id, '_aee_ai_generated', 1 );

        wp_update_post( [
            'ID'           => $post_id,
            'post_content' => $final,
            'post_title'   => ! empty( $result['title'] ) ? $result['title'] : $post->post_title,
        ] );

        wp_send_json_success( [ 'message' => '✅ تمت إعادة الكتابة — راجع المقال في المحرر' ] );
    }

    // =========================================================================
    // AJAX: GET QUEUE STATS
    // =========================================================================

    public function ajax_get_queue_stats(): void {
        $this->verify_nonce();

        global $wpdb;
        $stats = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$wpdb->prefix}aee_queue GROUP BY status",
            ARRAY_A
        );

        wp_send_json_success( [ 'stats' => $stats ] );
    }

    // =========================================================================
    // AJAX: TRIGGER SELF-LEARNING
    // =========================================================================

    public function ajax_trigger_learning(): void {
        $this->verify_nonce();

        $learner = new AEE_Self_Learner();
        $updated = $learner->update_system_prompt_from_feedback();

        if ( $updated ) {
            wp_send_json_success( [ 'message' => 'تم تحديث نماذج التعلم بنجاح من أفضل المقالات المعتمدة' ] );
        } else {
            wp_send_json_error( [ 'message' => 'لا توجد بيانات كافية بعد — تحتاج لمراجعة وموافقة على 10 مقالات على الأقل' ] );
        }
    }

    // =========================================================================
    // AJAX: SEED DICTIONARY FROM JSON
    // =========================================================================

    public function ajax_seed_dictionary(): void {
        $this->verify_nonce();

        $count = AEE_Activator::seed_dictionary();
        wp_send_json_success( [ 'message' => sprintf( 'تم استيراد %d كلمة من الملف الافتراضي', $count ) ] );
    }

    // =========================================================================
    // HELPER: VERIFY NONCE + CAPABILITY
    // =========================================================================

    private function verify_nonce(): void {
        if ( ! check_ajax_referer( 'aee_admin_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'انتهت صلاحية الجلسة — أعد تحميل الصفحة' ] );
            wp_die();
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'ليس لديك صلاحية' ] );
            wp_die();
        }
    }
}
