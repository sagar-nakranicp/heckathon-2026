<?php

declare(strict_types=1);

final class CallRepository
{
    /** @var PDO */
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** @return array<int, array<string, mixed>> */
    public function listRecent(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM calls ORDER BY created_at DESC LIMIT :lim'
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM calls WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<string, mixed> */
    public function insertPending(
        string $storedFilename,
        string $originalName,
        string $mime,
        int $sizeBytes
    ): array {
        $now = gmdate('c');
        $stmt = $this->pdo->prepare(
            'INSERT INTO calls (stored_filename, original_name, mime, size_bytes, status, created_at, updated_at)
             VALUES (:sf, :on, :m, :sz, :st, :c, :u)'
        );
        $stmt->execute([
            ':sf' => $storedFilename,
            ':on' => $originalName,
            ':m' => $mime,
            ':sz' => $sizeBytes,
            ':st' => 'pending',
            ':c' => $now,
            ':u' => $now,
        ]);
        $id = (int) $this->pdo->lastInsertId();
        return $this->find($id) ?? [];
    }

    public function setStatus(int $id, string $status, ?string $error = null): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE calls SET status = :st, error_message = :er, updated_at = :u WHERE id = :id'
        );
        $stmt->execute([
            ':st' => $status,
            ':er' => $error,
            ':u' => gmdate('c'),
            ':id' => $id,
        ]);
    }

    public function saveTranscript(int $id, string $transcript): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE calls SET transcript = :t, updated_at = :u WHERE id = :id'
        );
        $stmt->execute([':t' => $transcript, ':u' => gmdate('c'), ':id' => $id]);
    }

    /** @param array<string, mixed> $analysis */
    public function saveAnalysis(int $id, array $analysis): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE calls SET analysis_json = :j, status = :st, updated_at = :u WHERE id = :id'
        );
        $stmt->execute([
            ':j' => json_encode($analysis, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            ':st' => 'done',
            ':u' => gmdate('c'),
            ':id' => $id,
        ]);
    }

    /** @return array{pending: int, processing: int, done: int, error: int} */
    public function countsByStatus(): array
    {
        $rows = $this->pdo->query(
            'SELECT status, COUNT(*) AS c FROM calls GROUP BY status'
        )->fetchAll();
        $out = ['pending' => 0, 'processing' => 0, 'done' => 0, 'error' => 0];
        foreach ($rows as $r) {
            $s = (string) $r['status'];
            if (isset($out[$s])) {
                $out[$s] = (int) $r['c'];
            }
        }
        return $out;
    }

    public function saveDuration(int $id, float $seconds): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE calls SET duration_seconds = :d, updated_at = :u WHERE id = :id'
        );
        $stmt->execute([
            ':d' => round($seconds, 3),
            ':u' => gmdate('c'),
            ':id' => $id,
        ]);
    }

    /** Mean duration in seconds, or null if no rows have duration. */
    public function averageDurationSeconds(): ?float
    {
        $stmt = $this->pdo->query(
            'SELECT AVG(duration_seconds) AS a FROM calls WHERE duration_seconds IS NOT NULL'
        );
        $row = $stmt ? $stmt->fetch() : false;
        if ($row === false || $row['a'] === null) {
            return null;
        }
        $v = (float) $row['a'];
        return $v > 0 ? round($v, 1) : null;
    }

    public function averageScore(): ?float
    {
        $rows = $this->pdo->query(
            "SELECT analysis_json FROM calls WHERE status = 'done' AND analysis_json IS NOT NULL"
        )->fetchAll(PDO::FETCH_COLUMN);
        $scores = [];
        foreach ($rows as $j) {
            $a = json_decode((string) $j, true);
            if (is_array($a) && isset($a['overall_score']) && is_numeric($a['overall_score'])) {
                $scores[] = (float) $a['overall_score'];
            }
        }
        if ($scores === []) {
            return null;
        }
        return round(array_sum($scores) / count($scores), 1);
    }
}
