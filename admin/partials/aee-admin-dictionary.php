<?php
/**
 * Egyptian Dictionary Management Page
 *
 * @package AI_Editorial_Engine
 */

if ( ! defined( 'WPINC' ) ) die;
if ( ! current_user_can( 'manage_options' ) ) return;

global $wpdb;
$entries = $wpdb->get_results(
    "SELECT * FROM {$wpdb->prefix}aee_dictionary ORDER BY usage_count DESC, formal_word ASC",
    ARRAY_A
);

// Count by context
$contexts = [];
foreach ( $entries as $e ) {
    $ctx = $e['context'] ?: 'general';
    $contexts[ $ctx ] = ( $contexts[ $ctx ] ?? 0 ) + 1;
}
arsort( $contexts );
?>
<div class="aee-wrap" dir="rtl">
    <h1>📚 <?php esc_html_e( 'معجم العامية المصرية', 'ai-editorial-engine' ); ?></h1>

    <div style="margin-bottom:20px; display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
        <button type="button" id="aee-add-dict-entry" class="aee-btn aee-btn-primary">
            ➕ <?php esc_html_e( 'إضافة كلمة', 'ai-editorial-engine' ); ?>
        </button>
        <button type="button" id="aee-import-dict-btn" class="aee-btn aee-btn-secondary">
            📥 <?php esc_html_e( 'استيراد ملف JSON', 'ai-editorial-engine' ); ?>
        </button>
        <button type="button" id="aee-download-example-btn" class="aee-btn aee-btn-secondary" style="font-size:12px;">
            📄 <?php esc_html_e( 'تحميل ملف مثال', 'ai-editorial-engine' ); ?>
        </button>
        <input type="file" id="aee-import-file" accept=".json" style="display:none;">
        
        <span style="font-size:13px; color:#50575e;"><?php echo count( $entries ); ?> كلمة في المعجم</span>

        <?php foreach ( $contexts as $ctx => $count ) : ?>
            <span class="aee-badge aee-badge-processing"><?php echo esc_html( $ctx ); ?>: <?php echo esc_html( $count ); ?></span>
        <?php endforeach; ?>
    </div>

    <!-- Search Filter -->
    <div class="aee-panel" style="padding:12px 16px;">
        <input type="text" id="aee-dict-search" class="aee-input" placeholder="🔍 ابحث في المعجم..." style="max-width:400px;">
    </div>

    <div class="aee-panel">
        <div class="aee-panel-header">
            <h2>📖 <?php esc_html_e( 'الكلمات', 'ai-editorial-engine' ); ?></h2>
        </div>
        <div class="aee-panel-body" style="padding:0;">
            <?php if ( empty( $entries ) ) : ?>
                <div class="aee-empty-state">
                    <span class="dashicons dashicons-book-alt"></span>
                    <p>المعجم فارغ — سيتم تحميل المعجم الافتراضي من ملف JSON تلقائياً عند التشغيل</p>
                    <button id="aee-add-dict-entry" class="aee-btn aee-btn-primary">إضافة كلمة يدوياً</button>
                </div>
            <?php else : ?>
                <table class="aee-table" id="aee-dict-table">
                    <thead>
                        <tr>
                            <th>الكلمة الفصحى</th>
                            <th>العامية المصرية</th>
                            <th>السياق</th>
                            <th>الاستخدام</th>
                            <th>الحالة</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $entries as $entry ) : ?>
                        <tr data-id="<?php echo esc_attr( $entry['id'] ); ?>"
                            data-formal="<?php echo esc_attr( $entry['formal_word'] ); ?>"
                            data-colloquial="<?php echo esc_attr( $entry['colloquial_word'] ); ?>"
                            data-context="<?php echo esc_attr( $entry['context'] ); ?>">
                            <td><strong><?php echo esc_html( $entry['formal_word'] ); ?></strong></td>
                            <td style="color:#2271b1;"><?php echo esc_html( $entry['colloquial_word'] ); ?></td>
                            <td>
                                <span class="aee-badge aee-badge-processing"><?php echo esc_html( $entry['context'] ?: 'general' ); ?></span>
                            </td>
                            <td><?php echo esc_html( $entry['usage_count'] ); ?> مرة</td>
                            <td>
                                <span class="aee-badge <?php echo $entry['is_active'] ? 'aee-badge-active' : 'aee-badge-inactive'; ?>">
                                    <?php echo $entry['is_active'] ? 'نشط' : 'موقف'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="aee-actions">
                                    <button class="aee-btn aee-btn-secondary aee-edit-dict" style="font-size:11px;">تعديل</button>
                                    <button class="aee-btn aee-btn-danger aee-delete-dict" style="font-size:11px;">حذف</button>
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

<!-- Dictionary Entry Modal -->
<div class="aee-modal-backdrop" id="aee-modal-dict-backdrop" role="dialog">
    <div class="aee-modal" dir="rtl">
        <div class="aee-modal-header">
            <h3 id="aee-modal-dict-title">إضافة كلمة جديدة</h3>
            <button class="aee-modal-close">×</button>
        </div>
        <div class="aee-modal-body">
            <form id="aee-dict-form">
                <input type="hidden" name="id" value="">
                <div class="aee-two-col-form">
                    <div class="aee-form-row">
                        <label><?php esc_html_e( 'الكلمة الفصحى', 'ai-editorial-engine' ); ?> *</label>
                        <input type="text" name="formal" class="aee-input" required placeholder="مثال: الآن">
                    </div>
                    <div class="aee-form-row">
                        <label><?php esc_html_e( 'المقابل العامي', 'ai-editorial-engine' ); ?> *</label>
                        <input type="text" name="colloquial" class="aee-input" required placeholder="مثال: دلوقتي">
                    </div>
                </div>
                <div class="aee-form-row">
                    <label><?php esc_html_e( 'السياق', 'ai-editorial-engine' ); ?></label>
                    <select name="context" class="aee-select">
                        <option value="time">وقت (time)</option>
                        <option value="verb">فعل (verb)</option>
                        <option value="pronoun">ضمير (pronoun)</option>
                        <option value="question">استفهام (question)</option>
                        <option value="conjunction">رابط (conjunction)</option>
                        <option value="adverb">ظرف (adverb)</option>
                        <option value="journalistic">صحفي (journalistic)</option>
                        <option value="nouns">اسم (nouns)</option>
                        <option value="expression">تعبير (expression)</option>
                        <option value="general">عام (general)</option>
                    </select>
                </div>
            </form>
        </div>
        <div class="aee-modal-footer">
            <button class="aee-btn aee-btn-secondary aee-btn-cancel">إلغاء</button>
            <button type="button" class="aee-btn aee-btn-primary" id="aee-save-dict-btn">حفظ</button>
        </div>
    </div>
</div>

<script>
// Live search
document.getElementById('aee-dict-search')?.addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#aee-dict-table tbody tr').forEach(function(row) {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(q) ? '' : 'none';
    });
});
</script>
