<?php

declare(strict_types=1);

final class CallAnalyzerService
{
    /** @var CallRepository */
    private $repo;
    /** @var OpenAiClient */
    private $openai;
    /** @var string */
    private $uploadDir;

    public function __construct(CallRepository $repo, OpenAiClient $openai, string $uploadDir)
    {
        $this->repo = $repo;
        $this->openai = $openai;
        $this->uploadDir = $uploadDir;
    }

    /**
     * @return array{ok: true, analysis: array<string, mixed>}|array{ok: false, error: string}
     */
    public function processCall(int $id): array
    {
        $row = $this->repo->find($id);
        if ($row === null) {
            return ['ok' => false, 'error' => 'Call not found.'];
        }
        if (!$this->openai->hasKey()) {
            return ['ok' => false, 'error' => 'OPENAI_API_KEY is not set.'];
        }

        $path = $this->uploadDir . '/' . $row['stored_filename'];
        if (!is_file($path)) {
            $this->repo->setStatus($id, 'error', 'Uploaded file missing on disk.');
            return ['ok' => false, 'error' => 'File missing.'];
        }

        $dur = AudioDuration::guess($path);
        if ($dur !== null) {
            $this->repo->saveDuration($id, $dur);
        }

        try {
            $this->repo->setStatus($id, 'processing', null);
            $tr = $this->openai->transcribe($path, (string) $row['original_name']);
            $this->repo->saveTranscript($id, $tr['text']);
            $analysis = $this->openai->analyzeTranscript($tr['text']);
            $this->repo->saveAnalysis($id, $analysis);
            return ['ok' => true, 'analysis' => $analysis];
        } catch (Throwable $e) {
            $this->repo->setStatus($id, 'error', $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
