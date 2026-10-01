<?php
/**
 * Magazine Style Settings Page
 *
 * @package AI_Editorial_Engine
 */

if ( ! defined( 'WPINC' ) ) die;
if ( ! current_user_can( 'manage_options' ) ) return;

$settings = get_option( 'aee_settings', [] );
$intensity = floatval( $settings['colloquial_intensity'] ?? 0.3 );
?>
<div class="aee-wrap" dir="rtl">
    <h1>🎨 <?php esc_html_e( 'أسلوب المجلة', 'ai-editorial-engine' ); ?></h1>

    <form method="post" action="options.php">
        <?php settings_fields( 'aee_settings_group' ); ?>

        <!-- Magazine Identity -->
        <div class="aee-panel">
            <div class="aee-panel-header">
                <h2>🏷️ <?php esc_html_e( 'هوية المجلة', 'ai-editorial-engine' ); ?></h2>
            </div>
            <div class="aee-panel-body">
                <div class="aee-two-col-form">
                    <div class="aee-form-row">
                        <label><?php esc_html_e( 'اسم المجلة', 'ai-editorial-engine' ); ?></label>
                        <input type="text" name="aee_settings[magazine_name]"
                               class="aee-input"
                               placeholder="مثال: مجلة القاهرة اليوم"
                               value="<?php echo esc_attr( $settings['magazine_name'] ?? '' ); ?>">
                        <div class="aee-hint">يُستخدم في System Prompt والنسب</div>
                    </div>

                    <div class="aee-form-row">
                        <label><?php esc_html_e( 'الجمهور المستهدف', 'ai-editorial-engine' ); ?></label>
                        <input type="text" name="aee_settings[target_audience]"
                               class="aee-input"
                               placeholder="مثال: مصريون من 25 إلى 45 سنة، مهتمون بالاقتصاد"
                               value="<?php echo esc_attr( $settings['target_audience'] ?? '' ); ?>">
                    </div>

                    <div class="aee-form-row">
                        <label><?php esc_html_e( 'نبرة المجلة', 'ai-editorial-engine' ); ?></label>
                        <select name="aee_settings[magazine_tone]" class="aee-select">
                            <option value="formal"    <?php selected( $settings['magazine_tone'] ?? 'neutral', 'formal' ); ?>>رسمية وجادة</option>
                            <option value="neutral"   <?php selected( $settings['magazine_tone'] ?? 'neutral', 'neutral' ); ?>>محايدة ومهنية (مُوصى به)</option>
                            <option value="local"     <?php selected( $settings['magazine_tone'] ?? 'neutral', 'local' ); ?>>محلية ودافئة</option>
                            <option value="satirical" <?php selected( $settings['magazine_tone'] ?? 'neutral', 'satirical' ); ?>>ساخرة وتحليلية</option>
                        </select>
                    </div>

                    <div class="aee-form-row">
                        <label><?php esc_html_e( 'التصنيف الافتراضي', 'ai-editorial-engine' ); ?></label>
                        <input type="text" name="aee_settings[default_category]"
                               class="aee-input"
                               placeholder="مثال: أخبار AI"
                               value="<?php echo esc_attr( $settings['default_category'] ?? 'AI Content' ); ?>">
                        <div class="aee-hint">اسم تصنيف WordPress للمقالات الجديدة (يُنشأ تلقائياً)</div>
                    </div>
                </div>

                <div class="aee-form-row">
                    <label><?php esc_html_e( 'شخصية المجلة (Brand Voice)', 'ai-editorial-engine' ); ?></label>
                    <textarea name="aee_settings[brand_voice]" class="aee-textarea" rows="4"
                              placeholder="صف شخصية مجلتك للذكاء الاصطناعي. مثال: مجلتنا تتميز بالأسلوب العلمي الموثق مع لمسة إنسانية، تتجنب الإثارة المفرطة وتُقدم الخبر كما هو بلغة يفهمها الجميع..."><?php echo esc_textarea( $settings['brand_voice'] ?? '' ); ?></textarea>
                </div>
            </div>
        </div>

        <!-- Writing Rules -->
        <div class="aee-panel">
            <div class="aee-panel-header">
                <h2>📏 <?php esc_html_e( 'قواعد الكتابة', 'ai-editorial-engine' ); ?></h2>
            </div>
            <div class="aee-panel-body">
                <div class="aee-two-col-form">
                    <div class="aee-form-row">
                        <label><?php esc_html_e( 'الحد الأدنى لكلمات المقال', 'ai-editorial-engine' ); ?></label>
                        <input type="number" name="aee_settings[article_min_length]"
                               class="aee-input" min="100" max="2000" step="50"
                               value="<?php echo esc_attr( $settings['article_min_length'] ?? 300 ); ?>">
                    </div>

                    <div class="aee-form-row">
                        <label><?php esc_html_e( 'الحد الأقصى لكلمات المقال', 'ai-editorial-engine' ); ?></label>
                        <input type="number" name="aee_settings[article_max_length]"
                               class="aee-input" min="200" max="5000" step="50"
                               value="<?php echo esc_attr( $settings['article_max_length'] ?? 1200 ); ?>">
                    </div>
                </div>

                <div class="aee-form-row">
                    <label><?php esc_html_e( 'نسبة العامية المصرية', 'ai-editorial-engine' ); ?></label>
                    <div class="aee-slider-row">
                        <span>0%</span>
                        <input type="range" id="aee-intensity-slider"
                               name="aee_settings[colloquial_intensity]"
                               min="0" max="0.6" step="0.05"
                               value="<?php echo esc_attr( $intensity ); ?>"
                               class="aee-slider">
                        <span>60%</span>
                        <span class="aee-slider-value" id="aee-intensity-value"><?php echo round( $intensity * 100 ); ?>%</span>
                    </div>
                    <div class="aee-hint">
                        0% = فصحى بحتة | 20-30% = صحفي مصري طبيعي (مُوصى به) | 50%+ = عامية كتيرة
                    </div>
                </div>

                <div class="aee-form-row">
                    <label><?php esc_html_e( 'الكلمات الممنوعة', 'ai-editorial-engine' ); ?></label>
                    <textarea name="aee_settings[banned_words]" class="aee-textarea" rows="3"
                              placeholder="فصل الكلمات بفاصلة: كلمة1, كلمة2, كلمة3"><?php echo esc_textarea( $settings['banned_words'] ?? '' ); ?></textarea>
                    <div class="aee-hint">كلمات أو عبارات لا تريد ظهورها في أي مقال</div>
                </div>
            </div>
        </div>

        <!-- Sample Articles -->
        <div class="aee-panel">
            <div class="aee-panel-header">
                <h2>📄 <?php esc_html_e( 'نماذج من مقالاتك', 'ai-editorial-engine' ); ?></h2>
            </div>
            <div class="aee-panel-body">
                <p style="font-size:13px;color:#50575e;margin-bottom:14px;">
                    الصق هنا 2-3 مقالات من مجلتك كنماذج يتعلم منها الذكاء الاصطناعي. كلما كانت النماذج أفضل، كلما كانت النتائج أدق.
                </p>
                <textarea name="aee_settings[sample_articles]" class="aee-textarea" rows="10"
                          placeholder="--- نموذج 1 ---&#10;العنوان: ...&#10;المحتوى: ...&#10;&#10;--- نموذج 2 ---&#10;..."><?php echo esc_textarea( $settings['sample_articles'] ?? '' ); ?></textarea>
                <?php if ( ! empty( $settings['learned_samples'] ) ) : ?>
                <div class="aee-alert aee-alert-success" style="margin-top:10px;">
                    ✅ البلجن تعلم نماذج جديدة تلقائياً من مقالاتك المعتمدة
                    <small>(آخر تحديث: <?php echo esc_html( $settings['last_learning_update'] ?? '—' ); ?>)</small>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php submit_button( 'حفظ إعدادات الأسلوب', 'primary', 'submit', true ); ?>
    </form>
</div>
