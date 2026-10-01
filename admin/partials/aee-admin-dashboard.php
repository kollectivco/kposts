<?php
/**
 * Admin Dashboard View
 *
 * @package AI_Editorial_Engine
 */

if ( ! defined( 'WPINC' ) ) die;
if ( ! current_user_can( 'manage_options' ) ) return;

global $wpdb;
$prefix = $wpdb->prefix;

// Stats
$total_fetched   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}aee_queue" );
$total_ready     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}aee_queue WHERE status = 'ready'" );
$total_published = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}aee_queue WHERE status = 'published'" );
$total_pending   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}aee_queue WHERE status = 'pending'" );
$total_failed    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}aee_queue WHERE status = 'failed'" );

$avg_quality = (float) $wpdb->get_var( "SELECT AVG(quality_score) FROM {$prefix}aee_feedback WHERE quality_score > 0" );

// Recent queue items
$recent_items = $wpdb->get_results(
    "SELECT q.*, s.name as source_name FROM {$prefix}aee_queue q
     LEFT JOIN {$prefix}aee_sources s ON q.source_id = s.id
     ORDER BY q.fetched_at DESC LIMIT 10",
    ARRAY_A
);

// Active sources
$sources = $wpdb->get_results(
    "SELECT * FROM {$prefix}aee_sources WHERE is_active = 1 ORDER BY name",
    ARRAY_A
);
?>
<div class="aee-wrap" dir="rtl">
    <h1>🤖 <?php esc_html_e( 'AI Editorial Engine — لوحة التحكم', 'ai-editorial-engine' ); ?></h1>

    <!-- Quick Actions -->
    <div style="margin-bottom:20px; display:flex; gap:10px; flex-wrap:wrap;">
        <button class="aee-btn aee-btn-primary aee-btn-fetch" data-source-id="0">
            📡 <?php esc_html_e( 'اجلب الآن (كل المصادر)', 'ai-editorial-engine' ); ?>
        </button>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aee-sources' ) ); ?>" class="aee-btn aee-btn-secondary">
            ➕ <?php esc_html_e( 'أضف مصدراً', 'ai-editorial-engine' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aee-ai-settings' ) ); ?>" class="aee-btn aee-btn-secondary">
            ⚙️ <?php esc_html_e( 'إعدادات AI', 'ai-editorial-engine' ); ?>
        </a>
    </div>

    <!-- Stats Cards -->
    <div class="aee-stats-grid">
        <div class="aee-stat-card">
            <div class="aee-stat-number" data-stat="total"><?php echo esc_html( $total_fetched ); ?></div>
            <div class="aee-stat-label">إجمالي المجلوب</div>
        </div>
        <div class="aee-stat-card success">
            <div class="aee-stat-number" data-stat="ready"><?php echo esc_html( $total_ready ); ?></div>
            <div class="aee-stat-label">جاهز للمراجعة</div>
        </div>
        <div class="aee-stat-card">
            <div class="aee-stat-number" data-stat="published"><?php echo esc_html( $total_published ); ?></div>
            <div class="aee-stat-label">تم نشره</div>
        </div>
        <div class="aee-stat-card warning">
            <div class="aee-stat-number" data-stat="pending"><?php echo esc_html( $total_pending ); ?></div>
            <div class="aee-stat-label">في الانتظار</div>
        </div>
        <div class="aee-stat-card danger">
            <div class="aee-stat-number" data-stat="failed"><?php echo esc_html( $total_failed ); ?></div>
            <div class="aee-stat-label">فشل</div>
        </div>
        <div class="aee-stat-card">
            <div class="aee-stat-number"><?php echo $avg_quality > 0 ? esc_html( round( $avg_quality * 100 ) ) . '%' : '—'; ?></div>
            <div class="aee-stat-label">متوسط الجودة</div>
        </div>
    </div>

    <div id="aee-queue-stats"></div>

    <!-- Recent Queue -->
    <div class="aee-panel">
        <div class="aee-panel-header">
            <h2>📋 <?php esc_html_e( 'آخر المقالات في القائمة', 'ai-editorial-engine' ); ?></h2>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aee-sources' ) ); ?>" class="aee-btn aee-btn-secondary" style="font-size:12px;">عرض الكل</a>
        </div>
        <div class="aee-panel-body" style="padding:0;">
            <?php if ( empty( $recent_items ) ) : ?>
                <div class="aee-empty-state">
                    <span class="dashicons dashicons-rss"></span>
                    <p>لا توجد مقالات بعد — أضف مصادر واضغط «اجلب الآن»</p>
                </div>
            <?php else : ?>
                <table class="aee-table">
                    <thead>
                        <tr>
                            <th>العنوان</th>
                            <th>المصدر</th>
                            <th>الحالة</th>
                            <th>النموذج</th>
                            <th>التوقيت</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $recent_items as $item ) : ?>
                        <tr>
                            <td>
                                <strong style="font-size:13px;"><?php echo esc_html( mb_substr( $item['original_title'] ?: '—', 0, 60 ) ); ?></strong>
                                <div class="aee-queue-url"><?php echo esc_html( $item['original_url'] ); ?></div>
                            </td>
                            <td><?php echo esc_html( $item['source_name'] ?: '—' ); ?></td>
                            <td>
                                <span class="aee-badge aee-badge-<?php echo esc_attr( $item['status'] ); ?>">
                                    <?php echo esc_html( $item['status'] ); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html( $item['ai_model_used'] ?: '—' ); ?></td>
                            <td style="font-size:12px; color:#50575e;"><?php echo esc_html( $item['fetched_at'] ); ?></td>
                            <td>
                                <div class="aee-actions">
                                    <?php if ( $item['status'] === 'pending' ) : ?>
                                        <button class="aee-btn aee-btn-primary aee-btn-process" style="font-size:11px;" data-item-id="<?php echo esc_attr( $item['id'] ); ?>">معالجة</button>
                                    <?php endif; ?>
                                    <?php if ( $item['wp_post_id'] ) : ?>
                                        <a href="<?php echo esc_url( get_edit_post_link( $item['wp_post_id'] ) ); ?>" class="aee-btn aee-btn-secondary" style="font-size:11px;" target="_blank">تحرير</a>
                                    <?php endif; ?>
                                    <?php if ( $item['original_url'] ) : ?>
                                        <a href="<?php echo esc_url( $item['original_url'] ); ?>" class="aee-btn aee-btn-secondary" style="font-size:11px;" target="_blank">المصدر</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Active Sources Summary -->
    <?php if ( ! empty( $sources ) ) : ?>
    <div class="aee-panel">
        <div class="aee-panel-header">
            <h2>🔗 <?php esc_html_e( 'المصادر النشطة', 'ai-editorial-engine' ); ?></h2>
        </div>
        <div class="aee-panel-body" style="padding:0;">
            <table class="aee-table">
                <thead>
                    <tr>
                        <th>الاسم</th>
                        <th>النوع</th>
                        <th>آخر جلب</th>
                        <th>إجراء</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $sources as $source ) : ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html( $source['name'] ); ?></strong>
                            <div style="font-size:12px;color:#50575e;direction:ltr;"><?php echo esc_html( $source['url'] ); ?></div>
                        </td>
                        <td><span class="aee-badge aee-badge-active"><?php echo esc_html( strtoupper( $source['type'] ) ); ?></span></td>
                        <td style="font-size:12px;"><?php echo $source['last_fetched'] ? esc_html( $source['last_fetched'] ) : 'لم يُجلب بعد'; ?></td>
                        <td>
                            <button class="aee-btn aee-btn-secondary aee-btn-fetch" style="font-size:11px;" data-source-id="<?php echo esc_attr( $source['id'] ); ?>">اجلب الآن</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
