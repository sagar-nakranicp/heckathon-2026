<?php /** @var array $config */ ?>
<h1>Upload recordings</h1>
<p class="lead">Select one or more audio files. Each file is transcribed (speech-to-text) and analyzed.</p>

<form class="upload-form" method="post" enctype="multipart/form-data" action="<?= Http::esc(Http::url('/?action=upload', $config)) ?>">
    <input type="hidden" name="upload" value="1">
    <label class="file-label">
        <span class="file-btn">Choose file(s)</span>
        <input type="file" name="recordings[]" accept="audio/*,.mp3,.wav,.m4a,.ogg,.webm,.flac" multiple required>
    </label>
    <button type="submit" class="btn primary">Upload &amp; analyze</button>
</form>
<p class="muted small">Multiple files allowed · Max <?= round($config['upload_max_bytes'] / 1024 / 1024, 1) ?> MB per file · MP3, WAV, M4A, OGG, WebM, FLAC</p>
