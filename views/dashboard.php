<?php
/** @var array $config */
/** @var bool $hasOpenAiKey */
/** @var array<int, array<string, mixed>> $calls */
/** @var array{pending: int, processing: int, done: int, error: int} $counts */
/** @var float|null $avg */
/** @var float|null $avgDuration Average duration in seconds across calls that have duration */
?>
<h1>Dashboard</h1>
<p class="lead">Uploaded calls, status, and average quality score.</p>

<?php if (!$hasOpenAiKey) : ?>
<div class="banner warn">
    <strong>No API key.</strong> Copy <code>.env.example</code> to <code>.env</code> and set <code>OPENAI_API_KEY</code>.
</div>
<?php endif; ?>

<section class="cards">
    <div class="card">
        <div class="card-k">Analyzed</div>
        <div class="card-v"><?= (int) $counts['done'] ?></div>
    </div>
    <div class="card">
        <div class="card-k">Pending / processing</div>
        <div class="card-v"><?= (int) $counts['pending'] + (int) $counts['processing'] ?></div>
    </div>
    <div class="card">
        <div class="card-k">Errors</div>
        <div class="card-v"><?= (int) $counts['error'] ?></div>
    </div>
    <div class="card accent">
        <div class="card-k">Avg. quality score</div>
        <div class="card-v"><?= $avg !== null ? Http::esc((string) $avg) . '<span class="unit">/100</span>' : '—' ?></div>
    </div>
</section>

<h2>Recent calls</h2>
<div class="table-wrap">
<table class="table">
    <thead>
        <tr>
            <th>File</th>
            <th>Status</th>
            <th>Score</th>
            <th>Sentiment</th>
            <th>Uploaded</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    <?php if ($calls === []) : ?>
        <tr><td colspan="7" class="muted">No recordings. <a href="<?= Http::esc(Http::url('/?action=upload', $config)) ?>">Upload</a>.</td></tr>
    <?php else : ?>
        <?php foreach ($calls as $c) :
            $score = '—';
            $sent = '—';
            if (!empty($c['analysis_json'])) {
                $aid = json_decode((string) $c['analysis_json'], true);
                if (is_array($aid)) {
                    if (isset($aid['overall_score'])) {
                        $score = (string) $aid['overall_score'];
                    }
                    if (isset($aid['sentiment_customer'])) {
                        $sent = (string) $aid['sentiment_customer'];
                    }
                }
            }
            $st = (string) $c['status'];
            $durSec = isset($c['duration_seconds']) ? (float) $c['duration_seconds'] : null;
            if ($durSec !== null && $durSec <= 0) {
                $durSec = null;
            }
            $durCell = Http::formatDurationSeconds($durSec);
            ?>
        <tr>
            <td class="ellipsis" title="<?= Http::esc((string) $c['original_name']) ?>"><?= Http::esc((string) $c['original_name']) ?></td>
            <td><span class="badge st-<?= Http::esc($st) ?>"><?= Http::esc($st) ?></span></td>
            <td><?= Http::esc($score) ?></td>
            <td><?= Http::esc($sent) ?></td>
            <td class="muted nowrap"><?= Http::esc(Http::formatDisplayTime((string) ($c['created_at'] ?? ''), $config)) ?></td>
            <td><a href="<?= Http::esc(Http::url('/?action=view&id=' . (int) $c['id'], $config)) ?>">View</a></td>
        </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>
</div>
