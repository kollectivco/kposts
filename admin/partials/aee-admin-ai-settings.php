<?php
/**
 * AI Settings Page
 *
 * @package AI_Editorial_Engine
 */

if ( ! defined( 'WPINC' ) ) die;
if ( ! current_user_can( 'manage_options' ) ) return;

$settings = get_option( 'aee_settings', [] );

$apis = [
    'gemini_api_key'       => [ 'label' => 'Gemini API Key', 'hint' => 'من Google AI Studio — generativelanguage.googleapis.com', 'api' => 'gemini' ],
    'claude_api_key'       => [ 'label' => 'Claude API Key (Anthropic)', 'hint' => 'من console.anthropic.com', 'api' => 'claude' ],
    'openai_api_key'       => [ 'label' => 'OpenAI API Key', 'hint' => 'من platform.openai.com', 'api' => 'openai' ],
    'google_translate_key' => [ 'label' => 'Google Cloud Translate Key', 'hint' => 'من Google Cloud Console — Translation API', 'api' => 'google' ],
    'deepl_key'            => [ 'label' => 'DeepL API Key (احتياطي)', 'hint' => 'من deepl.com/pro — مجاني حتى 500,000 حرف/شهر', 'api' => 'deepl' ],
];
?>
<div class="aee-wrap" dir="rtl">
    <h1>🤖 <?php esc_html_e( 'إعدادات الذكاء الاصطناعي', 'ai-editorial-engine' ); ?></h1>

    <form method="post" action="options.php">
        <?php settings_fields( 'aee_settings_group' ); ?>

        <!-- API Keys -->
        <div class="aee-panel">
            <div class="aee-panel-header">
                <h2>🔑 <?php esc_html_e( 'مفاتيح API', 'ai-editorial-engine' ); ?></h2>
            </div>
            <div class="aee-panel-body">
                <p style="color:#50575e; margin-bottom:20px; font-size:13px;">
                    ⚠️ المفاتيح مشفّرة عند الحفظ — لن تظهر مرة ثانية. إذا أردت تغيير مفتاح، أدخل القيمة الجديدة فقط.
                </p>

                <?php foreach ( $apis as $key => $info ) : ?>
                <div class="aee-form-row">
                    <label><?php echo esc_html( $info['label'] ); ?></label>
                    <div class="aee-api-group">
                        <input type="password"
                               name="aee_settings[<?php echo esc_attr( $key ); ?>]"
                               class="aee-input"
                               placeholder="<?php echo ! empty( $settings[ $key ] ) ? '••••••••' : 'أدخل المفتاح هنا'; ?>"
                               autocomplete="new-password"
                               dir="ltr">
                        <button type="button" class="aee-btn aee-btn-secondary aee-toggle-key">👁</button>
                        <button type="button" class="aee-btn aee-btn-secondary aee-test-api" data-api="<?php echo esc_attr( $info['api'] ); ?>">اختبر</button>
                    </div>
                    <div class="aee-hint"><?php echo esc_html( $info['hint'] ); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- AI Model Config -->
        <div class="aee-panel">
            <div class="aee-panel-header">
                <h2>🧠 <?php esc_html_e( 'تكوين النماذج', 'ai-editorial-engine' ); ?></h2>
            </div>
            <div class="aee-panel-body">
                <div class="aee-two-col-form">
                    <div class="aee-form-row">
                        <label><?php esc_html_e( 'النموذج الرئيسي', 'ai-editorial-engine' ); ?></label>
                        <select name="aee_settings[primary_ai_model]" class="aee-select">
                            <option value="claude"  <?php selected( $settings['primary_ai_model'] ?? 'claude', 'claude' ); ?>>Claude 3.5 Sonnet — أفضل للصياغة الأدبية</option>
                            <option value="gemini"  <?php selected( $settings['primary_ai_model'] ?? 'claude', 'gemini' ); ?>>Gemini 1.5 Pro — أفضل للتحليل</option>
                            <option value="openai"  <?php selected( $settings['primary_ai_model'] ?? 'claude', 'openai' ); ?>>GPT-4o — أفضل لـ SEO والهيكلة</option>
                        </select>
                        <div class="aee-hint">البلجن بيستخدم الـ fallback chain تلقائياً عند الفشل</div>
                    </div>

                    <div class="aee-form-row">
                        <label><?php esc_html_e( 'حد المقالات اليومي', 'ai-editorial-engine' ); ?></label>
                        <input type="number" name="aee_settings[daily_article_limit]"
                               class="aee-input" min="1" max="500"
                               value="<?php echo esc_attr( $settings['daily_article_limit'] ?? 50 ); ?>">
                        <div class="aee-hint">الحد الأقصى للمقالات المجلوبة يومياً (للتحكم في التكلفة)</div>
                    </div>

                    <div class="aee-form-row">
                        <label><?php esc_html_e( 'النشر التلقائي', 'ai-editorial-engine' ); ?></label>
                        <label style="font-weight:400; cursor:pointer;">
                            <input type="checkbox" name="aee_settings[auto_publish]" value="1"
                                   <?php checked( $settings['auto_publish'] ?? 0, 1 ); ?>>
                            نشر المقالات تلقائياً بعد المعالجة (بدون مراجعة)
                        </label>
                        <div class="aee-hint" style="color:#d63638;">⚠️ غير مُوصى به — المراجعة اليدوية مهمة لضمان الجودة</div>
                    </div>

                    <div class="aee-form-row">
                        <label><?php esc_html_e( 'إيميل الإشعارات', 'ai-editorial-engine' ); ?></label>
                        <input type="email" name="aee_settings[notify_email]"
                               class="aee-input"
                               value="<?php echo esc_attr( $settings['notify_email'] ?? get_option( 'admin_email' ) ); ?>"
                               dir="ltr">
                        <div class="aee-hint">يُرسل إشعار على هذا الإيميل عند كل مقال جاهز</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fallback Chain Info -->
        <div class="aee-panel">
            <div class="aee-panel-header">
                <h2>⛓️ <?php esc_html_e( 'سلسلة الـ Fallback', 'ai-editorial-engine' ); ?></h2>
            </div>
            <div class="aee-panel-body">
                <p style="font-size:13px; color:#50575e; margin:0 0 16px;">البلجن بيشتغل بنظام ذكي لتوزيع المهام:</p>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <div class="aee-alert aee-alert-info" style="flex:1; min-width:200px;">
                        <strong>📊 Gemini</strong><br>تحليل المحتوى، استخراج الحقائق، التلخيص
                    </div>
                    <div class="aee-alert aee-alert-info" style="flex:1; min-width:200px;">
                        <strong>✍️ Claude</strong><br>إعادة الصياغة الأدبية، التدقيق اللغوي
                    </div>
                    <div class="aee-alert aee-alert-info" style="flex:1; min-width:200px;">
                        <strong>🔍 GPT-4o</strong><br>هيكلة المقال، عناوين SEO، الكلمات المفتاحية
                    </div>
                </div>
                <p style="font-size:12px; color:#50575e; margin-top:12px;">
                    إذا فشل أي نموذج → البلجن بينتقل تلقائياً للتالي في السلسلة ← ومحدش هيعرف!
                </p>
            </div>
        </div>

        <?php submit_button( 'حفظ الإعدادات', 'primary', 'submit', true ); ?>
    </form>
</div>
