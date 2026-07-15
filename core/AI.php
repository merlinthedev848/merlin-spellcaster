<?php
declare(strict_types=1);

/**
 * AI Service Class
 * Provides a unified interface for generating AI completions using the configured provider (OpenClaw / OpenAI).
 */
class AI {
    /**
     * Generates a response from the configured AI provider.
     * 
     * @param string $systemPrompt The system instruction context.
     * @param string $userPrompt The user's input prompt.
     * @param string $responseFormat 'text' or 'json_object'.
     * @param array $options Additional options (e.g. temperature, max_tokens).
     * @return string The generated content (JSON string or plain text).
     * @throws Exception If the AI provider is not configured or an error occurs.
     */
    public static function generate(string $systemPrompt, string $userPrompt, string $responseFormat = 'text', array $options = []): string {
        $endpoint = getSetting('ai_endpoint', '');
        $model = getSetting('ai_model', '');
        $apiKey = getSetting('ai_key', '');

        if (empty($endpoint) || empty($model)) {
            throw new Exception('AI Provider is not fully configured in AI Settings.');
        }

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt]
            ]
        ];

        if ($responseFormat === 'json_object') {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        // Merge any additional options overrides
        $payload = array_merge($payload, $options);

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        
        $headers = ['Content-Type: application/json'];
        if (!empty($apiKey)) {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $response = curl_exec($ch);
        $err = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) {
            throw new Exception('Connection to AI Provider failed: ' . $err);
        }
        if ($status < 200 || $status >= 300) {
            throw new Exception("AI Provider returned HTTP $status. Response: $response");
        }

        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Failed to parse JSON response from AI provider.");
        }

        if (isset($result['error'])) {
            $msg = $result['error']['message'] ?? 'Unknown API error';
            throw new Exception("AI Provider API Error: " . $msg);
        }

        $content = $result['choices'][0]['message']['content'] ?? null;
        if ($content === null) {
            throw new Exception('AI Provider returned an unexpected response structure.');
        }

        return $content;
    }
}
