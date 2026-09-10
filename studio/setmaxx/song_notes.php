<?php
require_once __DIR__ . '/_common.php';

$songId = max(0, (int)($_GET['id'] ?? 0));
$song = null;
$message = '';
$error = '';

if ($tablesReady && $songId > 0) {
    $stmt = $pdo->prepare(
        "SELECT id, title, artist, song_key, tempo_bpm, performance_notes, updated_at
         FROM setmaxx_songs
         WHERE id = ? AND user_id = ?
         LIMIT 1"
    );
    $stmt->execute([$songId, $userId]);
    $song = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

if ($song && is_post()) {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $error = 'Your session expired. Refresh and try again.';
    } else {
        $notes = mb_substr(trim((string)($_POST['performance_notes'] ?? '')), 0, 8000);
        $update = $pdo->prepare("UPDATE setmaxx_songs SET performance_notes = ? WHERE id = ? AND user_id = ?");
        $update->execute([$notes !== '' ? $notes : null, $songId, $userId]);
        $message = 'Notes saved.';

        $stmt->execute([$songId, $userId]);
        $song = $stmt->fetch(PDO::FETCH_ASSOC) ?: $song;
    }
}

header('Cache-Control: private, max-age=604800');
header('X-Robots-Tag: noindex, nofollow');

$title = $song ? (string)$song['title'] : 'Song notes';
$artist = $song ? (string)($song['artist'] ?: 'Artist not set') : '';
$notes = $song ? trim((string)($song['performance_notes'] ?? '')) : '';
$updatedAt = $song && !empty($song['updated_at']) ? date('M j, Y g:i A', strtotime((string)$song['updated_at'])) : '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> | Performance Notes</title>
  <style>
    :root {
      color-scheme:dark;
      --notes-scale:1;
      --notes-view-size:clamp(1.45rem, 4.6vw, 3.65rem);
      --notes-edit-size:clamp(1.3rem, 3.7vw, 2.9rem);
    }
    * { box-sizing:border-box; }
    body {
      margin:0;
      min-height:100vh;
      background:#050505;
      color:#fff;
      font-family:Arial, Helvetica, sans-serif;
    }
    .stage-notes {
      width:min(1100px, 100%);
      margin:0 auto;
      padding:clamp(1rem, 3vw, 2.5rem);
    }
    .stage-top {
      display:flex;
      justify-content:space-between;
      align-items:flex-start;
      gap:1rem;
      margin-bottom:clamp(.7rem, 1.8vw, 1.25rem);
    }
    .stage-link {
      display:inline-flex;
      min-height:40px;
      align-items:center;
      justify-content:center;
      padding:.6rem .85rem;
      border:1px solid #333;
      border-radius:10px;
      color:#fff;
      background:#111;
      text-decoration:none;
      font-size:.95rem;
      font-weight:700;
      cursor:pointer;
    }
    button.stage-link { font:inherit; }
    .stage-actions { display:flex; gap:.45rem; flex-wrap:wrap; justify-content:flex-end; }
    .font-button {
      min-width:46px;
      font-size:1.05rem;
    }
    h1 {
      margin:.1rem 0 .25rem;
      font-size:clamp(1.45rem, 4.2vw, 3rem);
      line-height:1;
      letter-spacing:0;
    }
    .artist {
      margin:0;
      color:#cfcfcf;
      font-size:clamp(.95rem, 2.2vw, 1.35rem);
      line-height:1.2;
    }
    .meta {
      display:flex;
      gap:.45rem;
      flex-wrap:wrap;
      margin-top:.35rem;
      color:#e8d99a;
      font-size:clamp(.85rem, 1.7vw, 1.05rem);
      font-weight:700;
    }
    .notes {
      width:100%;
      min-height:52vh;
      margin-top:.6rem;
      padding-top:.8rem;
      border-top:2px solid #333;
      white-space:pre-wrap;
      font-size:calc(var(--notes-view-size) * var(--notes-scale));
      line-height:1.12;
      font-weight:700;
    }
    .empty {
      color:#9f9f9f;
      font-size:clamp(1.6rem, 4vw, 3rem);
      font-weight:700;
    }
    .saved {
      margin-top:1.5rem;
      color:#858585;
      font-size:1rem;
    }
    .notice {
      margin:0 0 1rem;
      padding:.75rem 1rem;
      border:1px solid #2f5f3f;
      background:#0b2114;
      color:#c7ffd9;
      border-radius:10px;
      font-size:1rem;
      font-weight:700;
    }
    .notice.error {
      border-color:#743232;
      background:#2a1010;
      color:#ffd0d0;
    }
    .edit-form {
      display:none;
      margin-top:1rem;
    }
    .edit-form.is-open { display:block; }
    .notes-editor {
      width:100%;
      min-height:52vh;
      padding:1rem;
      border:2px solid #444;
      border-radius:12px;
      background:#080808;
      color:#fff;
      font:700 calc(var(--notes-edit-size) * var(--notes-scale))/1.15 Arial, Helvetica, sans-serif;
      resize:vertical;
    }
    .edit-actions {
      display:flex;
      gap:.75rem;
      flex-wrap:wrap;
      margin-top:1rem;
    }
    .hide-while-editing.is-editing { display:none; }
    @media (orientation:landscape) {
      :root {
        --notes-view-size:clamp(1.35rem, 3.9vw, 3.05rem);
        --notes-edit-size:clamp(1.25rem, 3.1vw, 2.55rem);
      }
    }
    @media (max-width: 640px) {
      .stage-top { gap:.7rem; }
      .stage-actions { max-width:150px; }
      .stage-link { min-height:38px; padding:.55rem .7rem; font-size:.9rem; }
      .font-button { min-width:42px; }
    }
  </style>
</head>
<body>
  <main class="stage-notes">
    <?php if ($message !== ''): ?><div class="notice"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
    <div class="stage-top">
      <div>
        <div class="meta">
          <span>Performance Notes</span>
          <?php if ($song && trim((string)($song['song_key'] ?? '')) !== ''): ?><span>Key <?= e((string)$song['song_key']) ?></span><?php endif; ?>
          <?php if ($song && (int)($song['tempo_bpm'] ?? 0) > 0): ?><span><?= (int)$song['tempo_bpm'] ?> BPM</span><?php endif; ?>
        </div>
        <h1><?= e($title) ?></h1>
        <?php if ($artist !== ''): ?><p class="artist"><?= e($artist) ?></p><?php endif; ?>
      </div>
      <div class="stage-actions">
        <?php if ($song): ?>
          <button class="stage-link font-button" type="button" id="decreaseFontBtn" aria-label="Decrease note font size">A-</button>
          <button class="stage-link font-button" type="button" id="increaseFontBtn" aria-label="Increase note font size">A+</button>
        <?php endif; ?>
        <?php if ($song): ?><button class="stage-link" type="button" id="editNotesBtn">Edit</button><?php endif; ?>
        <a class="stage-link" href="<?= e(base_url('/setmaxx/songs.php')) ?>">Songs</a>
      </div>
    </div>

    <div class="hide-while-editing" id="notesDisplay">
      <?php if (!$song): ?>
        <div class="notes empty">That song could not be found.</div>
      <?php elseif ($notes === ''): ?>
        <div class="notes empty">No performance notes saved yet.</div>
      <?php else: ?>
        <div class="notes"><?= e($notes) ?></div>
      <?php endif; ?>
    </div>

    <?php if ($song): ?>
      <form class="edit-form" id="notesEditForm" method="post" action="<?= e(base_url('/setmaxx/song_notes.php?id=' . $songId)) ?>">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <textarea class="notes-editor" name="performance_notes" aria-label="Performance notes"><?= e($notes) ?></textarea>
        <div class="edit-actions">
          <button class="stage-link" type="submit">Save</button>
          <button class="stage-link" type="button" id="cancelEditBtn">Cancel</button>
        </div>
      </form>
    <?php endif; ?>

    <?php if ($updatedAt !== ''): ?><div class="saved">Saved <?= e($updatedAt) ?></div><?php endif; ?>
  </main>
  <script>
    (function() {
      const notesWereSaved = <?= $message !== '' ? 'true' : 'false' ?>;
      const notesSwUrl = <?= json_encode(base_url('/sw.js')) ?>;
      const editButton = document.getElementById('editNotesBtn');
      const cancelButton = document.getElementById('cancelEditBtn');
      const decreaseFontButton = document.getElementById('decreaseFontBtn');
      const increaseFontButton = document.getElementById('increaseFontBtn');
      const display = document.getElementById('notesDisplay');
      const form = document.getElementById('notesEditForm');
      const fontStorageKey = 'setmaxxPerformanceNotesScale';
      const minScale = 0.72;
      const maxScale = 1.65;
      const scaleStep = 0.08;

      function storageValue(value) {
        try {
          if (!window.localStorage) return null;
          if (typeof value === 'undefined') return window.localStorage.getItem(fontStorageKey);
          window.localStorage.setItem(fontStorageKey, value);
        } catch (error) {
          return null;
        }
        return null;
      }

      function savedScale() {
        const value = parseFloat(storageValue() || '');
        return Number.isFinite(value) ? Math.min(maxScale, Math.max(minScale, value)) : 1;
      }

      function applyFontScale(scale) {
        const nextScale = Math.min(maxScale, Math.max(minScale, scale));
        document.documentElement.style.setProperty('--notes-scale', String(nextScale));
        storageValue(String(nextScale));
        if (decreaseFontButton) decreaseFontButton.disabled = nextScale <= minScale;
        if (increaseFontButton) increaseFontButton.disabled = nextScale >= maxScale;
      }

      applyFontScale(savedScale());

      if (notesWereSaved && 'serviceWorker' in navigator && window.MessageChannel) {
        window.addEventListener('load', function() {
          navigator.serviceWorker.register(notesSwUrl).then(function(registration) {
            const worker = registration.active || registration.waiting || registration.installing;
            if (!worker) return;
            const channel = new MessageChannel();
            worker.postMessage({
              type: 'SETMAXX_CACHE_PERFORMANCE_NOTES',
              urls: [window.location.href]
            }, [channel.port2]);
          }).catch(function() {});
        });
      }

      if (!editButton || !form || !display) return;

      if (decreaseFontButton) {
        decreaseFontButton.addEventListener('click', function() {
          applyFontScale(savedScale() - scaleStep);
        });
      }

      if (increaseFontButton) {
        increaseFontButton.addEventListener('click', function() {
          applyFontScale(savedScale() + scaleStep);
        });
      }

      function setEditing(editing) {
        form.classList.toggle('is-open', editing);
        display.classList.toggle('is-editing', editing);
        editButton.hidden = editing;
        if (editing) {
          const editor = form.querySelector('textarea');
          if (editor) editor.focus();
        }
      }

      editButton.addEventListener('click', function() { setEditing(true); });
      if (cancelButton) cancelButton.addEventListener('click', function() { setEditing(false); });
    })();
  </script>
</body>
</html>
