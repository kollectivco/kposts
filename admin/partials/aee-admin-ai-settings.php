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



        <?php submit_button( 'حفظ الإعدادات', 'primary', 'submit', true ); ?>
    </form>
</div>
