<?php /** @var array $config */ /** @var bool $hasOpenAiKey */ ?>
<h1>How it works</h1>
<p class="lead">End-to-end flow for call quality analysis: from upload to dashboard and review.</p>

<section class="panel workflow-visual" aria-label="Process overview">
    <div class="workflow-strip">
        <div class="workflow-node"><span class="workflow-num">1</span> Upload</div>
        <span class="workflow-arrow" aria-hidden="true">→</span>
        <div class="workflow-node"><span class="workflow-num">2</span> Store</div>
        <span class="workflow-arrow" aria-hidden="true">→</span>
        <div class="workflow-node"><span class="workflow-num">3</span> Transcribe</div>
        <span class="workflow-arrow" aria-hidden="true">→</span>
        <div class="workflow-node"><span class="workflow-num">4</span> Analyze</div>
        <span class="workflow-arrow" aria-hidden="true">→</span>
        <div class="workflow-node"><span class="workflow-num">5</span> Review</div>
    </div>
</section>

<section class="panel">
    <h2>Step-by-step</h2>
    <ol class="workflow-steps">
        <li><strong>Upload</strong> — On <a href="<?= Http::esc(Http::url('/?action=upload', $config)) ?>">Upload</a>, choose one or more audio files (MP3, WAV, M4A, etc.).</li>
        <li><strong>Save</strong> — Each file is stored under <code>storage/uploads/</code> with a safe random name. A row is created in MySQL (<code>calls</code>) with status, size, and original filename.</li>
        <li><strong>Transcribe (speech → text)</strong> — If <code>OPENAI_API_KEY</code> is set in <code>.env</code>, OpenAI <strong>Whisper</strong> converts the audio to a text transcript.</li>
        <li><strong>Analyze</strong> — The same transcript is sent to an OpenAI chat model, which returns structured JSON: overall score, customer sentiment, summary, compliance checklist, strengths, improvements, and red flags.</li>
        <li><strong>Persist</strong> — Transcript and analysis JSON are saved on the call record; status becomes <code>done</code> (or <code>error</code> if something failed).</li>
        <li><strong>Dashboard</strong> — The <a href="<?= Http::esc(Http::url('/?action=dashboard', $config)) ?>">Dashboard</a> lists recent calls, status, score, sentiment, and upload time (shown in your configured timezone, e.g. IST).</li>
        <li><strong>View</strong> — Open a call to play the original recording, see charts (quality score, compliance radar), read the summary and checklist, and scroll to the full transcript.</li>
        <li><strong>Re-run</strong> — On the view page you can re-run analysis (same audio + new AI result) if you change models or fix API issues.</li>
    </ol>
</section>

<?php if (!$hasOpenAiKey) : ?>
<div class="banner warn">
    <strong>No OpenAI key.</strong> Add <code>OPENAI_API_KEY</code> to <code>.env</code> for transcription and analysis; otherwise uploads are stored but not processed.
</div>
<?php endif; ?>
