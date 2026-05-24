<?php
require_once __DIR__ . '/_common.php';

function setmaxx_song_column_exists(PDO $pdo, string $columnName): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'setmaxx_songs' AND column_name = ? LIMIT 1");
    $stmt->execute([$columnName]);
    return (bool)$stmt->fetchColumn();
}

function setmaxx_ensure_song_metadata_schema(PDO $pdo): void {
    if (!setmaxx_table_exists($pdo, 'setmaxx_songs')) return;

    $columns = [
        'release_year' => "ADD COLUMN `release_year` smallint(5) unsigned DEFAULT NULL AFTER `artist`",
        'genre' => "ADD COLUMN `genre` varchar(120) DEFAULT NULL AFTER `release_year`",
        'is_prerecorded' => "ADD COLUMN `is_prerecorded` tinyint(1) NOT NULL DEFAULT 0 AFTER `genre`",
        'track_length_seconds' => "ADD COLUMN `track_length_seconds` smallint(5) unsigned DEFAULT NULL AFTER `is_prerecorded`",
        'is_medley' => "ADD COLUMN `is_medley` tinyint(1) NOT NULL DEFAULT 0 AFTER `track_length_seconds`",
        'medley_name' => "ADD COLUMN `medley_name` varchar(190) DEFAULT NULL AFTER `is_medley`",
        'opening_song' => "ADD COLUMN `opening_song` tinyint(1) NOT NULL DEFAULT 0 AFTER `medley_name`",
        'vocal_difficulty' => "ADD COLUMN `vocal_difficulty` enum('easy','medium','hard') DEFAULT NULL AFTER `opening_song`",
        'song_key' => "ADD COLUMN `song_key` varchar(24) DEFAULT NULL AFTER `vocal_difficulty`",
        'tempo_bpm' => "ADD COLUMN `tempo_bpm` smallint(5) unsigned DEFAULT NULL AFTER `song_key`",
        'family_friendly' => "ADD COLUMN `family_friendly` tinyint(1) NOT NULL DEFAULT 1 AFTER `tempo_bpm`",
        'performance_notes' => "ADD COLUMN `performance_notes` text DEFAULT NULL AFTER `family_friendly`",
    ];

    foreach ($columns as $column => $sql) {
        if (!setmaxx_song_column_exists($pdo, $column)) {
            $pdo->exec("ALTER TABLE `setmaxx_songs` {$sql}");
        }
    }
}

function setmaxx_clean_text($value, int $maxLength = 190): ?string {
    $value = trim((string)$value);
    if ($value === '') return null;
    return mb_substr($value, 0, $maxLength);
}

function setmaxx_clean_int($value, int $min, int $max): ?int {
    $value = trim((string)$value);
    if ($value === '') return null;
    $int = (int)$value;
    if ($int < $min || $int > $max) return null;
    return $int;
}

function setmaxx_seconds_to_length(?int $seconds): string {
    if (!$seconds || $seconds < 1) return '';
    return floor($seconds / 60) . ':' . str_pad((string)($seconds % 60), 2, '0', STR_PAD_LEFT);
}

function setmaxx_parse_length_seconds($value): ?int {
    $value = trim((string)$value);
    if ($value === '') return null;
    if (str_contains($value, ':')) {
        $parts = array_map('intval', explode(':', $value));
        if (count($parts) === 2) return max(0, ($parts[0] * 60) + $parts[1]);
        if (count($parts) === 3) return max(0, ($parts[0] * 3600) + ($parts[1] * 60) + $parts[2]);
    }
    return setmaxx_clean_int($value, 1, 5999);
}

function setmaxx_parse_song_import(string $text): array {
    $rows = [];
    $headerMap = null;
    $lines = preg_split('/\R/u', $text) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;

        $title = $line;
        $artist = null;
        $genre = null;
        $year = null;

        if (str_contains($line, "\t")) {
            $parts = array_map('trim', explode("\t", $line));
        } elseif (str_contains($line, ',')) {
            $parts = array_map('trim', str_getcsv($line));
        } elseif (preg_match('/\s+-\s+/', $line)) {
            $parts = array_map('trim', preg_split('/\s+-\s+/', $line, 2));
        } else {
            $parts = [$line];
        }

        $lowerParts = array_map(fn($part) => strtolower((string)$part), $parts);
        if (in_array('title', $lowerParts, true) || in_array('song title', $lowerParts, true)) {
            $headerMap = [];
            foreach ($lowerParts as $index => $heading) {
                $heading = trim($heading);
                if (in_array($heading, ['title', 'song title', 'song'], true)) $headerMap['title'] = $index;
                if (in_array($heading, ['artist', 'artist name', 'performer'], true)) $headerMap['artist'] = $index;
                if (in_array($heading, ['year', 'release year'], true)) $headerMap['release_year'] = $index;
                if ($heading === 'genre') $headerMap['genre'] = $index;
            }
            continue;
        }

        if (is_array($headerMap) && isset($headerMap['title'])) {
            $title = $parts[$headerMap['title']] ?? '';
            $artist = isset($headerMap['artist']) ? ($parts[$headerMap['artist']] ?? null) : null;
            $year = isset($headerMap['release_year']) ? setmaxx_clean_int($parts[$headerMap['release_year']] ?? '', 1800, (int)date('Y') + 1) : null;
            $genre = isset($headerMap['genre']) ? setmaxx_clean_text($parts[$headerMap['genre']] ?? '', 120) : null;
        } elseif (count($parts) >= 2) {
            $title = $parts[0];
            $artist = $parts[1] !== '' ? $parts[1] : null;
            if (count($parts) >= 3) $year = setmaxx_clean_int($parts[2], 1800, (int)date('Y') + 1);
            if (count($parts) >= 4) $genre = setmaxx_clean_text($parts[3], 120);
        }

        $title = setmaxx_clean_text($title);
        if ($title !== null) {
            $rows[] = [
                'title' => $title,
                'artist' => setmaxx_clean_text($artist),
                'release_year' => $year,
                'genre' => $genre,
            ];
        }
    }
    return $rows;
}

if ($tablesReady) {
    try {
        setmaxx_ensure_song_metadata_schema($pdo);
    } catch (Throwable $e) {
        $errors[] = 'Song metadata fields could not be prepared. Run the latest Set Maxx migration if this continues.';
    }
}

if ($tablesReady && is_post()) {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    } elseif (!$isProUser) {
        $errors[] = 'Set Maxx is included with the paid tools plan. Upgrade to continue.';
    } else {
        $action = (string)($_POST['action'] ?? 'add_song');
        try {
            if ($action === 'import_songs') {
                $importText = (string)($_POST['import_text'] ?? '');
                if (!empty($_FILES['song_file']['tmp_name']) && is_uploaded_file($_FILES['song_file']['tmp_name'])) {
                    $importText .= "\n" . (string)file_get_contents($_FILES['song_file']['tmp_name']);
                }

                $importRows = setmaxx_parse_song_import($importText);
                if (!$importRows) throw new RuntimeException('Add at least one song title to import.');

                $existingStmt = $pdo->prepare("SELECT title, COALESCE(artist, '') AS artist FROM setmaxx_songs WHERE user_id = ?");
                $existingStmt->execute([$userId]);
                $existing = [];
                foreach ($existingStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $existing[strtolower(trim($row['title']) . '|' . trim((string)$row['artist']))] = true;
                }

                $insert = $pdo->prepare(
                    "INSERT INTO setmaxx_songs (user_id, title, artist, release_year, genre, tip_amount_cents)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $added = 0;
                $skipped = 0;
                foreach ($importRows as $row) {
                    $key = strtolower($row['title'] . '|' . (string)($row['artist'] ?? ''));
                    if (isset($existing[$key])) {
                        $skipped++;
                        continue;
                    }
                    $insert->execute([$userId, $row['title'], $row['artist'], $row['release_year'], $row['genre'], 1000]);
                    $existing[$key] = true;
                    $added++;
                }

                $messages[] = $added . ' song' . ($added === 1 ? '' : 's') . ' imported.';
                if ($skipped > 0) $messages[] = $skipped . ' duplicate ' . ($skipped === 1 ? 'entry was' : 'entries were') . ' skipped.';
            } elseif ($action === 'save_catalog') {
                $rows = $_POST['songs'] ?? [];
                if (!is_array($rows)) throw new RuntimeException('No song updates were submitted.');

                $update = $pdo->prepare(
                    "UPDATE setmaxx_songs
                     SET title = ?, artist = ?, release_year = ?, genre = ?, is_prerecorded = ?, track_length_seconds = ?,
                         is_medley = ?, medley_name = ?, opening_song = ?, vocal_difficulty = ?, song_key = ?,
                         tempo_bpm = ?, family_friendly = ?, performance_notes = ?, tip_amount_cents = ?, is_active = ?
                     WHERE id = ? AND user_id = ?"
                );
                $saved = 0;
                foreach ($rows as $songId => $row) {
                    if (!is_array($row)) continue;
                    $songId = (int)$songId;
                    $title = setmaxx_clean_text($row['title'] ?? '');
                    if ($songId <= 0 || $title === null) continue;

                    $difficulty = setmaxx_clean_text($row['vocal_difficulty'] ?? '', 12);
                    if (!in_array($difficulty, ['easy', 'medium', 'hard'], true)) $difficulty = null;
                    $tipDollars = (float)($row['tip_dollars'] ?? 10);

                    $update->execute([
                        $title,
                        setmaxx_clean_text($row['artist'] ?? ''),
                        setmaxx_clean_int($row['release_year'] ?? '', 1800, (int)date('Y') + 1),
                        setmaxx_clean_text($row['genre'] ?? '', 120),
                        !empty($row['is_prerecorded']) ? 1 : 0,
                        setmaxx_parse_length_seconds($row['track_length'] ?? ''),
                        !empty($row['is_medley']) ? 1 : 0,
                        setmaxx_clean_text($row['medley_name'] ?? ''),
                        !empty($row['opening_song']) ? 1 : 0,
                        $difficulty,
                        setmaxx_clean_text($row['song_key'] ?? '', 24),
                        setmaxx_clean_int($row['tempo_bpm'] ?? '', 1, 400),
                        !empty($row['family_friendly']) ? 1 : 0,
                        setmaxx_clean_text($row['performance_notes'] ?? '', 2000),
                        max(0, (int)round($tipDollars * 100)),
                        !empty($row['is_active']) ? 1 : 0,
                        $songId,
                        $userId,
                    ]);
                    $saved++;
                }
                $messages[] = $saved . ' catalog ' . ($saved === 1 ? 'row' : 'rows') . ' saved.';
            } elseif ($action === 'delete_selected') {
                $selectedIds = $_POST['selected_song_ids'] ?? [];
                if (!is_array($selectedIds)) throw new RuntimeException('No songs were selected.');

                $selectedIds = array_values(array_unique(array_filter(array_map('intval', $selectedIds), fn($id) => $id > 0)));
                if (!$selectedIds) throw new RuntimeException('No songs were selected.');

                $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
                $delete = $pdo->prepare("DELETE FROM setmaxx_songs WHERE user_id = ? AND id IN ({$placeholders})");
                $delete->execute(array_merge([$userId], $selectedIds));
                $deleted = $delete->rowCount();
                $messages[] = $deleted . ' selected ' . ($deleted === 1 ? 'song was' : 'songs were') . ' deleted.';
            } else {
                $title = setmaxx_clean_text($_POST['title'] ?? '');
                $artist = setmaxx_clean_text($_POST['artist'] ?? '');
                $tipDollars = (float)($_POST['tip_dollars'] ?? 10);
                if ($title === null) throw new RuntimeException('Song title is required.');
                $stmt = $pdo->prepare("INSERT INTO setmaxx_songs (user_id, title, artist, tip_amount_cents) VALUES (?, ?, ?, ?)");
                $stmt->execute([$userId, $title, $artist, max(0, (int)round($tipDollars * 100))]);
                $messages[] = 'Song added to your Set Maxx catalog.';
            }
        } catch (Throwable $e) { $errors[] = $e->getMessage(); }
    }
}

$songs = [];
$availableLetters = [];
if ($tablesReady) {
    $songsStmt = $pdo->prepare(
        "SELECT id, title, artist, release_year, genre, is_prerecorded, track_length_seconds, is_medley,
                medley_name, opening_song, vocal_difficulty, song_key, tempo_bpm, family_friendly,
                performance_notes, tip_amount_cents, is_active, created_at
         FROM setmaxx_songs
         WHERE user_id = ?
         ORDER BY is_active DESC, title ASC, artist ASC"
    );
    $songsStmt->execute([$userId]);
    $songs = $songsStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($songs as $song) {
        $first = strtoupper(substr(trim((string)$song['title']), 0, 1));
        $letter = preg_match('/[A-Z]/', $first) ? $first : '#';
        $availableLetters[$letter] = true;
    }
    ksort($availableLetters);
}
setmaxx_page_head('Set Maxx | Song Catalog');
?>
<main class="container setmaxx-shell">
  <?php setmaxx_flash($messages, $errors); ?>
  <div class="setmaxx-card" style="margin-bottom:1rem;">
    <div class="setmaxx-pill">Song Catalog</div>
    <h1 style="margin:.8rem 0 .35rem;">Songs fans can request</h1>
    <p class="setmaxx-help">Import a song list, enrich likely metadata, then tune the performance details that matter on stage.</p>
  </div>
  <?php if (!$tablesReady): ?><?php setmaxx_install_notice(); ?><?php else: ?>
  <section class="setmaxx-grid">
    <div class="setmaxx-card">
      <h2 style="margin-top:0;">Import songs</h2>
      <form method="post" enctype="multipart/form-data" class="setmaxx-stack" action="">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="import_songs">
        <div class="setmaxx-field">
          <label for="import_text">Paste titles or title/artist rows</label>
          <textarea class="setmaxx-textarea" id="import_text" name="import_text" placeholder="Sweet Caroline - Neil Diamond&#10;September, Earth Wind &amp; Fire&#10;Mr. Brightside"></textarea>
          <div class="setmaxx-help">CSV, tab-separated, and "Title - Artist" rows are supported. Optional columns: year, genre.</div>
        </div>
        <div class="setmaxx-field">
          <label for="song_file">Upload text or CSV</label>
          <input class="setmaxx-input" id="song_file" name="song_file" type="file" accept=".txt,.csv,text/plain,text/csv">
        </div>
        <div class="setmaxx-actions"><button class="btn btn-primary" type="submit" <?= $isProUser ? '' : 'disabled' ?>>Import songs</button></div>
      </form>
    </div>
    <div class="setmaxx-card">
      <h2 style="margin-top:0;">Add one song</h2>
      <form method="post" class="setmaxx-stack" action="">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="add_song">
        <div class="setmaxx-form-grid">
          <div class="setmaxx-field"><label for="title">Song title</label><input class="setmaxx-input" id="title" name="title" required></div>
          <div class="setmaxx-field"><label for="artist">Artist</label><input class="setmaxx-input" id="artist" name="artist"></div>
          <div class="setmaxx-field"><label for="tip_dollars">Suggested tip</label><input class="setmaxx-input" id="tip_dollars" name="tip_dollars" type="number" min="0" step="1" value="10"></div>
        </div>
        <div class="setmaxx-actions">
          <button class="btn btn-primary" type="submit" <?= $isProUser ? '' : 'disabled' ?>>Add song</button>
          <a class="btn btn-outline" href="<?= e(base_url('/setmaxx/sessions.php')) ?>">Gig Sessions</a>
        </div>
      </form>
    </div>
  </section>

  <form method="post" class="setmaxx-card setmaxx-catalog-editor" style="margin-top:1rem;" action="">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="save_catalog">
    <div class="setmaxx-catalog-toolbar">
      <div>
        <h2 style="margin:0;">Editable catalog</h2>
        <div class="setmaxx-help">Fill the request-facing basics and the private performance notes in one pass.</div>
      </div>
      <div class="setmaxx-actions">
        <button class="btn btn-outline" type="button" id="setmaxxEnrichBtn" <?= $songs ? '' : 'disabled' ?>>Enrich selected</button>
        <button class="btn btn-outline" type="submit" name="action" value="delete_selected" id="setmaxxDeleteSelectedBtn" <?= $isProUser && $songs ? '' : 'disabled' ?>>Delete selected</button>
        <button class="btn btn-primary" type="submit" <?= $isProUser && $songs ? '' : 'disabled' ?>>Save catalog</button>
      </div>
    </div>

    <?php if (!$songs): ?>
      <div class="setmaxx-row" style="margin-top:1rem;"><div class="setmaxx-meta">No songs yet. Import a list or add a few staples first.</div></div>
    <?php else: ?>
      <div class="setmaxx-alpha-menu" aria-label="Song alphabet filter">
        <button class="setmaxx-alpha-button active" type="button" data-letter="all">All</button>
        <?php foreach (array_merge(['#'], range('A', 'Z')) as $letter): ?>
          <button class="setmaxx-alpha-button" type="button" data-letter="<?= e($letter) ?>" <?= isset($availableLetters[$letter]) ? '' : 'disabled' ?>><?= e($letter) ?></button>
        <?php endforeach; ?>
      </div>
      <div class="setmaxx-table-wrap">
        <table class="setmaxx-song-table" id="setmaxxSongTable">
          <thead>
            <tr>
              <th><input type="checkbox" id="setmaxxSelectAll" aria-label="Select all songs"></th>
              <th>Active</th>
              <th>Title</th>
              <th>Artist</th>
              <th>Year</th>
              <th>Genre</th>
              <th>Track</th>
              <th>Length</th>
              <th>Medley</th>
              <th>Medley name</th>
              <th>Opener</th>
              <th>Vocal</th>
              <th>Key</th>
              <th>Tempo</th>
              <th>Family</th>
              <th>Notes</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($songs as $song): ?>
              <?php
                $id = (int)$song['id'];
                $first = strtoupper(substr(trim((string)$song['title']), 0, 1));
                $letter = preg_match('/[A-Z]/', $first) ? $first : '#';
              ?>
              <tr class="setmaxx-song-row" data-letter="<?= e($letter) ?>">
                <td>
                  <input class="js-row-select" type="checkbox" name="selected_song_ids[]" value="<?= $id ?>" aria-label="Select <?= e($song['title']) ?>">
                  <input type="hidden" name="songs[<?= $id ?>][tip_dollars]" value="<?= e((string)(((int)$song['tip_amount_cents']) / 100)) ?>">
                </td>
                <td><input type="hidden" name="songs[<?= $id ?>][is_active]" value="0"><input type="checkbox" name="songs[<?= $id ?>][is_active]" value="1" <?= !empty($song['is_active']) ? 'checked' : '' ?>></td>
                <td><input class="setmaxx-grid-input js-title" name="songs[<?= $id ?>][title]" value="<?= e($song['title']) ?>" required></td>
                <td><input class="setmaxx-grid-input js-artist" name="songs[<?= $id ?>][artist]" value="<?= e((string)$song['artist']) ?>"></td>
                <td><input class="setmaxx-grid-input js-year" name="songs[<?= $id ?>][release_year]" type="number" min="1800" max="<?= (int)date('Y') + 1 ?>" value="<?= e((string)$song['release_year']) ?>"></td>
                <td><input class="setmaxx-grid-input js-genre" name="songs[<?= $id ?>][genre]" value="<?= e((string)$song['genre']) ?>"></td>
                <td><input type="hidden" name="songs[<?= $id ?>][is_prerecorded]" value="0"><input class="js-prerecorded" type="checkbox" name="songs[<?= $id ?>][is_prerecorded]" value="1" <?= !empty($song['is_prerecorded']) ? 'checked' : '' ?>></td>
                <td><input class="setmaxx-grid-input js-length" name="songs[<?= $id ?>][track_length]" placeholder="3:45" value="<?= e(setmaxx_seconds_to_length((int)($song['track_length_seconds'] ?? 0))) ?>"></td>
                <td><input type="hidden" name="songs[<?= $id ?>][is_medley]" value="0"><input type="checkbox" name="songs[<?= $id ?>][is_medley]" value="1" <?= !empty($song['is_medley']) ? 'checked' : '' ?>></td>
                <td><input class="setmaxx-grid-input" name="songs[<?= $id ?>][medley_name]" value="<?= e((string)$song['medley_name']) ?>"></td>
                <td><input type="hidden" name="songs[<?= $id ?>][opening_song]" value="0"><input type="checkbox" name="songs[<?= $id ?>][opening_song]" value="1" <?= !empty($song['opening_song']) ? 'checked' : '' ?>></td>
                <td>
                  <select class="setmaxx-grid-input" name="songs[<?= $id ?>][vocal_difficulty]">
                    <option value=""></option>
                    <option value="easy" <?= $song['vocal_difficulty'] === 'easy' ? 'selected' : '' ?>>Easy</option>
                    <option value="medium" <?= $song['vocal_difficulty'] === 'medium' ? 'selected' : '' ?>>Med</option>
                    <option value="hard" <?= $song['vocal_difficulty'] === 'hard' ? 'selected' : '' ?>>Hard</option>
                  </select>
                </td>
                <td><input class="setmaxx-grid-input" name="songs[<?= $id ?>][song_key]" value="<?= e((string)$song['song_key']) ?>"></td>
                <td><input class="setmaxx-grid-input" name="songs[<?= $id ?>][tempo_bpm]" type="number" min="1" max="400" value="<?= e((string)$song['tempo_bpm']) ?>"></td>
                <td><input type="hidden" name="songs[<?= $id ?>][family_friendly]" value="0"><input type="checkbox" name="songs[<?= $id ?>][family_friendly]" value="1" <?= !empty($song['family_friendly']) ? 'checked' : '' ?>></td>
                <td><textarea class="setmaxx-grid-notes" name="songs[<?= $id ?>][performance_notes]"><?= e((string)$song['performance_notes']) ?></textarea></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </form>
  <?php endif; ?>
</main>
<style>
  .setmaxx-catalog-toolbar { display:flex; justify-content:space-between; gap:1rem; align-items:flex-start; flex-wrap:wrap; margin-bottom:1rem; }
  .setmaxx-alpha-menu { display:flex; gap:.35rem; flex-wrap:wrap; align-items:center; margin:.25rem 0 1rem; padding:.65rem; border-radius:16px; background:rgba(9,8,20,.7); border:1px solid rgba(255,255,255,.08); }
  .setmaxx-alpha-button { min-width:34px; height:34px; border-radius:10px; border:1px solid rgba(255,255,255,.12); background:rgba(255,255,255,.05); color:#fff; font:inherit; font-size:.82rem; cursor:pointer; }
  .setmaxx-alpha-button.active,
  .setmaxx-alpha-button:hover { background:rgba(140,107,255,.24); border-color:rgba(140,107,255,.45); }
  .setmaxx-alpha-button:disabled { opacity:.35; cursor:not-allowed; }
  .setmaxx-table-wrap { overflow:auto; border:1px solid rgba(255,255,255,.08); border-radius:16px; }
  .setmaxx-song-table { width:100%; min-width:1600px; border-collapse:collapse; }
  .setmaxx-song-table th,
  .setmaxx-song-table td { padding:.55rem; border-bottom:1px solid rgba(255,255,255,.07); vertical-align:top; }
  .setmaxx-song-table th { position:sticky; top:0; z-index:1; background:#151323; color:rgba(255,255,255,.78); font-size:.78rem; text-align:left; font-weight:600; }
  .setmaxx-song-table tbody tr:nth-child(even) { background:rgba(255,255,255,.025); }
  .setmaxx-grid-input,
  .setmaxx-grid-notes { width:100%; min-width:92px; padding:.55rem .6rem; border-radius:10px; border:1px solid rgba(255,255,255,.1); background:rgba(255,255,255,.05); color:#fff; font:inherit; font-size:.88rem; }
  .setmaxx-grid-input option { background:#151323; color:#fff; }
  .setmaxx-grid-notes { min-width:180px; height:42px; resize:vertical; }
  .setmaxx-song-table input[type="checkbox"] { width:18px; height:18px; accent-color:#8c6bff; }
</style>
<script>
(function() {
  const button = document.getElementById('setmaxxEnrichBtn');
  const deleteButton = document.getElementById('setmaxxDeleteSelectedBtn');
  const selectAll = document.getElementById('setmaxxSelectAll');
  const table = document.getElementById('setmaxxSongTable');
  const alphaButtons = Array.from(document.querySelectorAll('.setmaxx-alpha-button'));
  if (!button || !table) return;

  function isBlank(input) {
    return input && input.value.trim() === '';
  }

  function msToLength(ms) {
    const seconds = Math.round(ms / 1000);
    const minutes = Math.floor(seconds / 60);
    return minutes + ':' + String(seconds % 60).padStart(2, '0');
  }

  async function findTrack(title, artist) {
    const term = [title, artist].filter(Boolean).join(' ');
    const url = 'https://itunes.apple.com/search?entity=song&limit=1&term=' + encodeURIComponent(term);
    const response = await fetch(url);
    if (!response.ok) return null;
    const data = await response.json();
    return data && data.results && data.results.length ? data.results[0] : null;
  }

  function selectedRows() {
    return Array.from(table.querySelectorAll('.setmaxx-song-row')).filter(function(row) {
      const checkbox = row.querySelector('.js-row-select');
      return checkbox && checkbox.checked;
    });
  }

  function visibleRows() {
    return Array.from(table.querySelectorAll('.setmaxx-song-row')).filter(function(row) {
      return !row.hidden;
    });
  }

  function updateSelectionControls() {
    const checkboxes = visibleRows().map(function(row) {
      return row.querySelector('.js-row-select');
    }).filter(Boolean);
    const selectedCount = checkboxes.filter(function(checkbox) { return checkbox.checked; }).length;
    button.disabled = selectedCount === 0;
    if (deleteButton) deleteButton.disabled = selectedCount === 0;
    if (selectAll) {
      selectAll.checked = checkboxes.length > 0 && selectedCount === checkboxes.length;
      selectAll.indeterminate = selectedCount > 0 && selectedCount < checkboxes.length;
    }
  }

  table.addEventListener('change', function(event) {
    if (event.target && event.target.classList.contains('js-row-select')) {
      updateSelectionControls();
    }
  });

  if (selectAll) {
    selectAll.addEventListener('change', function() {
      visibleRows().forEach(function(row) {
        const checkbox = row.querySelector('.js-row-select');
        if (checkbox) checkbox.checked = selectAll.checked;
      });
      updateSelectionControls();
    });
  }

  alphaButtons.forEach(function(alphaButton) {
    alphaButton.addEventListener('click', function() {
      const letter = alphaButton.getAttribute('data-letter');
      alphaButtons.forEach(function(item) { item.classList.toggle('active', item === alphaButton); });
      table.querySelectorAll('.setmaxx-song-row').forEach(function(row) {
        const hidden = letter !== 'all' && row.getAttribute('data-letter') !== letter;
        row.hidden = hidden;
        if (hidden) {
          const checkbox = row.querySelector('.js-row-select');
          if (checkbox) checkbox.checked = false;
        }
      });
      if (selectAll) selectAll.checked = false;
      updateSelectionControls();
    });
  });

  if (deleteButton) {
    deleteButton.addEventListener('click', function(event) {
      const count = selectedRows().length;
      if (count === 0) {
        event.preventDefault();
        return;
      }
      if (!window.confirm('Delete ' + count + ' selected ' + (count === 1 ? 'song' : 'songs') + '?')) {
        event.preventDefault();
      }
    });
  }

  button.addEventListener('click', async function() {
    const rows = selectedRows();
    if (rows.length === 0) {
      button.textContent = 'Select rows first';
      window.setTimeout(function() {
        button.textContent = 'Enrich selected';
      }, 1600);
      return;
    }
    let enriched = 0;
    button.disabled = true;
    if (deleteButton) deleteButton.disabled = true;
    button.textContent = 'Enriching...';

    for (const row of rows) {
      const title = row.querySelector('.js-title');
      const artist = row.querySelector('.js-artist');
      if (!title || title.value.trim() === '') continue;

      try {
        const result = await findTrack(title.value.trim(), artist ? artist.value.trim() : '');
        if (!result) continue;

        const year = row.querySelector('.js-year');
        const genre = row.querySelector('.js-genre');
        const length = row.querySelector('.js-length');
        const prerecorded = row.querySelector('.js-prerecorded');

        if (artist && isBlank(artist) && result.artistName) artist.value = result.artistName;
        if (year && isBlank(year) && result.releaseDate) year.value = String(new Date(result.releaseDate).getFullYear());
        if (genre && isBlank(genre) && result.primaryGenreName) genre.value = result.primaryGenreName;
        if (length && isBlank(length) && result.trackTimeMillis) {
          length.value = msToLength(result.trackTimeMillis);
          if (prerecorded) prerecorded.checked = true;
        }
        enriched++;
      } catch (error) {
        continue;
      }
    }

    button.textContent = enriched ? 'Enriched ' + enriched + ' selected' : 'No matches found';
    window.setTimeout(function() {
      button.textContent = 'Enrich selected';
      updateSelectionControls();
    }, 1800);
  });

  updateSelectionControls();
})();
</script>
<?php setmaxx_page_foot(); ?>
