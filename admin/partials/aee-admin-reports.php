<?php
/**
 * Reports & Self-Learning Analytics Page
 *
 * @package AI_Editorial_Engine
 */

if ( ! defined( 'WPINC' ) ) die;
if ( ! current_user_can( 'manage_options' ) ) return;

global $wpdb;
$prefix = $wpdb->prefix;

$learner  = new AEE_Self_Learner();
$insights = $learner->get_learning_insights();

// Weekly stats
$weekly_fetched   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}aee_queue WHERE fetched_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)" );
$weekly_published = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}aee_queue WHERE status IN ('published','ready') AND fetched_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)" );

// Processing time avg
$avg_time = (float) $wpdb->get_var( "SELECT AVG(processing_time) FROM {$prefix}aee_queue WHERE processing_time > 0" );

// Articles per day (last 7 days)
$daily_stats = $wpdb->get_results(
    "SELECT DATE(fetched_at) as day, COUNT(*) as count, status
     FROM {$prefix}aee_queue
     WHERE fetched_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     GROUP BY DATE(fetched_at), status
     ORDER BY day ASC",
    ARRAY_A
);

// Top sources by article count
$top_sources = $wpdb->get_results(
    "SELECT s.name, COUNT(q.id) as count
     FROM {$prefix}aee_sources s
     LEFT JOIN {$prefix}aee_queue q ON s.id = q.source_id
     GROUP BY s.id, s.name
     ORDER BY count DESC
     LIMIT 5",
    ARRAY_A
);
?>
<div class="aee-wrap" dir="rtl">
    <h1>📈 <?php esc_html_e( 'التقارير والتحليلات', 'ai-editorial-engine' ); ?></h1>

    <!-- Weekly Summary -->
    <div class="aee-stats-grid">
        <div class="aee-stat-card">
            <div class="aee-stat-number"><?php echo esc_html( $weekly_fetched ); ?></div>
            <div class="aee-stat-label">مجلوب هذا الأسبوع</div>
        </div>
        <div class="aee-stat-card success">
            <div class="aee-stat-number"><?php echo esc_html( $weekly_published ); ?></div>
            <div class="aee-stat-label">منشور هذا الأسبوع</div>
        </div>
        <div class="aee-stat-card">
            <div class="aee-stat-number"><?php echo esc_html( round( $insights['approval_rate'] ) ); ?>%</div>
            <div class="aee-stat-label">معدل الموافقة</div>
        </div>
        <div class="aee-stat-card">
            <div class="aee-stat-number"><?php echo $avg_time > 0 ? esc_html( round( $avg_time ) ) . 'ث' : '—'; ?></div>
            <div class="aee-stat-label">متوسط وقت المعالجة</div>
        </div>
        <div class="aee-stat-card">
            <div class="aee-stat-number"><?php echo esc_html( $insights['total_processed'] ); ?></div>
            <div class="aee-stat-label">إجمالي الفيدباك</div>
        </div>
    </div>

    <div class="aee-layout-cols">
        <!-- AI Model Performance -->
        <div class="aee-panel">
            <div class="aee-panel-header">
                <h2>🤖 <?php esc_html_e( 'أداء نماذج AI', 'ai-editorial-engine' ); ?></h2>
            </div>
            <div class="aee-panel-body">
                <?php if ( empty( $insights['model_scores'] ) ) : ?>
                    <p style="color:#50575e; font-size:13px;">لا توجد بيانات كافية بعد — تحتاج لمعالجة 10 مقالات على الأقل</p>
                <?php else : ?>
                    <?php foreach ( $insights['model_scores'] as $model ) : ?>
                        <?php $pct = round( floatval( $model['avg_score'] ) * 100 ); ?>
                        <div class="aee-model-bar">
                            <span style="min-width:80px; font-weight:600;"><?php echo esc_html( $model['ai_model_used'] ?: '—' ); ?></span>
                            <div class="aee-model-bar-track">
                                <div class="aee-model-bar-fill" style="width:<?php echo esc_attr( $pct ); ?>%;"></div>
                            </div>
                            <span style="min-width:50px;"><?php echo esc_html( $pct ); ?>%</span>
                            <span style="color:#50575e; font-size:12px;">(<?php echo esc_html( $model['count'] ); ?> مقال)</span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Top Sources -->
        <div class="aee-panel">
            <div class="aee-panel-header">
                <h2>🔗 <?php esc_html_e( 'أكثر المصادر نشاطاً', 'ai-editorial-engine' ); ?></h2>
            </div>
            <div class="aee-panel-body">
                <?php if ( empty( $top_sources ) ) : ?>
                    <p style="color:#50575e; font-size:13px;">لا توجد بيانات بعد</p>
                <?php else : ?>
                    <?php
                    $max_count = max( array_column( $top_sources, 'count' ) ?: [1] );
                    foreach ( $top_sources as $src ) :
                        $pct = $max_count > 0 ? round( $src['count'] / $max_count * 100 ) : 0;
                    ?>
                        <div class="aee-model-bar" style="margin-bottom:12px;">
                            <span style="min-width:100px; font-weight:600; font-size:12px;"><?php echo esc_html( mb_substr( $src['name'], 0, 15 ) ); ?></span>
                            <div class="aee-model-bar-track">
                                <div class="aee-model-bar-fill" style="width:<?php echo esc_attr( $pct ); ?>%; background:#2271b1;"></div>
                            </div>
                            <span style="min-width:40px; font-size:13px;"><?php echo esc_html( $src['count'] ); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Common Edit Patterns (Self-Learning) -->
    <?php if ( ! empty( $insights['common_changes'] ) ) : ?>
    <div class="aee-panel">
        <div class="aee-panel-header">
            <h2>🧠 <?php esc_html_e( 'أكثر ما يعدّله المحرر', 'ai-editorial-engine' ); ?></h2>
        </div>
        <div class="aee-panel-body">
            <p style="font-size:13px; color:#50575e; margin-bottom:16px;">
                البلجن بيتعلم من التعديلات دي ويحاول يتجنبها في المستقبل 🎓
            </p>
            <table class="aee-table">
                <thead>
                    <tr>
                        <th>نوع التغيير</th>
                        <th>عدد المرات</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $insights['common_changes'] as $change ) : ?>
                    <tr>
                        <td><?php echo esc_html( $change['editor_changes'] ); ?></td>
                        <td><?php echo esc_html( $change['count'] ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Manual Learning Trigger -->
    <div class="aee-panel">
        <div class="aee-panel-header">
            <h2>🔄 <?php esc_html_e( 'التحديث اليدوي للتعلم', 'ai-editorial-engine' ); ?></h2>
        </div>
        <div class="aee-panel-body">
            <p style="font-size:13px; color:#50575e; margin-bottom:14px;">
                البلجن بيتحدث تلقائياً كل أسبوع. لو عندك مقالات جيدة جديدة، اضغط الزر للتحديث الفوري.
            </p>
            <?php
            $settings = get_option( 'aee_settings', [] );
            $last_update = $settings['last_learning_update'] ?? null;
            if ( $last_update ) : ?>
                <div class="aee-alert aee-alert-info">
                    آخر تحديث تلقائي: <?php echo esc_html( $last_update ); ?>
                </div>
            <?php endif; ?>
            <button type="button" class="aee-btn aee-btn-primary" id="aee-trigger-learning">
                🎓 <?php esc_html_e( 'تحديث نماذج التعلم الآن', 'ai-editorial-engine' ); ?>
            </button>
            <div id="aee-learning-result" style="margin-top:10px;"></div>
        </div>
    </div>
</div>

<script>
document.getElementById('aee-trigger-learning')?.addEventListener('click', function() {
    const btn = this;
    const result = document.getElementById('aee-learning-result');
    btn.disabled = true;
    btn.textContent = 'جاري التحديث...';

    fetch(aeeData.ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=aee_trigger_learning&nonce=' + aeeData.nonce
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.textContent = '🎓 تحديث نماذج التعلم الآن';
        result.innerHTML = data.success
            ? '<div class="aee-alert aee-alert-success">✅ ' + (data.data?.message || 'تم التحديث') + '</div>'
            : '<div class="aee-alert aee-alert-error">❌ ' + (data.data?.message || 'حدث خطأ') + '</div>';
    });
});
</script>
