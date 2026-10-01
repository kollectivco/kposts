<?php
class AEE_Activator {
	public static function activate() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$tables = [
			"CREATE TABLE {$wpdb->prefix}aee_sources (
				id int(11) NOT NULL AUTO_INCREMENT,
				name varchar(255) NOT NULL,
				url varchar(500) NOT NULL,
				type enum('rss','scrape') DEFAULT 'rss',
				fetch_interval int(11) DEFAULT 3600,
				last_fetched datetime,
				is_active tinyint(1) DEFAULT 1,
				keywords_whitelist text,
				keywords_blacklist text,
				created_at datetime DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id)
			) $charset_collate;",

			"CREATE TABLE {$wpdb->prefix}aee_queue (
				id int(11) NOT NULL AUTO_INCREMENT,
				source_id int(11) NOT NULL,
				original_url varchar(500) NOT NULL,
				original_title text,
				original_content longtext,
				original_language varchar(10),
				translated_content longtext,
				processed_content longtext,
				status enum('pending','translating','processing','ready','published','failed') DEFAULT 'pending',
				ai_model_used varchar(50),
				processing_time float,
				wp_post_id int(11),
				error_log text,
				fetched_at datetime DEFAULT CURRENT_TIMESTAMP,
				published_at datetime,
				PRIMARY KEY (id)
			) $charset_collate;",

			"CREATE TABLE {$wpdb->prefix}aee_feedback (
				id int(11) NOT NULL AUTO_INCREMENT,
				queue_id int(11) NOT NULL,
				wp_post_id int(11) NOT NULL,
				editor_changes text,
				original_text longtext,
				revised_text longtext,
				feedback_type enum('approved','edited','rejected') DEFAULT 'approved',
				quality_score float,
				views int(11) DEFAULT 0,
				shares int(11) DEFAULT 0,
				created_at datetime DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id)
			) $charset_collate;",

			"CREATE TABLE {$wpdb->prefix}aee_dictionary (
				id int(11) NOT NULL AUTO_INCREMENT,
				formal_word varchar(255) NOT NULL,
				colloquial_word varchar(255) NOT NULL,
				context varchar(100),
				usage_count int(11) DEFAULT 0,
				is_active tinyint(1) DEFAULT 1,
				PRIMARY KEY (id)
			) $charset_collate;",

			"CREATE TABLE {$wpdb->prefix}aee_style_log (
				id int(11) NOT NULL AUTO_INCREMENT,
				pattern_type varchar(100),
				ai_model varchar(50),
				prompt_version varchar(20),
				quality_score float,
				created_at datetime DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id)
			) $charset_collate;"
		];

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		foreach ( $tables as $table ) {
			dbDelta( $table );
		}
	}

	public static function seed_dictionary(): int {
		global $wpdb;
		$table = $wpdb->prefix . 'aee_dictionary';
		
		// check if empty
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		if ( $count > 0 ) {
			return 0; // Already seeded
		}
		
		$json_file = AEE_PLUGIN_DIR . 'data/egyptian-dictionary.json';
		if ( ! file_exists( $json_file ) ) {
			return 0;
		}
		
		$json = file_get_contents( $json_file );
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return 0;
		}
		
		$inserted = 0;
		foreach ( $data as $entry ) {
			if ( ! empty( $entry['formal'] ) && ! empty( $entry['colloquial'] ) ) {
				$wpdb->insert( $table, [
					'formal_word'     => sanitize_text_field( $entry['formal'] ),
					'colloquial_word' => sanitize_text_field( $entry['colloquial'] ),
					'context'         => sanitize_text_field( $entry['context'] ?? 'general' ),
					'is_active'       => 1
				] );
				$inserted++;
			}
		}
		return $inserted;
	}
}
