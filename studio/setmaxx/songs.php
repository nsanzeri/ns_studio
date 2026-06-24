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
        'broad_genre' => "ADD COLUMN `broad_genre` varchar(80) DEFAULT NULL AFTER `genre`",
        'is_prerecorded' => "ADD COLUMN `is_prerecorded` tinyint(1) NOT NULL DEFAULT 0 AFTER `broad_genre`",
        'track_length_seconds' => "ADD COLUMN `track_length_seconds` smallint(5) unsigned DEFAULT NULL AFTER `is_prerecorded`",
        'is_medley' => "ADD COLUMN `is_medley` tinyint(1) NOT NULL DEFAULT 0 AFTER `track_length_seconds`",
        'medley_name' => "ADD COLUMN `medley_name` varchar(190) DEFAULT NULL AFTER `is_medley`",
        'opening_song' => "ADD COLUMN `opening_song` tinyint(1) NOT NULL DEFAULT 0 AFTER `medley_name`",
        'vocal_difficulty' => "ADD COLUMN `vocal_difficulty` enum('easy','medium','hard') DEFAULT NULL AFTER `opening_song`",
        'song_key' => "ADD COLUMN `song_key` varchar(24) DEFAULT NULL AFTER `vocal_difficulty`",
        'tempo_bpm' => "ADD COLUMN `tempo_bpm` smallint(5) unsigned DEFAULT NULL AFTER `song_key`",
        'family_friendly' => "ADD COLUMN `family_friendly` tinyint(1) NOT NULL DEFAULT 1 AFTER `tempo_bpm`",
        'instrumental' => "ADD COLUMN `instrumental` tinyint(1) NOT NULL DEFAULT 0 AFTER `family_friendly`",
        'performance_notes' => "ADD COLUMN `performance_notes` text DEFAULT NULL AFTER `instrumental`",
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
        $broadGenre = null;
        $year = null;

        if (str_contains($line, "\t")) {
            $parts = array_map('trim', explode("\t", $line));
        } elseif (preg_match('/\s+-\s+/', $line)) {
            $parts = array_map('trim', preg_split('/\s+-\s+/', $line, 2));
        } elseif (str_contains($line, ',')) {
            $parts = array_map('trim', str_getcsv($line, ',', '"', ''));
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
                if (in_array($heading, ['broad genre', 'category'], true)) $headerMap['broad_genre'] = $index;
            }
            continue;
        }

        if (is_array($headerMap) && isset($headerMap['title'])) {
            $title = $parts[$headerMap['title']] ?? '';
            $artist = isset($headerMap['artist']) ? ($parts[$headerMap['artist']] ?? null) : null;
            $year = isset($headerMap['release_year']) ? setmaxx_clean_int($parts[$headerMap['release_year']] ?? '', 1800, (int)date('Y') + 1) : null;
            $genre = isset($headerMap['genre']) ? setmaxx_clean_text($parts[$headerMap['genre']] ?? '', 120) : null;
            $broadGenre = isset($headerMap['broad_genre']) ? setmaxx_clean_text($parts[$headerMap['broad_genre']] ?? '', 80) : null;
        } elseif (count($parts) >= 2) {
            $title = $parts[0];
            $artist = $parts[1] !== '' ? $parts[1] : null;
            if (count($parts) >= 3) $year = setmaxx_clean_int($parts[2], 1800, (int)date('Y') + 1);
            if (count($parts) >= 4) $genre = setmaxx_clean_text($parts[3], 120);
            if (count($parts) >= 5) $broadGenre = setmaxx_clean_text($parts[4], 80);
        }

        $title = setmaxx_clean_text($title);
        if ($title !== null) {
            $rows[] = [
                'title' => $title,
                'artist' => setmaxx_clean_text($artist),
                'release_year' => $year,
                'genre' => $genre,
                'broad_genre' => $broadGenre ?? null,
            ];
        }
    }
    return $rows;
}

if (!empty($_SESSION['setmaxx_songs_messages']) && is_array($_SESSION['setmaxx_songs_messages'])) {
    $messages = array_merge($messages, $_SESSION['setmaxx_songs_messages']);
    unset($_SESSION['setmaxx_songs_messages']);
}

if (!empty($_SESSION['setmaxx_songs_errors']) && is_array($_SESSION['setmaxx_songs_errors'])) {
    $errors = array_merge($errors, $_SESSION['setmaxx_songs_errors']);
    unset($_SESSION['setmaxx_songs_errors']);
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
                    "INSERT INTO setmaxx_songs (user_id, title, artist, release_year, genre, broad_genre, tip_amount_cents)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $added = 0;
                $skipped = 0;
                foreach ($importRows as $row) {
                    $key = strtolower($row['title'] . '|' . (string)($row['artist'] ?? ''));
                    if (isset($existing[$key])) {
                        $skipped++;
                        continue;
                    }
                    $insert->execute([$userId, $row['title'], $row['artist'], $row['release_year'], $row['genre'], $row['broad_genre'], 0]);
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
                     SET title = ?, artist = ?, release_year = ?, genre = ?, broad_genre = ?, is_prerecorded = ?, track_length_seconds = ?,
                         is_medley = ?, medley_name = ?, opening_song = ?, vocal_difficulty = ?, song_key = ?,
                         tempo_bpm = ?, family_friendly = ?, instrumental = ?, performance_notes = ?, tip_amount_cents = ?, is_active = ?
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
                    $tipDollars = (float)($row['tip_dollars'] ?? 0);

                    $update->execute([
                        $title,
                        setmaxx_clean_text($row['artist'] ?? ''),
                        setmaxx_clean_int($row['release_year'] ?? '', 1800, (int)date('Y') + 1),
                        setmaxx_clean_text($row['genre'] ?? '', 120),
                        setmaxx_clean_text($row['broad_genre'] ?? '', 80),
                        !empty($row['is_prerecorded']) ? 1 : 0,
                        setmaxx_parse_length_seconds($row['track_length'] ?? ''),
                        !empty($row['is_medley']) ? 1 : 0,
                        setmaxx_clean_text($row['medley_name'] ?? ''),
                        !empty($row['opening_song']) ? 1 : 0,
                        $difficulty,
                        setmaxx_clean_text($row['song_key'] ?? '', 24),
                        setmaxx_clean_int($row['tempo_bpm'] ?? '', 1, 400),
                        !empty($row['family_friendly']) ? 1 : 0,
                        !empty($row['instrumental']) ? 1 : 0,
                        setmaxx_clean_text($row['performance_notes'] ?? '', 2000),
                        max(0, (int)round($tipDollars * 100)),
                        !empty($row['is_active']) ? 1 : 0,
                        $songId,
                        $userId,
                    ]);
                    $saved++;
                }
                $messages[] = $saved > 0
                    ? $saved . ' catalog ' . ($saved === 1 ? 'row' : 'rows') . ' saved.'
                    : 'No catalog changes to save.';
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
                if ($title === null) throw new RuntimeException('Song title is required.');
                $stmt = $pdo->prepare("INSERT INTO setmaxx_songs (user_id, title, artist, tip_amount_cents) VALUES (?, ?, ?, 0)");
                $stmt->execute([$userId, $title, $artist]);
                $messages[] = 'Song added to your Set Maxx catalog.';
            }
        } catch (Throwable $e) { $errors[] = $e->getMessage(); }
    }
}

if (is_post()) {
    $_SESSION['setmaxx_songs_messages'] = $messages;
    $_SESSION['setmaxx_songs_errors'] = $errors;
    header('Location: ' . base_url('/setmaxx/songs.php'));
    exit;
}

$songs = [];
$availableLetters = [];
$songCount = 0;
if ($tablesReady) {
    $songsStmt = $pdo->prepare(
        "SELECT id, title, artist, release_year, genre, broad_genre, is_prerecorded, track_length_seconds, is_medley,
                medley_name, opening_song, vocal_difficulty, song_key, tempo_bpm, family_friendly,
                instrumental, performance_notes, tip_amount_cents, is_active, created_at
         FROM setmaxx_songs
         WHERE user_id = ?
         ORDER BY is_active DESC, title ASC, artist ASC"
    );
    $songsStmt->execute([$userId]);
    $songs = $songsStmt->fetchAll(PDO::FETCH_ASSOC);
    $songCount = count($songs);
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
      <div class="setmaxx-section-head">
        <h2>Import songs</h2>
        <button class="setmaxx-help-button" type="button" id="setmaxxImportHelpBtn" aria-label="Show import help" aria-haspopup="dialog">?</button>
      </div>
      <form method="post" enctype="multipart/form-data" class="setmaxx-stack" action="">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="import_songs">
        <div class="setmaxx-field">
          <label for="import_text">Paste titles or title/artist rows</label>
          <textarea class="setmaxx-textarea" id="import_text" name="import_text" placeholder="Sweet Caroline - Neil Diamond&#10;September, Earth Wind &amp; Fire&#10;Mr. Brightside"></textarea>
          <div class="setmaxx-help">CSV, tab-separated, and "Title - Artist" rows are supported. Optional columns: year, source genre, broad genre.</div>
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
        <div class="setmaxx-section-head">
          <h2>Editable catalog</h2>
          <button class="setmaxx-help-button" type="button" id="setmaxxCatalogHelpBtn" aria-label="Show catalog help" aria-haspopup="dialog">?</button>
        </div>
        <div class="setmaxx-help"><?= (int)$songCount ?> total <?= $songCount === 1 ? 'entry' : 'entries' ?>. Only changed rows are saved, which keeps large catalogs fast.</div>
      </div>
      <div class="setmaxx-actions">
        <button class="btn btn-outline" type="button" id="setmaxxEnrichBtn" <?= $songs ? '' : 'disabled' ?>>Enrich visible</button>
        <button class="btn btn-outline" type="button" id="setmaxxExportBtn" <?= $songs ? '' : 'disabled' ?>>Export / print</button>
        <button class="btn btn-outline" type="submit" name="action" value="delete_selected" id="setmaxxDeleteSelectedBtn" <?= $isProUser && $songs ? '' : 'disabled' ?>>Delete selected</button>
        <button class="btn btn-primary" type="submit" <?= $isProUser && $songs ? '' : 'disabled' ?>>Save catalog</button>
      </div>
    </div>

    <?php if (!$songs): ?>
      <div class="setmaxx-row" style="margin-top:1rem;"><div class="setmaxx-meta">No songs yet. Import a list or add a few staples first.</div></div>
    <?php else: ?>
      <div class="setmaxx-alpha-menu" aria-label="Song alphabet filter">
        <span class="setmaxx-alpha-label">Filter</span>
        <button class="setmaxx-alpha-button active" type="button" data-letter="all">All</button>
        <?php foreach (array_merge(['#'], range('A', 'Z')) as $letter): ?>
          <button class="setmaxx-alpha-button" type="button" data-letter="<?= e($letter) ?>" <?= isset($availableLetters[$letter]) ? '' : 'disabled' ?>><?= e($letter) ?></button>
        <?php endforeach; ?>
        <span class="setmaxx-alpha-spacer"></span>
        <span class="setmaxx-alpha-label">Sort</span>
        <button class="setmaxx-sort-button active" type="button" data-sort="title">Title</button>
        <button class="setmaxx-sort-button" type="button" data-sort="artist">Artist</button>
      </div>
      <div class="setmaxx-table-wrap">
        <table class="setmaxx-song-table" id="setmaxxSongTable">
          <colgroup>
            <col class="setmaxx-col-select">
            <col class="setmaxx-col-check">
            <col class="setmaxx-col-title">
            <col class="setmaxx-col-artist">
            <col class="setmaxx-col-lyrics">
            <col class="setmaxx-col-money">
            <col class="setmaxx-col-check">
            <col class="setmaxx-col-vocal">
            <col class="setmaxx-col-year">
            <col class="setmaxx-col-genre">
            <col class="setmaxx-col-genre">
            <col class="setmaxx-col-length">
            <col class="setmaxx-col-key">
            <col class="setmaxx-col-tempo">
            <col class="setmaxx-col-check">
            <col class="setmaxx-col-notes">
          </colgroup>
          <thead>
            <tr>
              <th><input type="checkbox" id="setmaxxSelectAll" aria-label="Select all songs"></th>
              <th>Active</th>
              <th>Title</th>
              <th>Artist</th>
              <th>Lyrics</th>
              <th>Min $</th>
              <th>Opener</th>
              <th>Vocal</th>
              <th>Year</th>
              <th>Source genre</th>
              <th>Broad genre</th>
              <th>Length</th>
              <th>Key</th>
              <th>Tempo</th>
              <th>Instr.</th>
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
              <tr class="setmaxx-song-row" data-song-id="<?= $id ?>" data-letter="<?= e($letter) ?>" data-title="<?= e(strtolower((string)$song['title'])) ?>" data-artist="<?= e(strtolower((string)($song['artist'] ?: $song['title']))) ?>" data-title-letter="<?= e($letter) ?>" data-artist-letter="<?= e(preg_match('/[A-Z]/', strtoupper(substr(trim((string)($song['artist'] ?: $song['title'])), 0, 1))) ? strtoupper(substr(trim((string)($song['artist'] ?: $song['title'])), 0, 1)) : '#') ?>">
                <td>
                  <input class="js-row-select" type="checkbox" name="selected_song_ids[]" value="<?= $id ?>" aria-label="Select <?= e($song['title']) ?>">
                  <input class="js-row-dirty" type="hidden" name="dirty_song_ids[]" value="" disabled>
                </td>
                <td><input type="hidden" name="songs[<?= $id ?>][is_active]" value="0"><input type="checkbox" name="songs[<?= $id ?>][is_active]" value="1" <?= !empty($song['is_active']) ? 'checked' : '' ?>></td>
                <td><input class="setmaxx-grid-input js-title" name="songs[<?= $id ?>][title]" value="<?= e($song['title']) ?>" required></td>
                <td><input class="setmaxx-grid-input js-artist" name="songs[<?= $id ?>][artist]" value="<?= e((string)$song['artist']) ?>"></td>
                <td><a class="setmaxx-mini-link" href="<?= e(setmaxx_lyrics_url((string)$song['title'], (string)$song['artist'])) ?>" target="_blank" rel="noopener">Lyrics</a></td>
                <td><input class="setmaxx-grid-input setmaxx-grid-input-compact" name="songs[<?= $id ?>][tip_dollars]" type="number" min="0" max="100" step="1" value="<?= e((string)(((int)$song['tip_amount_cents']) / 100)) ?>"></td>
                <td><input type="hidden" name="songs[<?= $id ?>][opening_song]" value="0"><input type="checkbox" name="songs[<?= $id ?>][opening_song]" value="1" <?= !empty($song['opening_song']) ? 'checked' : '' ?>></td>
                <td>
                  <select class="setmaxx-grid-input setmaxx-grid-input-compact" name="songs[<?= $id ?>][vocal_difficulty]">
                    <option value=""></option>
                    <option value="easy" <?= $song['vocal_difficulty'] === 'easy' ? 'selected' : '' ?>>Easy</option>
                    <option value="medium" <?= $song['vocal_difficulty'] === 'medium' ? 'selected' : '' ?>>Med</option>
                    <option value="hard" <?= $song['vocal_difficulty'] === 'hard' ? 'selected' : '' ?>>Hard</option>
                  </select>
                </td>
                <td><input class="setmaxx-grid-input setmaxx-grid-input-compact js-year" name="songs[<?= $id ?>][release_year]" type="number" min="1800" max="<?= (int)date('Y') + 1 ?>" value="<?= e((string)$song['release_year']) ?>"></td>
                <td><input class="setmaxx-grid-input js-genre" name="songs[<?= $id ?>][genre]" value="<?= e((string)$song['genre']) ?>"></td>
                <td><input class="setmaxx-grid-input" name="songs[<?= $id ?>][broad_genre]" placeholder="Pop, Rock, Rap" value="<?= e((string)$song['broad_genre']) ?>"></td>
                <td><input class="setmaxx-grid-input setmaxx-grid-input-compact js-length" name="songs[<?= $id ?>][track_length]" placeholder="3:45" value="<?= e(setmaxx_seconds_to_length((int)($song['track_length_seconds'] ?? 0))) ?>"></td>
                <td><input class="setmaxx-grid-input setmaxx-grid-input-compact" name="songs[<?= $id ?>][song_key]" value="<?= e((string)$song['song_key']) ?>"></td>
                <td><input class="setmaxx-grid-input setmaxx-grid-input-compact" name="songs[<?= $id ?>][tempo_bpm]" type="number" min="1" max="400" value="<?= e((string)$song['tempo_bpm']) ?>"></td>
                <td><input type="hidden" name="songs[<?= $id ?>][instrumental]" value="0"><input type="checkbox" name="songs[<?= $id ?>][instrumental]" value="1" <?= !empty($song['instrumental']) ? 'checked' : '' ?>></td>
                <td>
                  <input class="js-prerecorded" type="hidden" name="songs[<?= $id ?>][is_prerecorded]" value="<?= !empty($song['is_prerecorded']) ? '1' : '0' ?>">
                  <input type="hidden" name="songs[<?= $id ?>][is_medley]" value="<?= !empty($song['is_medley']) ? '1' : '0' ?>">
                  <input type="hidden" name="songs[<?= $id ?>][medley_name]" value="<?= e((string)$song['medley_name']) ?>">
                  <input type="hidden" name="songs[<?= $id ?>][family_friendly]" value="<?= !empty($song['family_friendly']) ? '1' : '0' ?>">
                  <textarea class="setmaxx-grid-notes" name="songs[<?= $id ?>][performance_notes]"><?= e((string)$song['performance_notes']) ?></textarea>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </form>
  <dialog class="setmaxx-dialog" id="setmaxxImportHelpDialog" aria-labelledby="setmaxxImportHelpTitle">
    <div class="setmaxx-dialog-inner">
      <div class="setmaxx-dialog-head">
        <div>
          <div class="setmaxx-pill">Import help</div>
          <h2 class="setmaxx-dialog-title" id="setmaxxImportHelpTitle">What can I import?</h2>
        </div>
        <button class="setmaxx-dialog-close" type="button" id="setmaxxImportHelpClose" aria-label="Close">&times;</button>
      </div>
      <ul class="setmaxx-format-list">
        <li>One song per line is required.</li>
        <li>Title-only rows work: <strong>Mr. Brightside</strong></li>
        <li>Title and artist rows work with a dash: <strong>Sweet Caroline - Neil Diamond</strong></li>
        <li>CSV or tab-separated rows work in this order: title, artist, year, source genre, broad genre.</li>
        <li>A header row is optional. Supported headers include title, artist, year, genre, and broad genre.</li>
      </ul>
      <pre class="setmaxx-format-example">A DAY IN THE LIFE - THE BEATLES
A HORSE WITH NO NAME - AMERICA
ADDICTED TO LOVE - ROBERT PALMER</pre>
    </div>
  </dialog>
  <dialog class="setmaxx-dialog" id="setmaxxCatalogHelpDialog" aria-labelledby="setmaxxCatalogHelpTitle">
    <div class="setmaxx-dialog-inner">
      <div class="setmaxx-dialog-head">
        <div>
          <div class="setmaxx-pill">Catalog help</div>
          <h2 class="setmaxx-dialog-title" id="setmaxxCatalogHelpTitle">How the catalog controls work</h2>
        </div>
        <button class="setmaxx-dialog-close" type="button" id="setmaxxCatalogHelpClose" aria-label="Close">&times;</button>
      </div>
      <ul class="setmaxx-format-list">
        <li><strong>Select rows</strong> to enrich or delete a specific group. With nothing selected, Enrich visible works on the current filtered view.</li>
        <li><strong>Active</strong> controls whether fans can request the song.</li>
        <li><strong>Min $</strong> is the minimum request amount for that song.</li>
        <li><strong>Opener, vocal, key, tempo, and instrumental</strong> are stage-planning fields.</li>
        <li><strong>Year, source genre, broad genre, and length</strong> help organize the catalog and build better sets.</li>
        <li><strong>Notes</strong> are private performance reminders for arrangement, capo, transitions, or special instructions.</li>
      </ul>
    </div>
  </dialog>
  <dialog class="setmaxx-dialog" id="setmaxxExportDialog" aria-labelledby="setmaxxExportTitle">
    <div class="setmaxx-dialog-inner">
      <div class="setmaxx-dialog-head">
        <div>
          <div class="setmaxx-pill">Export</div>
          <h2 class="setmaxx-dialog-title" id="setmaxxExportTitle">Export or print songs</h2>
        </div>
        <button class="setmaxx-dialog-close" type="button" id="setmaxxExportClose" aria-label="Close">&times;</button>
      </div>
      <p class="setmaxx-help" id="setmaxxExportSummary">Selected songs are exported. If none are selected, the current filtered list is used.</p>
      <div class="setmaxx-option-list">
        <label><input type="checkbox" id="setmaxxExportArtist" checked> Include artist</label>
        <label><input type="checkbox" id="setmaxxExportKey"> Include song key</label>
      </div>
      <div class="setmaxx-actions">
        <button class="btn btn-primary" type="button" id="setmaxxPrintBtn">Print list</button>
        <button class="btn btn-outline" type="button" id="setmaxxCsvBtn">Download CSV</button>
      </div>
    </div>
  </dialog>
  <?php endif; ?>
</main>
<style>
  .setmaxx-catalog-toolbar { display:flex; justify-content:space-between; gap:1rem; align-items:flex-start; flex-wrap:wrap; margin-bottom:1rem; }
  .setmaxx-alpha-menu { display:flex; gap:.35rem; flex-wrap:wrap; align-items:center; margin:.25rem 0 1rem; padding:.65rem; border-radius:16px; background:rgba(9,8,20,.7); border:1px solid rgba(255,255,255,.08); }
  .setmaxx-alpha-label { color:rgba(255,255,255,.68); font-size:.82rem; font-weight:600; padding:0 .25rem; }
  .setmaxx-alpha-spacer { flex:1 1 1rem; }
  .setmaxx-alpha-button,
  .setmaxx-sort-button { min-width:34px; height:34px; border-radius:10px; border:1px solid rgba(255,255,255,.12); background:rgba(255,255,255,.05); color:#fff; font:inherit; font-size:.82rem; cursor:pointer; }
  .setmaxx-sort-button { padding:0 .75rem; }
  .setmaxx-alpha-button.active,
  .setmaxx-sort-button.active,
  .setmaxx-alpha-button:hover,
  .setmaxx-sort-button:hover { background:rgba(140,107,255,.24); border-color:rgba(140,107,255,.45); }
  .setmaxx-alpha-button:disabled { opacity:.35; cursor:not-allowed; }
  .setmaxx-table-wrap { overflow:auto; border:1px solid rgba(255,255,255,.08); border-radius:16px; }
  .setmaxx-song-table { width:100%; min-width:1180px; border-collapse:collapse; table-layout:fixed; }
  .setmaxx-col-select { width:42px; }
  .setmaxx-col-check { width:56px; }
  .setmaxx-col-title { width:210px; }
  .setmaxx-col-artist { width:132px; }
  .setmaxx-col-lyrics { width:66px; }
  .setmaxx-col-money { width:76px; }
  .setmaxx-col-vocal { width:88px; }
  .setmaxx-col-year { width:76px; }
  .setmaxx-col-genre { width:112px; }
  .setmaxx-col-length { width:76px; }
  .setmaxx-col-key { width:66px; }
  .setmaxx-col-tempo { width:76px; }
  .setmaxx-col-notes { width:180px; }
  .setmaxx-song-table th,
  .setmaxx-song-table td { padding:.55rem; border-bottom:1px solid rgba(255,255,255,.07); vertical-align:top; }
  .setmaxx-song-table th { position:sticky; top:0; z-index:1; background:#151323; color:rgba(255,255,255,.78); font-size:.78rem; text-align:left; font-weight:600; }
  .setmaxx-song-table tbody tr:nth-child(even) { background:rgba(255,255,255,.025); }
  .setmaxx-grid-input,
  .setmaxx-grid-notes { width:100%; min-width:0; padding:.55rem .6rem; border-radius:10px; border:1px solid rgba(255,255,255,.1); background:rgba(255,255,255,.05); color:#fff; font:inherit; font-size:.88rem; }
  .setmaxx-grid-input-compact { padding-left:.5rem; padding-right:.5rem; }
  .setmaxx-song-table input[type="number"] { appearance:textfield; -moz-appearance:textfield; }
  .setmaxx-song-table input[type="number"]::-webkit-outer-spin-button,
  .setmaxx-song-table input[type="number"]::-webkit-inner-spin-button { -webkit-appearance:none; margin:0; }
  .setmaxx-grid-input option { background:#151323; color:#fff; }
  .setmaxx-grid-notes { min-width:180px; height:42px; resize:vertical; }
  .setmaxx-song-table input[type="checkbox"] { width:18px; height:18px; accent-color:#8c6bff; }
  .setmaxx-mini-link { display:inline-flex; align-items:center; min-height:34px; color:#efe7ff; font-size:.86rem; text-decoration:none; }
  .setmaxx-mini-link:hover { text-decoration:underline; }
  .setmaxx-section-head { display:flex; align-items:center; gap:.65rem; margin-bottom:1rem; }
  .setmaxx-section-head h2 { margin:0; }
  .setmaxx-help-button { display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:999px; border:1px solid rgba(255,255,255,.18); background:rgba(255,255,255,.06); color:#efe7ff; font-weight:700; cursor:pointer; }
  .setmaxx-help-button:hover, .setmaxx-help-button:focus-visible { border-color:rgba(140,107,255,.55); background:rgba(140,107,255,.18); outline:none; }
  .setmaxx-dialog { width:min(560px, calc(100vw - 2rem)); border:1px solid rgba(255,255,255,.12); border-radius:18px; padding:0; background:#151323; color:#fff; box-shadow:0 24px 70px rgba(0,0,0,.55); }
  .setmaxx-dialog::backdrop { background:rgba(0,0,0,.62); backdrop-filter:blur(4px); }
  .setmaxx-dialog-inner { padding:1.15rem; display:grid; gap:1rem; }
  .setmaxx-dialog-head { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; }
  .setmaxx-dialog-title { margin:0; font-size:1.15rem; }
  .setmaxx-dialog-close { border:1px solid rgba(255,255,255,.14); border-radius:999px; width:36px; height:36px; background:rgba(255,255,255,.05); color:#fff; cursor:pointer; font-size:1.35rem; line-height:1; }
  .setmaxx-format-list { margin:0; padding-left:1.2rem; color:rgba(255,255,255,.82); line-height:1.75; }
  .setmaxx-format-example { margin:0; padding:.85rem .95rem; border-radius:14px; background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.08); color:#efe7ff; white-space:pre-wrap; overflow:auto; }
  .setmaxx-option-list { display:grid; gap:.55rem; color:rgba(255,255,255,.86); }
  .setmaxx-option-list label { display:flex; align-items:center; gap:.55rem; }
  .setmaxx-option-list input[type="checkbox"] { width:18px; height:18px; accent-color:#8c6bff; }
</style>
<script>
(function() {
  function setupDialog(buttonId, dialogId, closeId) {
    const openButton = document.getElementById(buttonId);
    const dialog = document.getElementById(dialogId);
    const closeButton = document.getElementById(closeId);
    if (!openButton || !dialog) return;

    function closeDialog() {
      if (typeof dialog.close === 'function') {
        dialog.close();
      } else {
        dialog.setAttribute('hidden', '');
      }
    }

    openButton.addEventListener('click', function() {
      if (typeof dialog.showModal === 'function') {
        dialog.showModal();
      } else {
        dialog.removeAttribute('hidden');
      }
    });

    if (closeButton) closeButton.addEventListener('click', closeDialog);
    dialog.addEventListener('click', function(event) {
      if (event.target === dialog) closeDialog();
    });
  }

  setupDialog('setmaxxImportHelpBtn', 'setmaxxImportHelpDialog', 'setmaxxImportHelpClose');
  setupDialog('setmaxxCatalogHelpBtn', 'setmaxxCatalogHelpDialog', 'setmaxxCatalogHelpClose');
})();

(function() {
  const button = document.getElementById('setmaxxEnrichBtn');
  const exportButton = document.getElementById('setmaxxExportBtn');
  const exportDialog = document.getElementById('setmaxxExportDialog');
  const exportClose = document.getElementById('setmaxxExportClose');
  const printButton = document.getElementById('setmaxxPrintBtn');
  const csvButton = document.getElementById('setmaxxCsvBtn');
  const exportArtist = document.getElementById('setmaxxExportArtist');
  const exportKey = document.getElementById('setmaxxExportKey');
  const exportSummary = document.getElementById('setmaxxExportSummary');
  const deleteButton = document.getElementById('setmaxxDeleteSelectedBtn');
  const selectAll = document.getElementById('setmaxxSelectAll');
  const table = document.getElementById('setmaxxSongTable');
  const alphaButtons = Array.from(document.querySelectorAll('.setmaxx-alpha-button'));
  const sortButtons = Array.from(document.querySelectorAll('.setmaxx-sort-button'));
  const csrfToken = <?= json_encode(csrf_token()) ?>;
  let currentLetter = 'all';
  let currentSort = 'title';
  if (!button || !table) return;

  const editableSelector = 'input[name^="songs["], select[name^="songs["], textarea[name^="songs["]';

  function rowEditableFields(row) {
    return Array.from(row.querySelectorAll(editableSelector));
  }

  function setRowEditing(row, enabled) {
    const dirty = row.querySelector('.js-row-dirty');
    if (dirty) {
      dirty.disabled = !enabled;
      dirty.value = enabled ? (row.getAttribute('data-song-id') || '') : '';
    }
    row.classList.toggle('is-dirty', enabled);
  }

  function markRowDirty(row) {
    setRowEditing(row, true);
  }

  table.querySelectorAll('.setmaxx-song-row').forEach(function(row) {
    setRowEditing(row, false);
  });

  const catalogForm = table.closest('form');
  if (catalogForm) {
    catalogForm.addEventListener('submit', function(event) {
      const submitter = event.submitter;
      const action = submitter && submitter.name === 'action' ? submitter.value : 'save_catalog';
      table.querySelectorAll('.setmaxx-song-row').forEach(function(row) {
        const shouldSubmit = action === 'save_catalog' ? row.classList.contains('is-dirty') : false;
        rowEditableFields(row).forEach(function(field) {
          field.disabled = !shouldSubmit;
        });
      });
    });
  }

  function isBlank(input) {
    return input && input.value.trim() === '';
  }

  function msToLength(ms) {
    const seconds = Math.round(ms / 1000);
    const minutes = Math.floor(seconds / 60);
    return minutes + ':' + String(seconds % 60).padStart(2, '0');
  }

  async function findTrack(title, artist) {
    return findTrackJsonp(title, artist);
  }

  function findTrackJsonp(title, artist) {
    return new Promise(function(resolve, reject) {
      const callbackName = 'setmaxxItunes' + Date.now() + Math.floor(Math.random() * 10000);
      const term = [title, artist].filter(Boolean).join(' ');
      const script = document.createElement('script');
      const timer = window.setTimeout(function() {
        cleanup();
        reject(new Error('Lookup timed out'));
      }, 9000);

      function cleanup() {
        window.clearTimeout(timer);
        delete window[callbackName];
        if (script.parentNode) script.parentNode.removeChild(script);
      }

      window[callbackName] = function(data) {
        cleanup();
        resolve(data && data.results && data.results.length ? data.results[0] : null);
      };

      script.onerror = function() {
        cleanup();
        reject(new Error('Lookup failed'));
      };

      script.src = 'https://itunes.apple.com/search?media=music&entity=song&country=US&limit=1&term=' + encodeURIComponent(term) + '&callback=' + encodeURIComponent(callbackName);
      document.head.appendChild(script);
    });
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

  function exportRows() {
    const checkedRows = selectedRows();
    return checkedRows.length ? checkedRows : visibleRows();
  }

  function fieldValue(row, selector) {
    const field = row.querySelector(selector);
    return field ? field.value.trim() : '';
  }

  function exportData() {
    return exportRows().map(function(row) {
      return {
        title: fieldValue(row, '.js-title'),
        artist: fieldValue(row, '.js-artist'),
        key: fieldValue(row, 'input[name$="[song_key]"]')
      };
    }).filter(function(song) {
      return song.title !== '';
    });
  }

  function exportColumns() {
    const columns = ['Title'];
    if (exportArtist && exportArtist.checked) columns.push('Artist');
    if (exportKey && exportKey.checked) columns.push('Key');
    return columns;
  }

  function valueForColumn(song, column) {
    if (column === 'Artist') return song.artist;
    if (column === 'Key') return song.key;
    return song.title;
  }

  function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, function(character) {
      return {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
      }[character];
    });
  }

  function csvValue(value) {
    const text = String(value);
    return /[",\r\n]/.test(text) ? '"' + text.replace(/"/g, '""') + '"' : text;
  }

  function updateExportSummary() {
    if (!exportSummary) return;
    const checkedCount = selectedRows().length;
    const count = exportRows().length;
    const source = checkedCount > 0 ? 'selected' : 'visible';
    exportSummary.textContent = count + ' ' + source + ' ' + (count === 1 ? 'song' : 'songs') + ' will be exported.';
  }

  function closeExportDialog() {
    if (!exportDialog) return;
    if (typeof exportDialog.close === 'function') {
      exportDialog.close();
    } else {
      exportDialog.setAttribute('hidden', '');
    }
  }

  function openExportDialog() {
    if (!exportDialog) return;
    updateExportSummary();
    if (typeof exportDialog.showModal === 'function') {
      exportDialog.showModal();
    } else {
      exportDialog.removeAttribute('hidden');
    }
  }

  function printExport() {
    const songsToExport = exportData();
    if (!songsToExport.length) return;
    const columns = exportColumns();
    const header = columns.map(function(column) {
      return '<th>' + escapeHtml(column) + '</th>';
    }).join('');
    const body = songsToExport.map(function(song) {
      return '<tr>' + columns.map(function(column) {
        return '<td>' + escapeHtml(valueForColumn(song, column)) + '</td>';
      }).join('') + '</tr>';
    }).join('');
    const printWindow = window.open('', '_blank');
    if (!printWindow) {
      window.alert('Your browser blocked the print window. Allow popups for this site and try again.');
      return;
    }
    printWindow.document.write('<!doctype html><html><head><meta charset="utf-8"><title>Set Maxx Song Export</title><style>body{font-family:Arial,sans-serif;margin:32px;color:#111;}h1{font-size:24px;margin:0 0 6px;}p{margin:0 0 18px;color:#555;}table{width:100%;border-collapse:collapse;}th,td{padding:8px 10px;border-bottom:1px solid #ddd;text-align:left;}th{background:#f2f2f2;}@media print{body{margin:18mm;}}</style></head><body><h1>Set Maxx Song Catalog</h1><p>' + songsToExport.length + ' ' + (songsToExport.length === 1 ? 'song' : 'songs') + '</p><table><thead><tr>' + header + '</tr></thead><tbody>' + body + '</tbody></table></body></html>');
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
  }

  function downloadCsv() {
    const songsToExport = exportData();
    if (!songsToExport.length) return;
    const columns = exportColumns();
    const lines = [columns.map(csvValue).join(',')].concat(songsToExport.map(function(song) {
      return columns.map(function(column) {
        return csvValue(valueForColumn(song, column));
      }).join(',');
    }));
    const blob = new Blob([lines.join('\r\n')], { type: 'text/csv;charset=utf-8' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'setmaxx-song-catalog.csv';
    document.body.appendChild(link);
    link.click();
    URL.revokeObjectURL(link.href);
    link.remove();
  }

  function updateSelectionControls() {
    const checkboxes = visibleRows().map(function(row) {
      return row.querySelector('.js-row-select');
    }).filter(Boolean);
    const selectedCount = checkboxes.filter(function(checkbox) { return checkbox.checked; }).length;
    button.disabled = visibleRows().length === 0;
    if (!button.disabled) {
      button.textContent = selectedCount > 0 ? 'Enrich selected' : 'Enrich visible';
    }
    if (deleteButton) deleteButton.disabled = selectedCount === 0;
    if (selectAll) {
      selectAll.checked = checkboxes.length > 0 && selectedCount === checkboxes.length;
      selectAll.indeterminate = selectedCount > 0 && selectedCount < checkboxes.length;
    }
    updateExportSummary();
  }

  function rowLetter(row) {
    return row.getAttribute('data-' + currentSort + '-letter') || '#';
  }

  function updateAlphabetAvailability() {
    const rows = Array.from(table.querySelectorAll('.setmaxx-song-row'));
    const letters = new Set(rows.map(rowLetter));
    alphaButtons.forEach(function(alphaButton) {
      const letter = alphaButton.getAttribute('data-letter');
      if (letter === 'all') {
        alphaButton.disabled = false;
      } else {
        alphaButton.disabled = !letters.has(letter);
      }
      if (alphaButton.disabled && alphaButton.classList.contains('active')) {
        currentLetter = 'all';
      }
    });
  }

  function sortRows() {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(table.querySelectorAll('.setmaxx-song-row'));
    rows.sort(function(a, b) {
      const aValue = a.getAttribute('data-' + currentSort) || '';
      const bValue = b.getAttribute('data-' + currentSort) || '';
      return aValue.localeCompare(bValue);
    });
    rows.forEach(function(row) { tbody.appendChild(row); });
  }

  function applyCatalogView() {
    updateAlphabetAvailability();
    sortRows();
    alphaButtons.forEach(function(item) {
      item.classList.toggle('active', item.getAttribute('data-letter') === currentLetter);
    });
    table.querySelectorAll('.setmaxx-song-row').forEach(function(row) {
      const hidden = currentLetter !== 'all' && rowLetter(row) !== currentLetter;
      row.hidden = hidden;
      if (hidden) {
        const checkbox = row.querySelector('.js-row-select');
        if (checkbox) checkbox.checked = false;
      }
    });
    if (selectAll) selectAll.checked = false;
    updateSelectionControls();
  }

  table.addEventListener('change', function(event) {
    if (event.target && event.target.classList.contains('js-row-select')) {
      updateSelectionControls();
    } else if (event.target && event.target.matches(editableSelector)) {
      markRowDirty(event.target.closest('.setmaxx-song-row'));
    }
  });

  table.addEventListener('input', function(event) {
    if (event.target && event.target.matches(editableSelector)) {
      markRowDirty(event.target.closest('.setmaxx-song-row'));
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
      if (alphaButton.disabled) return;
      currentLetter = alphaButton.getAttribute('data-letter') || 'all';
      applyCatalogView();
    });
  });

  sortButtons.forEach(function(sortButton) {
    sortButton.addEventListener('click', function() {
      currentSort = sortButton.getAttribute('data-sort') || 'title';
      sortButtons.forEach(function(item) { item.classList.toggle('active', item === sortButton); });
      currentLetter = 'all';
      applyCatalogView();
    });
  });

  if (exportButton) exportButton.addEventListener('click', openExportDialog);
  if (exportClose) exportClose.addEventListener('click', closeExportDialog);
  if (exportDialog) {
    exportDialog.addEventListener('click', function(event) {
      if (event.target === exportDialog) closeExportDialog();
    });
  }
  if (printButton) printButton.addEventListener('click', printExport);
  if (csvButton) csvButton.addEventListener('click', downloadCsv);
  if (exportArtist) exportArtist.addEventListener('change', updateExportSummary);
  if (exportKey) exportKey.addEventListener('change', updateExportSummary);

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
    const checkedRows = selectedRows();
    const rows = checkedRows.length ? checkedRows : visibleRows();
    let enriched = 0;
    let lookupError = '';
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
          if (prerecorded) {
            if (prerecorded.type === 'hidden') {
              prerecorded.value = '1';
            } else {
              prerecorded.checked = true;
            }
          }
        }
        markRowDirty(row);
        enriched++;
      } catch (error) {
        lookupError = error && error.message ? error.message : 'Lookup failed';
        continue;
      }
    }

    button.textContent = enriched ? 'Enriched ' + enriched + ' rows' : (lookupError || 'No matches found');
    window.setTimeout(function() {
      button.textContent = selectedRows().length ? 'Enrich selected' : 'Enrich visible';
      updateSelectionControls();
    }, 1800);
  });

  applyCatalogView();
})();
</script>
<?php setmaxx_page_foot(); ?>
