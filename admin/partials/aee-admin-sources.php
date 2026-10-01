<?php
/**
 * Content Sources Management Page
 *
 * @package AI_Editorial_Engine
 */

if ( ! defined( 'WPINC' ) ) die;
if ( ! current_user_can( 'manage_options' ) ) return;

global $wpdb;
$sources = $wpdb->get_results(
    "SELECT * FROM {$wpdb->prefix}aee_sources ORDER BY created_at DESC",
    ARRAY_A
);
?>
<div class="aee-wrap" dir="rtl">
    <h1>🔗 <?php esc_html_e( 'مصادر المحتوى', 'ai-editorial-engine' ); ?></h1>

    <div style="margin-bottom:20px;">
        <button type="button" id="aee-add-source" class="aee-btn aee-btn-primary">
            ➕ <?php esc_html_e( 'إضافة مصدر جديد', 'ai-editorial-engine' ); ?>
        </button>
        <button type="button" class="aee-btn aee-btn-secondary aee-btn-fetch" data-source-id="0">
            📡 <?php esc_html_e( 'اجلب من كل المصادر الآن', 'ai-editorial-engine' ); ?>
        </button>
    </div>

    <div class="aee-panel">
        <div class="aee-panel-header">
            <h2>📋 <?php esc_html_e( 'قائمة المصادر', 'ai-editorial-engine' ); ?></h2>
            <span style="font-size:13px; color:#50575e;"><?php echo count( $sources ); ?> مصدر</span>
        </div>
        <div class="aee-panel-body" style="padding:0;">
            <?php if ( empty( $sources ) ) : ?>
                <div class="aee-empty-state">
                    <span class="dashicons dashicons-rss"></span>
                    <p>لا توجد مصادر بعد — أضف مصدرك الأول!</p>
                    <button id="aee-add-source-empty" class="aee-btn aee-btn-primary">إضافة مصدر</button>
                </div>
            <?php else : ?>
                <table class="aee-table">
                    <thead>
                        <tr>
                            <th>الاسم</th>
                            <th>الرابط</th>
                            <th>النوع</th>
                            <th>التكرار</th>
                            <th>الكلمات المسموح</th>
                            <th>آخر جلب</th>
                            <th>الحالة</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $sources as $source ) : ?>
                        <tr data-id="<?php echo esc_attr( $source['id'] ); ?>"
                            data-name="<?php echo esc_attr( $source['name'] ); ?>"
                            data-url="<?php echo esc_attr( $source['url'] ); ?>"
                            data-type="<?php echo esc_attr( $source['type'] ); ?>"
                            data-interval="<?php echo esc_attr( $source['fetch_interval'] ); ?>"
                            data-active="<?php echo esc_attr( $source['is_active'] ); ?>"
                            data-whitelist="<?php echo esc_attr( $source['keywords_whitelist'] ); ?>"
                            data-blacklist="<?php echo esc_attr( $source['keywords_blacklist'] ); ?>">
                            <td><strong><?php echo esc_html( $source['name'] ); ?></strong></td>
                            <td>
                                <a href="<?php echo esc_url( $source['url'] ); ?>" target="_blank"
                                   style="font-size:12px; direction:ltr; display:block; max-width:250px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                    <?php echo esc_html( $source['url'] ); ?>
                                </a>
                            </td>
                            <td><span class="aee-badge aee-badge-active"><?php echo esc_html( strtoupper( $source['type'] ) ); ?></span></td>
                            <td><?php echo esc_html( $source['fetch_interval'] >= 3600 ? ( $source['fetch_interval'] / 3600 ) . ' ساعة' : ( $source['fetch_interval'] / 60 ) . ' دقيقة' ); ?></td>
                            <td style="font-size:12px; color:#50575e;">
                                <?php echo $source['keywords_whitelist'] ? esc_html( mb_substr( $source['keywords_whitelist'], 0, 40 ) ) . '...' : '—'; ?>
                            </td>
                            <td style="font-size:12px;"><?php echo $source['last_fetched'] ? esc_html( $source['last_fetched'] ) : 'لم يُجلب بعد'; ?></td>
                            <td>
                                <span class="aee-badge <?php echo $source['is_active'] ? 'aee-badge-active' : 'aee-badge-inactive'; ?>">
                                    <?php echo $source['is_active'] ? 'نشط' : 'موقف'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="aee-actions">
                                    <button class="aee-btn aee-btn-secondary aee-btn-fetch" style="font-size:11px;" data-source-id="<?php echo esc_attr( $source['id'] ); ?>">اجلب</button>
                                    <button class="aee-btn aee-btn-secondary aee-edit-source" style="font-size:11px;">تعديل</button>
                                    <button class="aee-btn aee-btn-danger aee-delete-source" style="font-size:11px;">حذف</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Source Modal -->
<div class="aee-modal-backdrop" id="aee-modal-source-backdrop" role="dialog" aria-modal="true">
    <div class="aee-modal" dir="rtl">
        <div class="aee-modal-header">
            <h3 id="aee-modal-source-title">إضافة مصدر جديد</h3>
            <button class="aee-modal-close" aria-label="إغلاق">×</button>
        </div>
        <div class="aee-modal-body">
            <form id="aee-source-form">
                <input type="hidden" name="id" value="">
                <div class="aee-form-row">
                    <label><?php esc_html_e( 'اسم المصدر', 'ai-editorial-engine' ); ?> *</label>
                    <input type="text" name="name" class="aee-input" required placeholder="مثال: BBC Arabic">
                </div>
                <div class="aee-form-row">
                    <label><?php esc_html_e( 'رابط RSS أو الموقع', 'ai-editorial-engine' ); ?> *</label>
                    <input type="url" name="url" class="aee-input" required dir="ltr" placeholder="https://...">
                </div>
                <div class="aee-two-col-form">
                    <div class="aee-form-row">
                        <label><?php esc_html_e( 'النوع', 'ai-editorial-engine' ); ?></label>
                        <select name="type" class="aee-select">
                            <option value="rss">RSS Feed</option>
                            <option value="scrape">Scraping</option>
                        </select>
                    </div>
                    <div class="aee-form-row">
                        <label><?php esc_html_e( 'تكرار الجلب', 'ai-editorial-engine' ); ?></label>
                        <select name="fetch_interval" class="aee-select">
                            <option value="1800">كل 30 دقيقة</option>
                            <option value="3600" selected>كل ساعة</option>
                            <option value="7200">كل ساعتين</option>
                            <option value="21600">كل 6 ساعات</option>
                            <option value="86400">يومياً</option>
                        </select>
                    </div>
                </div>
                <div class="aee-form-row">
                    <label><?php esc_html_e( 'كلمات مسموح بها (فصل بفاصلة)', 'ai-editorial-engine' ); ?></label>
                    <input type="text" name="keywords_whitelist" class="aee-input" placeholder="تقنية, اقتصاد, سياسة">
                    <div class="aee-hint">المقال يجب أن يحتوي على واحدة منها على الأقل — اتركها فارغة للقبول بكل شيء</div>
                </div>
                <div class="aee-form-row">
                    <label><?php esc_html_e( 'كلمات محظورة (فصل بفاصلة)', 'ai-editorial-engine' ); ?></label>
                    <input type="text" name="keywords_blacklist" class="aee-input" placeholder="إعلان, ترويج">
                    <div class="aee-hint">المقالات التي تحتوي على أي منها تُرفض تلقائياً</div>
                </div>
                <div class="aee-form-row">
                    <label style="display:flex; align-items:center; gap:8px; font-weight:400; cursor:pointer;">
                        <input type="checkbox" name="is_active" value="1" checked>
                        <?php esc_html_e( 'تفعيل هذا المصدر', 'ai-editorial-engine' ); ?>
                    </label>
                </div>
            </form>
        </div>
        <div class="aee-modal-footer">
            <button class="aee-btn aee-btn-secondary aee-btn-cancel"><?php esc_html_e( 'إلغاء', 'ai-editorial-engine' ); ?></button>
            <button class="aee-btn aee-btn-primary" onclick="document.getElementById('aee-source-form').dispatchEvent(new Event('submit'))"><?php esc_html_e( 'حفظ', 'ai-editorial-engine' ); ?></button>
        </div>
    </div>
</div>
<script>
// Open modal on empty state button click
document.addEventListener('DOMContentLoaded', function() {
    var emptyBtn = document.getElementById('aee-add-source-empty');
    if (emptyBtn) {
        emptyBtn.addEventListener('click', function() {
            document.getElementById('aee-add-source').click();
        });
    }
});
</script>
