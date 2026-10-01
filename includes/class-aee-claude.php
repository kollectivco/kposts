<?php
/**
 * Claude (Anthropic) API Client
 *
 * @package AI_Editorial_Engine
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class AEE_Claude {

    private string $api_key;
    private string $default_model;

    public function __construct( string $api_key = '' ) {
        $this->api_key       = $api_key ?: aee_get_api_key( 'claude_api_key' );
        $this->default_model = 'claude-3-5-sonnet-20241022';
    }

    /**
     * إرسال طلب لـ Claude
     *
     * @param string $prompt
     * @param string $system_prompt
     * @param string $model
     * @return string|WP_Error
     */
    public function complete( string $prompt, string $system_prompt = '', string $model = '' ) {
        if ( empty( $this->api_key ) ) {
            return new WP_Error( 'no_api_key', 'Claude API key is not set.' );
        }

        $model = $model ?: $this->default_model;

        $body = [
            'model'      => $model,
            'max_tokens' => 4096,
            'messages'   => [
                [ 'role' => 'user', 'content' => $prompt ],
            ],
        ];

        if ( ! empty( $system_prompt ) ) {
            $body['system'] = $system_prompt;
        }

        $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
            'body'    => wp_json_encode( $body ),
            'headers' => [
                'x-api-key'         => $this->api_key,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ],
            'timeout' => 90,
        ] );

        return $this->parse_response( $response );
    }

    /**
     * إعادة صياغة بأسلوب أدبي
     *
     * @param string $text
     * @param string $style_prompt
     * @return string|WP_Error
     */
    public function rewrite( string $text, string $style_prompt = '' ) {
        $system = $style_prompt ?: 'أنت كاتب ومحرر صحفي بارع متخصص في الأسلوب الصحفي العربي الراقي.';
        $prompt = "أعد صياغة النص التالي بأسلوب أدبي صحفي راقٍ مع الحفاظ على جميع المعلومات:\n\n{$text}\n\nالنتيجة المطلوبة:\n- أسلوب سلس وجذاب\n- جمل متوازنة الطول\n- بداية لافتة\n- انتقالات سلسة بين الفقرات\n- خاتمة مؤثرة";
        return $this->complete( $prompt, $system );
    }

    /**
     * التدقيق اللغوي والأسلوبي
     *
     * @param string $text
     * @return string|WP_Error
     */
    public function proofread( string $text ) {
        $system = 'أنت مدقق لغوي وأسلوبي محترف متخصص في اللغة العربية الصحفية.';
        $prompt = "دقّق النص التالي لغوياً وأسلوبياً وأعد كتابته بعد التصحيح:\n\n{$text}";
        return $this->complete( $prompt, $system );
    }

    /**
     * توليد عنوان جذاب
     *
     * @param string $content
     * @param string $magazine_tone
     * @return string|WP_Error
     */
    public function generate_headline( string $content, string $magazine_tone = 'neutral' ) {
        $tone_map = [
            'formal'   => 'رسمية وجادة',
            'neutral'  => 'محايدة ومهنية',
            'local'    => 'محلية ودافئة',
            'satirical'=> 'ساخرة وذكية',
        ];
        $tone_desc = $tone_map[ $magazine_tone ] ?? 'محايدة ومهنية';

        $system = 'أنت خبير في كتابة العناوين الصحفية الجذابة.';
        $prompt = "بناءً على المحتوى التالي، اكتب 3 عناوين صحفية بنبرة {$tone_desc}. اختر أقصر كلمة تعبّر وتجذب:\n\n{$content}\n\nأعطني 3 خيارات فقط، كل عنوان في سطر مستقل رقمه.";

        return $this->complete( $prompt, $system );
    }

    /**
     * اختبار الاتصال
     *
     * @return bool|string
     */
    public function test_connection() {
        $result = $this->complete( 'قل "متصل" فقط.' );
        if ( is_wp_error( $result ) ) return $result->get_error_message();
        return true;
    }

    /**
     * Parse API response
     *
     * @param mixed $response
     * @return string|WP_Error
     */
    private function parse_response( $response ) {
        if ( is_wp_error( $response ) ) return $response;

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( $code !== 200 ) {
            $message = $data['error']['message'] ?? "Claude HTTP {$code}";
            return new WP_Error( 'claude_error', $message );
        }

        if ( $data['stop_reason'] === 'max_tokens' ) {
            // Partial result — still usable
            return $data['content'][0]['text'] ?? '';
        }

        return $data['content'][0]['text'] ?? '';
    }
}
