<?php
/**
 * OpenAI GPT-4o API Client
 *
 * @package AI_Editorial_Engine
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class AEE_OpenAI {

    private string $api_key;
    private string $default_model;

    public function __construct( string $api_key = '' ) {
        $this->api_key       = $api_key ?: aee_get_api_key( 'openai_api_key' );
        $this->default_model = 'gpt-4o';
    }

    /**
     * إرسال طلب لـ GPT-4o
     *
     * @param string $prompt
     * @param string $system_prompt
     * @param string $model
     * @param float  $temperature
     * @return string|WP_Error
     */
    public function complete( string $prompt, string $system_prompt = '', string $model = '', float $temperature = 0.7 ) {
        if ( empty( $this->api_key ) ) {
            return new WP_Error( 'no_api_key', 'OpenAI API key is not set.' );
        }

        $model    = $model ?: $this->default_model;
        $messages = [];

        if ( ! empty( $system_prompt ) ) {
            $messages[] = [ 'role' => 'system', 'content' => $system_prompt ];
        }

        $messages[] = [ 'role' => 'user', 'content' => $prompt ];

        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
            'body'    => wp_json_encode( [
                'model'       => $model,
                'messages'    => $messages,
                'temperature' => $temperature,
                'max_tokens'  => 4096,
            ] ),
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 90,
        ] );

        return $this->parse_response( $response );
    }

    /**
     * توليد عنوان SEO للمقال
     *
     * @param string $content
     * @return string|WP_Error
     */
    public function generate_seo_title( string $content ) {
        $system = 'أنت خبير SEO وكتابة عناوين صحفية بالعربية. العناوين يجب أن تكون جذابة، صادقة، وتحتوي على الكلمات المفتاحية المهمة.';
        $prompt = "اكتب عنوان SEO احترافي للمحتوى التالي. لا يتجاوز 70 حرف. استخدم كلمات مفتاحية طبيعية:\n\n{$content}";
        return $this->complete( $prompt, $system, '', 0.5 );
    }

    /**
     * هيكلة المقال (ليد + جسم + خاتمة)
     *
     * @param string $content
     * @return string|WP_Error
     */
    public function structure_article( string $content ) {
        $system = 'أنت محرر صحفي متخصص في هيكلة المقالات الإخبارية العربية وفق أساليب الصحافة الحديثة.';
        $prompt = "أعد هيكلة المحتوى التالي كمقال صحفي متكامل:\n\n{$content}\n\nالهيكل المطلوب:\n[عنوان رئيسي]\n[ليد: فقرة افتتاحية قوية تضم أهم المعلومات]\n[الجسم: فقرات تفصيلية متسلسلة]\n[الخاتمة: سياق أوسع أو تطورات متوقعة]\n\nأرسل المقال فقط بدون تعليقات.";
        return $this->complete( $prompt, $system );
    }

    /**
     * توليد وصف meta للـ SEO
     *
     * @param string $content
     * @return string|WP_Error
     */
    public function generate_meta_description( string $content ) {
        $system = 'أنت خبير SEO. اكتب وصفاً موجزاً وجذاباً.';
        $prompt = "اكتب وصف meta لمحرك البحث للمحتوى التالي. 150-160 حرفاً فقط:\n\n" . mb_substr( $content, 0, 1000 );
        return $this->complete( $prompt, $system, '', 0.5 );
    }

    /**
     * اقتراح كلمات مفتاحية
     *
     * @param string $content
     * @return string|WP_Error
     */
    public function suggest_keywords( string $content ) {
        $system = 'أنت خبير SEO عربي.';
        $prompt = "استخرج 10 كلمات مفتاحية من المحتوى التالي مفصولة بفاصلة:\n\n" . mb_substr( $content, 0, 1000 );
        return $this->complete( $prompt, $system, '', 0.3 );
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
            $message = $data['error']['message'] ?? "OpenAI HTTP {$code}";
            return new WP_Error( 'openai_error', $message );
        }

        return $data['choices'][0]['message']['content'] ?? '';
    }
}
