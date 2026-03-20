<?php

declare(strict_types=1);

final class OpenAiClient
{
    /** @var string */
    private $apiKey;
    /** @var string */
    private $chatModel;
    /** @var string */
    private $whisperModel;

    public function __construct(string $apiKey, string $chatModel, string $whisperModel)
    {
        $this->apiKey = trim($apiKey);
        $this->chatModel = $chatModel;
        $this->whisperModel = $whisperModel;
    }

    public function hasKey(): bool
    {
        return $this->apiKey !== '';
    }

    /** @return array{ text: string } */
    public function transcribe(string $absoluteFilePath, string $filenameForApi): array
    {
        if (!is_file($absoluteFilePath) || !is_readable($absoluteFilePath)) {
            throw new RuntimeException('Could not read audio file for transcription.');
        }
        $mime = 'application/octet-stream';
        if (class_exists('finfo')) {
            $fi = new finfo(FILEINFO_MIME_TYPE);
            $detected = $fi->file($absoluteFilePath);
            if (is_string($detected) && $detected !== '') {
                $mime = $detected;
            }
        }
        $safeName = preg_replace('/[^-a-zA-Z0-9._]+/', '_', $filenameForApi) ?: 'audio.mp3';
        $cfile = new CURLFile($absoluteFilePath, $mime, $safeName);
        $post = ['model' => $this->whisperModel, 'file' => $cfile];

        $ch = curl_init('https://api.openai.com/v1/audio/transcriptions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->apiKey],
            CURLOPT_POSTFIELDS => $post,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 600,
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('Transcription request failed: ' . $err);
        }
        $json = json_decode($raw, true);
        if ($code >= 400) {
            $msg = is_array($json) && isset($json['error']['message'])
                ? (string) $json['error']['message'] : $raw;
            throw new RuntimeException('OpenAI transcription error (' . $code . '): ' . $msg);
        }
        if (!is_array($json) || !isset($json['text'])) {
            throw new RuntimeException('Unexpected transcription response.');
        }
        return ['text' => (string) $json['text']];
    }

    private static function promptsDir(): string
    {
        $schema = <<<'PROMPT'
You are a call quality analyst for customer support/sales calls.
Given the full call transcript, return ONLY valid JSON (no markdown) with this exact structure:
{
  "overall_score": <number 0-100>,
  "sentiment_customer": "positive" | "neutral" | "negative",
  "summary": "<2-4 sentence summary>",
  "key_topics": ["topic1", "topic2"],
  "compliance": {
    "professional_greeting": { "pass": true|false, "note": "short reason" },
    "customer_verification_or_identification": { "pass": true|false, "note": "..." },
    "active_listening": { "pass": true|false, "note": "..." },
    "accurate_information": { "pass": true|false, "note": "..." },
    "resolution_or_next_steps": { "pass": true|false, "note": "..." },
    "professional_closing": { "pass": true|false, "note": "..." }
  },
  "strengths": ["...", "..."],
  "improvements": ["...", "..."],
  "red_flags": []
}
Use "pass": true when evidence supports it; false when missing or poor. Notes must be brief.
PROMPT;

        $payload = [
            'model' => $this->chatModel,
            'messages' => [
                ['role' => 'system', 'content' => $schema],
                ['role' => 'user', 'content' => "Transcript:\n\n" . $transcript],
            ],
            'temperature' => 0.3,
            'response_format' => ['type' => 'json_object'],
        ];

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('Analysis request failed.');
        }
        $json = json_decode($raw, true);
        if ($code >= 400) {
            $msg = is_array($json) && isset($json['error']['message'])
                ? (string) $json['error']['message'] : $raw;
            throw new RuntimeException('OpenAI analysis error (' . $code . '): ' . $msg);
        }
        if (!is_array($json) || !isset($json['choices'][0]['message']['content'])) {
            throw new RuntimeException('Unexpected analysis response.');
        }
        $content = (string) $json['choices'][0]['message']['content'];
        $parsed = json_decode($content, true);
        if (!is_array($parsed)) {
            throw new RuntimeException('Analysis was not valid JSON.');
        }
        return $parsed;
    }
}
