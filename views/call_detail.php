<?php
/** @var array $config */
/** @var array<string, mixed> $call */
/** @var array<string, mixed>|null $analysis */
/** @var bool $hasOpenAiKey */
/** @var bool $hasAudio */
/** @var float|null $avgCallDurationSeconds Fleet average duration (seconds); null if unknown */
$id = (int) $call['id'];
$originalName = (string) ($call['original_name'] ?? '');
$analyzeUrl = Http::url('/?action=analyze', $config);
$titleName = basename((string) ($call['original_name'] ?? ''));
if ($titleName === '') {
    $titleName = 'Call #' . $id;
}

$chartScore = null;
$chartCompliance = null;
if (is_array($analysis)) {
    $rawScore = isset($analysis['overall_score']) ? (float) $analysis['overall_score'] : 0.0;
    $chartScore = max(0.0, min(100.0, $rawScore));
    $compLabels = [];
    $barColors = [];
    $passedFlags = [];
    $comp = $analysis['compliance'] ?? [];
    if (is_array($comp)) {
        foreach ($comp as $label => $item) {
            if (!is_array($item)) {
                continue;
            }
            $human = ucwords(str_replace('_', ' ', (string) $label));
            if (function_exists('mb_strlen') && mb_strlen($human) > 28) {
                $human = mb_substr($human, 0, 25) . '…';
            } elseif (strlen($human) > 28) {
                $human = substr($human, 0, 25) . '…';
            }
            $compLabels[] = $human;
            $pass = !empty($item['pass']);
            $passedFlags[] = $pass;
            // Horizontal bars: full width (100); color shows pass (green) vs not met (red)
            $barColors[] = $pass ? '#34d399' : '#f87171';
        }
    }
    if ($compLabels !== []) {
        $n = count($compLabels);
        $chartCompliance = [
            'labels' => $compLabels,
            'barColors' => $barColors,
            'barValues' => array_fill(0, $n, 100),
            'passed' => $passedFlags,
        ];
    }
}
$sentiment = 'neutral';
if (is_array($analysis)) {
    $sentiment = strtolower((string) ($analysis['sentiment_customer'] ?? 'neutral'));
}
$sentimentClass = preg_replace('/[^a-z]/', '', $sentiment);
if ($sentimentClass === '') {
    $sentimentClass = 'neutral';
}
$nComp = isset($chartCompliance['labels']) ? count($chartCompliance['labels']) : 0;
$complianceChartHeight = $nComp > 0 ? max(200, min(520, $nComp * 32 + 72)) : 240;
$thisDurationSec = isset($call['duration_seconds']) ? (float) $call['duration_seconds'] : null;
if ($thisDurationSec !== null && $thisDurationSec <= 0) {
    $thisDurationSec = null;
}
$fleetAvgDur = isset($avgCallDurationSeconds) ? $avgCallDurationSeconds : null;
$durLabel = Http::formatDurationSeconds($thisDurationSec);
?>
<h1 class="call-title"><?= Http::esc($titleName) ?></h1>
<p class="muted">
    Call #<?= $id ?> · <?= Http::esc((string) $call['original_name']) ?> · <?= Http::esc((string) $call['mime']) ?> · <?= (int) $call['size_bytes'] ?> bytes
    · <strong>Call duration</strong> <?= Http::esc($durLabel) ?>
</p>
<p class="muted small">Uploaded: <?= Http::esc(Http::formatDisplayTime((string) ($call['created_at'] ?? ''), $config)) ?></p>

<div class="toolbar">
    <span class="badge st-<?= Http::esc((string) $call['status']) ?>"><?= Http::esc((string) $call['status']) ?></span>
    <?php if ($hasOpenAiKey) : ?>
    <button type="button" class="btn btn-reanalyze" id="reanalyze" data-id="<?= $id ?>" data-default-label="Re-run analysis" aria-busy="false">
      <span class="btn-reanalyze-spinner" aria-hidden="true"></span>
      <span class="btn-reanalyze-text">Re-run analysis</span>
    </button>
    <?php endif; ?>
    <a class="btn ghost" href="<?= Http::esc(Http::url('/?action=dashboard', $config)) ?>">Dashboard</a>
</div>

<?php if (!empty($hasAudio)) : ?>
<?php $audioUrl = Http::url('/?action=audio&id=' . $id, $config); ?>
<section class="panel audio-panel">
    <audio class="call-audio" controls preload="metadata" src="<?= Http::esc($audioUrl) ?>">
        Your browser does not support the audio element.
    </audio>
</section>
<?php endif; ?>

<?php if (!empty($call['error_message'])) : ?>
<div class="banner err"><?= Http::esc((string) $call['error_message']) ?></div>
<?php endif; ?>

<?php if ($analysis) : ?>
<section class="charts-row">
    <div class="panel chart-card">
        <h2>Quality score</h2>
        <?php if ($chartScore !== null) : ?>
        <div class="score-donut-wrap">
            <canvas id="chartScore" width="220" height="220" aria-label="Score chart"></canvas>
            <div class="score-donut-center">
                <span class="score-donut-val"><?= Http::esc((string) (int) round($chartScore)) ?></span>
                <span class="score-donut-of">/100</span>
            </div>
        </div>
        <?php else : ?>
        <div class="big-score"><?= Http::esc((string) ($analysis['overall_score'] ?? '—')) ?><span class="unit">/100</span></div>
        <?php endif; ?>
        <div class="sentiment-row">
            <span class="sentiment-label">Customer sentiment</span>
            <span class="sentiment-chip sentiment-<?= Http::esc($sentimentClass) ?>"><?= Http::esc($sentiment) ?></span>
        </div>
    </div>
    <div class="panel chart-card chart-card--wide">
        <h2>Compliance overview</h2>
        <p class="muted small chart-hint">Each row is one criterion. <span class="chart-legend-pass">Green</span> = pass, <span class="chart-legend-fail">Red</span> = not met.</p>
        <?php if ($chartCompliance !== null) : ?>
        <div class="compliance-chart-wrap" style="height: <?= (int) $complianceChartHeight ?>px;">
            <canvas id="chartCompliance" aria-label="Compliance bars"></canvas>
        </div>
        <?php else : ?>
        <p class="muted small">No compliance breakdown in this analysis.</p>
        <?php endif; ?>
    </div>
</section>

<section class="grid-two">
    <div class="panel">
        <h2>Summary</h2>
        <p class="summary-text"><?= Http::esc((string) ($analysis['summary'] ?? '')) ?></p>
        <?php if (!empty($analysis['key_topics']) && is_array($analysis['key_topics'])) : ?>
        <h3>Key topics</h3>
        <ul class="tags">
            <?php foreach ($analysis['key_topics'] as $t) : ?>
            <li><?= Http::esc((string) $t) ?></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
    <div class="panel">
        <h2>Compliance detail</h2>
        <?php
        $comp = $analysis['compliance'] ?? [];
        if (is_array($comp)) :
            foreach ($comp as $label => $item) :
                if (!is_array($item)) {
                    continue;
                }
                $pass = !empty($item['pass']);
                $note = (string) ($item['note'] ?? '');
                $human = ucwords(str_replace('_', ' ', (string) $label));
                ?>
        <div class="check-row <?= $pass ? 'pass' : 'fail' ?>">
            <span class="check-ic"><?= $pass ? '✓' : '✗' ?></span>
            <div>
                <strong><?= Http::esc($human) ?></strong>
                <?php if ($note !== '') : ?><p class="small muted"><?= Http::esc($note) ?></p><?php endif; ?>
            </div>
        </div>
            <?php
            endforeach;
        endif;
        ?>
    </div>
</section>

<section class="panel">
    <h2>Strengths</h2>
    <ul>
        <?php foreach ((array) ($analysis['strengths'] ?? []) as $s) : ?>
        <li><?= Http::esc((string) $s) ?></li>
        <?php endforeach; ?>
    </ul>
    <h2>Improvements</h2>
    <ul>
        <?php foreach ((array) ($analysis['improvements'] ?? []) as $s) : ?>
        <li><?= Http::esc((string) $s) ?></li>
        <?php endforeach; ?>
    </ul>
    <?php if (!empty($analysis['red_flags']) && is_array($analysis['red_flags']) && array_filter($analysis['red_flags'])) : ?>
    <h2>Red flags</h2>
    <ul class="red">
        <?php foreach ($analysis['red_flags'] as $s) : ?>
        <li><?= Http::esc((string) $s) ?></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</section>

<?php if ($chartScore !== null || $chartCompliance !== null) : ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
<script>
(function () {
  var Chart = window.Chart;
  if (!Chart) return;
  var grid = '#2d3a4f';
  var tick = '#8b9cb3';
  var accent = '#3d8bfd';
  var track = '#2d3a4f';

  <?php if ($chartScore !== null) : ?>
  var score = <?= json_encode($chartScore) ?>;
  var rest = Math.max(0, 100 - score);
  var ctx = document.getElementById('chartScore');
  if (ctx) {
    new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Score', 'Remaining'],
        datasets: [{
          data: [score, rest],
          backgroundColor: [accent, track],
          borderWidth: 0,
          hoverOffset: 4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        cutout: '72%',
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function (c) {
                var i = c.dataIndex;
                return i === 0 ? 'Score: ' + score.toFixed(0) : 'Remaining: ' + rest.toFixed(0);
              }
            }
          }
        }
      }
    });
  }
  <?php endif; ?>

  <?php if ($chartCompliance !== null) : ?>
  var comp = <?= json_encode($chartCompliance, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  var ctx2 = document.getElementById('chartCompliance');
  if (ctx2 && comp.labels && comp.labels.length && comp.barValues && comp.barColors) {
    var passArr = comp.passed || [];
    new Chart(ctx2, {
      type: 'bar',
      data: {
        labels: comp.labels,
        datasets: [{
          label: 'Status',
          data: comp.barValues,
          backgroundColor: comp.barColors,
          borderWidth: 0,
          borderRadius: 4,
          barThickness: 16
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          x: {
            min: 0,
            max: 100,
            display: false
          },
          y: {
            grid: { display: false },
            ticks: { color: tick, font: { size: 11 } }
          }
        },
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function (ctx) {
                var i = ctx.dataIndex;
                var ok = passArr[i] === true;
                return ok ? 'Pass' : 'Not met';
              }
            }
          }
        }
      }
    });
  }
  <?php endif; ?>
})();
</script>
<?php endif; ?>

<?php elseif ((string) $call['status'] === 'processing') : ?>
<p class="muted">Processing… refresh shortly.</p>
<?php elseif (!$hasOpenAiKey) : ?>
<p class="muted">Add <code>OPENAI_API_KEY</code> to <code>.env</code>, then re-run analysis.</p>
<?php endif; ?>

<section class="panel">
    <h2>Transcript</h2>
    <?php if (!empty($call['transcript'])) : ?>
    <pre class="transcript"><?= Http::esc((string) $call['transcript']) ?></pre>
    <?php else : ?>
    <p class="muted">No transcript yet.</p>
    <?php endif; ?>
</section>

<?php if ($hasOpenAiKey) : ?>
<div id="reanalyze-overlay" class="busy-overlay" hidden>
  <div class="busy-overlay-card" role="status" aria-live="polite">
    <div class="busy-overlay-spinner" aria-hidden="true"></div>
    <p class="busy-overlay-title">Running analysis</p>
    <p class="busy-overlay-msg">Transcribing audio and scoring the call. This can take a minute or two — please keep this tab open.</p>
  </div>
</div>
<script>
(function () {
  var btn = document.getElementById('reanalyze');
  var overlay = document.getElementById('reanalyze-overlay');
  if (!btn || !overlay) return;
  var labelEl = btn.querySelector('.btn-reanalyze-text');
  var defaultLabel = (btn.getAttribute('data-default-label') || 'Re-run analysis').trim();

  function setBusy(on) {
    if (on) {
      btn.disabled = true;
      btn.classList.add('is-loading');
      btn.setAttribute('aria-busy', 'true');
      if (labelEl) labelEl.textContent = 'Analyzing…';
      overlay.removeAttribute('hidden');
      document.body.classList.add('busy-cursor');
    } else {
      btn.disabled = false;
      btn.classList.remove('is-loading');
      btn.setAttribute('aria-busy', 'false');
      if (labelEl) labelEl.textContent = defaultLabel;
      overlay.setAttribute('hidden', '');
      document.body.classList.remove('busy-cursor');
    }
  }

  btn.addEventListener('click', function () {
    setBusy(true);
    var fd = new FormData();
    fd.append('id', btn.getAttribute('data-id'));
    fetch(<?= json_encode($analyzeUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, {
      method: 'POST',
      body: fd
    })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (j.ok) {
          location.reload();
          return;
        }
        alert(j.error || 'Analysis failed.');
        setBusy(false);
      })
      .catch(function () {
        alert('Network error — check your connection and try again.');
        setBusy(false);
      });
  });
})();
</script>
<?php endif; ?>
