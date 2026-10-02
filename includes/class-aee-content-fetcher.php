<?php
/**
 * AI Content Fetcher — جلب المحتوى من RSS والمواقع الخارجية
 *
 * @package AI_Editorial_Engine
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class AEE_Content_Fetcher {

    /**
     * جلب المحتوى من RSS Feed
     *
     * @param array $source Source row from DB
     * @return int Number of items fetched
     */
    public function fetch_from_rss( array $source ): int {
        if ( ! function_exists( 'fetch_feed' ) ) {
            include_once ABSPATH . WPINC . '/feed.php';
        }

        $rss = fetch_feed( $source['url'] );

        if ( is_wp_error( $rss ) ) {
            $this->log_error( $source['id'], $rss->get_error_message() );
            return 0;
        }

        $max_items  = $rss->get_item_quantity( 10 );
        $rss_items  = $rss->get_items( 0, $max_items );
        $count      = 0;

        foreach ( $rss_items as $item ) {
            $url   = $item->get_permalink();
            $title = $item->get_title();

            // Skip if already in queue
            if ( $this->is_url_in_queue( $url ) ) {
                continue;
            }

            $content = $item->get_content();
            if ( empty( $content ) ) {
                $content = $item->get_description();
            }

            // Apply keyword filters
            $combined = $title . ' ' . $content;
            if ( ! $this->filter_by_keywords(
                $combined,
                $source['keywords_whitelist'] ?? '',
                $source['keywords_blacklist'] ?? ''
            ) ) {
                continue;
            }

            $data = [
                'source_id'        => intval( $source['id'] ),
                'original_url'     => sanitize_url( $url ),
                'original_title'   => sanitize_text_field( $title ),
                'original_content' => wp_kses_post( $content ),
                'original_language'=> 'auto',
                'status'           => 'pending',
                'fetched_at'       => current_time( 'mysql' ),
            ];

            if ( $this->save_to_queue( $data ) ) {
                $count++;
            }
        }

        // Update last_fetched on source
        $this->update_source_last_fetched( $source['id'] );

        return $count;
    }

    /**
     * جلب وتنظيف محتوى صفحة ويب
     *
     * @param string $url
     * @return string|false
     */
    public function fetch_from_url( string $url ) {
        $response = wp_remote_get( $url, [
            'timeout'    => 30,
            'user-agent' => 'Mozilla/5.0 (compatible; AEE-Bot/1.0)',
            'sslverify'  => false,
        ] );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            return false;
        }

        $body = wp_remote_retrieve_body( $response );

        // Extract readable content using basic DOM parsing
        return $this->extract_readable_content( $body );
    }

    /**
     * استخلاص النص القابل للقراءة من HTML
     *
     * @param string $html
     * @return string
     */
    private function extract_readable_content( string $html ): string {
        if ( empty( $html ) ) return '';

        // Remove scripts, styles, nav, footer, ads
        $html = preg_replace(
            '/<(script|style|nav|footer|header|aside|form|iframe)[^>]*>.*?<\/\1>/si',
            '',
            $html
        );

        // Extract article/main content if available
        if ( preg_match( '/<article[^>]*>(.*?)<\/article>/si', $html, $matches ) ) {
            $html = $matches[1];
        } elseif ( preg_match( '/<main[^>]*>(.*?)<\/main>/si', $html, $matches ) ) {
            $html = $matches[1];
        } elseif ( preg_match( '/<div[^>]*class="[^"]*(?:content|article|post|entry)[^"]*"[^>]*>(.*?)<\/div>/si', $html, $matches ) ) {
            $html = $matches[1];
        }

        // Convert block tags to newlines before stripping
        $html = preg_replace( '/<(p|br|div|h[1-6]|li)[^>]*>/i', "\n", $html );

        // Strip all remaining tags
        $text = strip_tags( $html );

        // Clean up whitespace
        $text = preg_replace( '/\n{3,}/', "\n\n", $text );
        $text = preg_replace( '/[ \t]+/', ' ', $text );
        $text = trim( $text );

        return $text;
    }

    /**
     * فلترة المحتوى بالكلمات المفتاحية
     *
     * @param string $content
     * @param string $whitelist Comma-separated keywords (must contain at least one)
     * @param string $blacklist Comma-separated keywords (must contain none)
     * @return bool
     */
    public function filter_by_keywords( string $content, string $whitelist, string $blacklist ): bool {
        $content_lower = mb_strtolower( $content );

        // Blacklist check — if any blacklist word found, reject
        if ( ! empty( $blacklist ) ) {
            $blacklist_words = array_filter( array_map( 'trim', explode( ',', $blacklist ) ) );
            foreach ( $blacklist_words as $word ) {
                if ( mb_strpos( $content_lower, mb_strtolower( $word ) ) !== false ) {
                    return false;
                }
            }
        }

        // Whitelist check — must contain at least one whitelist word
        if ( ! empty( $whitelist ) ) {
            $whitelist_words = array_filter( array_map( 'trim', explode( ',', $whitelist ) ) );
            if ( ! empty( $whitelist_words ) ) {
                foreach ( $whitelist_words as $word ) {
                    if ( mb_strpos( $content_lower, mb_strtolower( $word ) ) !== false ) {
                        return true;
                    }
                }
                return false; // No whitelist word found
            }
        }

        return true;
    }

    /**
     * حفظ عنصر في قائمة الانتظار
     *
     * @param array $data
     * @return bool
     */
    public function save_to_queue( array $data ): bool {
        global $wpdb;

        $result = $wpdb->insert(
            $wpdb->prefix . 'aee_queue',
            $data,
            [
                '%d', // source_id
                '%s', // original_url
                '%s', // original_title
                '%s', // original_content
                '%s', // original_language
                '%s', // status
                '%s', // fetched_at
            ]
        );

        return $result !== false;
    }

    /**
     * تشغيل الجلب المجدول لكل المصادر النشطة
     */
    public function run_scheduled_fetch(): void {
        global $wpdb;

        $sources = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}aee_sources WHERE is_active = 1",
            ARRAY_A
        );

        if ( empty( $sources ) ) return;

        // Respect daily article limit
        $limit     = intval( aee_get_setting( 'daily_article_limit', 50 ) );
        $today_count = $this->get_today_fetched_count();

        foreach ( $sources as $source ) {
            if ( $today_count >= $limit ) break;

            if ( $source['type'] === 'rss' ) {
                $fetched = $this->fetch_from_rss( $source );
            } else {
                $fetched = $this->fetch_from_scrape( $source );
            }
            $today_count += $fetched;
        }
    }

    /**
     * جلب المحتوى من موقع (Scraping)
     *
     * @param array $source
     * @return int
     */
    public function fetch_from_scrape( array $source ): int {
        $url = $source['url'];
        $response = wp_remote_get( $url, [
            'timeout'    => 15,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ] );

        if ( is_wp_error( $response ) ) {
            $this->log_error( $source['id'], $response->get_error_message() );
            return 0;
        }

        $html = wp_remote_retrieve_body( $response );
        if ( empty( $html ) ) return 0;

        // Find all href links
        preg_match_all( '/href=["\']([^"\']+)["\']/i', $html, $matches );
        if ( empty( $matches[1] ) ) return 0;

        $links = array_unique( $matches[1] );
        $base_url = parse_url( $url, PHP_URL_SCHEME ) . '://' . parse_url( $url, PHP_URL_HOST );

        $count = 0;
        foreach ( $links as $link ) {
            if ( $count >= 5 ) break;

            if ( strpos( $link, 'http' ) !== 0 ) {
                if ( strpos( $link, '/' ) === 0 ) {
                    $link = rtrim( $base_url, '/' ) . $link;
                } else {
                    continue;
                }
            }

            // 1. Must be from the same domain
            $link_host = parse_url( $link, PHP_URL_HOST );
            $base_host = parse_url( $base_url, PHP_URL_HOST );
            if ( str_replace( 'www.', '', $link_host ?? '' ) !== str_replace( 'www.', '', $base_host ?? '' ) ) {
                continue;
            }

            // 2. Ignore assets and static files
            if ( preg_match( '/\.(css|js|png|jpg|jpeg|gif|svg|pdf|json|xml)$/i', parse_url( $link, PHP_URL_PATH ) ?? '' ) ) {
                continue;
            }

            // 3. Ignore common non-article paths
            if ( preg_match( '/\/(category|tag|author|page|search)\//i', $link ) ) {
                continue;
            }

            // 4. Simple heuristic for article links (must have some path length)
            $path = parse_url( $link, PHP_URL_PATH ) ?? '';
            if ( strlen( $path ) < 10 || $path === '/' ) {
                continue;
            }

            if ( $this->is_url_in_queue( $link ) ) continue;

            $article_response = wp_remote_get( $link, [
                'timeout'    => 15,
                'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ] );

            if ( is_wp_error( $article_response ) ) continue;

            $article_html = wp_remote_retrieve_body( $article_response );
            $content = $this->extract_readable_content( $article_html );
            
            // Relaxed content length check in case of short news or aggressive stripping
            if ( empty( $content ) || mb_strlen( trim( strip_tags( $content ) ) ) < 100 ) continue;

            $title = '';
            if ( preg_match( '/<title>(.*?)<\/title>/is', $article_html, $t_matches ) ) {
                $title = trim( strip_tags( $t_matches[1] ) );
            }
            if ( empty( $title ) ) $title = 'مقال مسحوب: ' . wp_date('Y-m-d H:i');

            // Apply keyword filters
            $combined = $title . ' ' . $content;
            if ( ! $this->filter_by_keywords(
                $combined,
                $source['keywords_whitelist'] ?? '',
                $source['keywords_blacklist'] ?? ''
            ) ) {
                continue;
            }

            $data = [
                'source_id'        => $source['id'],
                'original_url'     => sanitize_url( $link ),
                'original_title'   => sanitize_text_field( $title ),
                'original_content' => wp_kses_post( $content ),
            ];

            if ( $this->save_to_queue( $data ) ) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * تسجيل جلب المحتوى في WP-Cron
     */
    public static function schedule_cron(): void {
        if ( ! wp_next_scheduled( 'aee_fetch_content' ) ) {
            wp_schedule_event( time(), 'hourly', 'aee_fetch_content' );
        }
    }

    /**
     * هل الرابط موجود بالفعل في القائمة؟
     *
     * @param string $url
     * @return bool
     */
    public function is_url_in_queue( string $url ): bool {
        global $wpdb;
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}aee_queue WHERE original_url = %s",
                $url
            )
        );
        return intval( $count ) > 0;
    }

    /**
     * عدد المقالات المجلوبة اليوم
     *
     * @return int
     */
    private function get_today_fetched_count(): int {
        global $wpdb;
        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aee_queue WHERE DATE(fetched_at) = CURDATE()"
        );
        return intval( $count );
    }

    /**
     * تحديث وقت آخر جلب للمصدر
     *
     * @param int $source_id
     */
    private function update_source_last_fetched( int $source_id ): void {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'aee_sources',
            [ 'last_fetched' => current_time( 'mysql' ) ],
            [ 'id' => $source_id ],
            [ '%s' ],
            [ '%d' ]
        );
    }

    /**
     * تسجيل خطأ في log المصدر
     *
     * @param int    $source_id
     * @param string $message
     */
    private function log_error( int $source_id, string $message ): void {
        error_log( "[AEE] Content Fetcher Error - Source #{$source_id}: {$message}" );
    }
}

// Hook the scheduled fetch
add_action( 'aee_fetch_content', function () {
    $fetcher = new AEE_Content_Fetcher();
    $fetcher->run_scheduled_fetch();
} );
