<?php
require_once __DIR__ . '/_common.php';

function setmaxx_setlist_seconds(?int $seconds): string {
    if (!$seconds || $seconds < 1) return 'No length';
    return floor($seconds / 60) . ':' . str_pad((string)($seconds % 60), 2, '0', STR_PAD_LEFT);
}

function setmaxx_setlist_planning_seconds(array $song): int {
    $seconds = (int)($song['track_length_seconds'] ?? 0);
    return $seconds > 0 ? $seconds : 240;
}

function setmaxx_setlist_display_length(array $song): string {
    $seconds = (int)($song['track_length_seconds'] ?? 0);
    return $seconds > 0 ? setmaxx_setlist_seconds($seconds) : '4:00 assumed';
}

function setmaxx_setlist_total_time(int $seconds): string {
    $minutes = (int)floor($seconds / 60);
    return $minutes . ' min';
}

function setmaxx_setlist_bool_filter(string $value): ?int {
    if ($value === 'yes') return 1;
    if ($value === 'no') return 0;
    return null;
}

function setmaxx_setlist_column_exists(PDO $pdo, string $columnName): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'setmaxx_songs' AND column_name = ? LIMIT 1");
    $stmt->execute([$columnName]);
    return (bool)$stmt->fetchColumn();
}

function setmaxx_setlist_ensure_broad_genre(PDO $pdo): void {
    if (!setmaxx_setlist_column_exists($pdo, 'broad_genre')) {
        $pdo->exec("ALTER TABLE `setmaxx_songs` ADD COLUMN `broad_genre` varchar(80) DEFAULT NULL AFTER `genre`");
    }
}

function setmaxx_setlist_tempo_value(array $song): int {
    return (int)($song['tempo_bpm'] ?? 0);
}

function setmaxx_setlist_order_by_tempo(array $songs, string $mode): array {
    if ($mode === 'none' || count($songs) < 2) return $songs;

    $knownTempo = [];
    $unknownTempo = [];
    foreach ($songs as $song) {
        if (setmaxx_setlist_tempo_value($song) > 0) {
            $knownTempo[] = $song;
        } else {
            $unknownTempo[] = $song;
        }
    }

    usort($knownTempo, function (array $a, array $b): int {
        return setmaxx_setlist_tempo_value($a) <=> setmaxx_setlist_tempo_value($b);
    });

    if ($mode === 'tempo_asc') {
        return array_merge($knownTempo, $unknownTempo);
    }

    if ($mode === 'tempo_desc') {
        return array_merge(array_reverse($knownTempo), $unknownTempo);
    }

    $slow = array_slice($knownTempo, 0, (int)ceil(count($knownTempo) / 2));
    $fast = array_slice($knownTempo, (int)ceil(count($knownTempo) / 2));
    $fast = array_reverse($fast);
    $ordered = [];

    $take = function (array &$bucket, int $count) use (&$ordered): void {
        for ($i = 0; $i < $count; $i++) {
            if (!$bucket) return;
            $ordered[] = array_shift($bucket);
        }
    };

    while ($slow || $fast) {
        if ($mode === 'alternate_fast_slow') {
            $take($fast, 1);
            $take($slow, 1);
        } elseif ($mode === 'two_fast_one_slow') {
            $take($fast, 2);
            $take($slow, 1);
        } elseif ($mode === 'two_slow_one_fast') {
            $take($slow, 2);
            $take($fast, 1);
        } else {
            break;
        }
    }

    return array_merge($ordered, $unknownTempo);
}

$filters = [
    'year_from' => trim((string)($_POST['year_from'] ?? '')),
    'year_to' => trim((string)($_POST['year_to'] ?? '')),
    'active' => (string)($_POST['active'] ?? 'yes'),
    'family_friendly' => (string)($_POST['family_friendly'] ?? 'any'),
    'prerecorded' => (string)($_POST['prerecorded'] ?? 'any'),
    'vocal_difficulty' => (string)($_POST['vocal_difficulty'] ?? 'any'),
    'source_genres' => array_values(array_filter(array_map('trim', (array)($_POST['source_genres'] ?? [])))),
    'broad_genres' => array_values(array_filter(array_map('trim', (array)($_POST['broad_genres'] ?? [])))),
    'set_count' => max(1, min(6, (int)($_POST['set_count'] ?? 3))),
    'set_minutes' => max(10, min(180, (int)($_POST['set_minutes'] ?? 45))),
    'tempo_order' => (string)($_POST['tempo_order'] ?? 'none'),
];

if (!in_array($filters['tempo_order'], ['none', 'tempo_asc', 'tempo_desc', 'alternate_fast_slow', 'two_fast_one_slow', 'two_slow_one_fast'], true)) {
    $filters['tempo_order'] = 'none';
}

$generatedSets = [];
$unusedSongs = [];
$unknownLengthSongs = [];
$matchingCount = 0;
$sourceGenreOptions = [];
$broadGenreOptions = [];

if ($tablesReady) {
    setmaxx_setlist_ensure_broad_genre($pdo);
    $sourceGenreStmt = $pdo->prepare("SELECT DISTINCT genre FROM setmaxx_songs WHERE user_id = ? AND genre IS NOT NULL AND genre <> '' ORDER BY genre ASC");
    $sourceGenreStmt->execute([$userId]);
    $sourceGenreOptions = array_map('strval', array_column($sourceGenreStmt->fetchAll(PDO::FETCH_ASSOC), 'genre'));

    $broadGenreStmt = $pdo->prepare("SELECT DISTINCT broad_genre FROM setmaxx_songs WHERE user_id = ? AND broad_genre IS NOT NULL AND broad_genre <> '' ORDER BY broad_genre ASC");
    $broadGenreStmt->execute([$userId]);
    $broadGenreOptions = array_map('strval', array_column($broadGenreStmt->fetchAll(PDO::FETCH_ASSOC), 'broad_genre'));
}

if ($tablesReady && is_post()) {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    } elseif (!$isProUser) {
        $errors[] = 'Set Maxx is included with the paid tools plan. Upgrade to continue.';
    } else {
        $where = ['user_id = ?'];
        $params = [$userId];

        $active = setmaxx_setlist_bool_filter($filters['active']);
        if ($active !== null) {
            $where[] = 'is_active = ?';
            $params[] = $active;
        }

        $familyFriendly = setmaxx_setlist_bool_filter($filters['family_friendly']);
        if ($familyFriendly !== null) {
            $where[] = 'family_friendly = ?';
            $params[] = $familyFriendly;
        }

        $prerecorded = setmaxx_setlist_bool_filter($filters['prerecorded']);
        if ($prerecorded !== null) {
            $where[] = 'is_prerecorded = ?';
            $params[] = $prerecorded;
        }

        if ($filters['vocal_difficulty'] !== 'any' && in_array($filters['vocal_difficulty'], ['easy', 'medium', 'hard'], true)) {
            $where[] = 'vocal_difficulty = ?';
            $params[] = $filters['vocal_difficulty'];
        }

        if ($filters['broad_genres']) {
            $selectedBroadGenres = array_values(array_intersect($filters['broad_genres'], $broadGenreOptions));
            if ($selectedBroadGenres) {
                $where[] = 'broad_genre IN (' . implode(',', array_fill(0, count($selectedBroadGenres), '?')) . ')';
                $params = array_merge($params, $selectedBroadGenres);
            }
        }

        if ($filters['source_genres']) {
            $selectedSourceGenres = array_values(array_intersect($filters['source_genres'], $sourceGenreOptions));
            if ($selectedSourceGenres) {
                $where[] = 'genre IN (' . implode(',', array_fill(0, count($selectedSourceGenres), '?')) . ')';
                $params = array_merge($params, $selectedSourceGenres);
            }
        }

        if ($filters['year_from'] !== '') {
            $where[] = 'release_year >= ?';
            $params[] = max(1800, (int)$filters['year_from']);
        }

        if ($filters['year_to'] !== '') {
            $where[] = 'release_year <= ?';
            $params[] = min((int)date('Y') + 1, (int)$filters['year_to']);
        }

        $songStmt = $pdo->prepare(
            "SELECT id, title, artist, release_year, genre, broad_genre, track_length_seconds, opening_song,
                    vocal_difficulty, song_key, tempo_bpm, family_friendly, is_active
             FROM setmaxx_songs
             WHERE " . implode(' AND ', $where) . "
             ORDER BY opening_song DESC, title ASC, artist ASC"
        );
        $songStmt->execute($params);
        $songs = $songStmt->fetchAll(PDO::FETCH_ASSOC);
        $matchingCount = count($songs);

        $planningSongs = [];
        foreach ($songs as $song) {
            if ((int)($song['track_length_seconds'] ?? 0) <= 0) {
                $unknownLengthSongs[] = $song;
            }
            $planningSongs[] = $song;
        }

        if (!$planningSongs) {
            $errors[] = 'No matching songs found for those criteria.';
        } else {
            $openers = array_values(array_filter($planningSongs, fn($song) => !empty($song['opening_song'])));
            $pool = array_values(array_filter($planningSongs, fn($song) => empty($song['opening_song'])));
            shuffle($openers);
            shuffle($pool);
            $targetSeconds = $filters['set_minutes'] * 60;

            for ($setNumber = 1; $setNumber <= $filters['set_count']; $setNumber++) {
                $generatedSets[$setNumber] = [
                    'songs' => [],
                    'seconds' => 0,
                ];

                if ($openers) {
                    $song = array_shift($openers);
                    $generatedSets[$setNumber]['songs'][] = $song;
                    $generatedSets[$setNumber]['seconds'] += setmaxx_setlist_planning_seconds($song);
                }
            }

            $pool = array_merge($pool, $openers);
            foreach ($pool as $song) {
                $seconds = setmaxx_setlist_planning_seconds($song);
                $bestSet = null;
                $bestGap = PHP_INT_MAX;

                foreach ($generatedSets as $setNumber => $set) {
                    $newTotal = $set['seconds'] + $seconds;
                    $gap = abs($targetSeconds - $newTotal);
                    if ($set['seconds'] < $targetSeconds && $gap < $bestGap) {
                        $bestSet = $setNumber;
                        $bestGap = $gap;
                    }
                }

                if ($bestSet === null) {
                    $unusedSongs[] = $song;
                    continue;
                }

                $generatedSets[$bestSet]['songs'][] = $song;
                $generatedSets[$bestSet]['seconds'] += $seconds;
            }

            foreach ($generatedSets as $setNumber => $set) {
                $generatedSets[$setNumber]['songs'] = setmaxx_setlist_order_by_tempo($set['songs'], $filters['tempo_order']);
            }
        }
    }
}

setmaxx_page_head('Set Maxx | Setlist Generator');
?>
<main class="container setmaxx-shell">
  <?php setmaxx_flash($messages, $errors); ?>
  <div class="setmaxx-card" style="margin-bottom:1rem;">
    <div class="setmaxx-pill">Setlist Generator</div>
    <h1 style="margin:.8rem 0 .35rem;">Build sets from your catalog</h1>
    <p class="setmaxx-help">Choose the room, era, and length, then generate a practical starting point from uploaded songs.</p>
  </div>

  <?php if (!$tablesReady): ?><?php setmaxx_install_notice(); ?><?php else: ?>
    <section class="setmaxx-grid">
      <div class="setmaxx-card">
        <h2 style="margin-top:0;">Criteria</h2>
        <form method="post" class="setmaxx-stack" action="">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <div class="setmaxx-form-grid">
            <div class="setmaxx-field">
              <label for="year_from">Year from</label>
              <input class="setmaxx-input" id="year_from" name="year_from" type="number" min="1800" max="<?= (int)date('Y') + 1 ?>" value="<?= e($filters['year_from']) ?>">
            </div>
            <div class="setmaxx-field">
              <label for="year_to">Year to</label>
              <input class="setmaxx-input" id="year_to" name="year_to" type="number" min="1800" max="<?= (int)date('Y') + 1 ?>" value="<?= e($filters['year_to']) ?>">
            </div>
            <div class="setmaxx-field">
              <label for="active">Active songs</label>
              <select class="setmaxx-select" id="active" name="active">
                <option value="yes" <?= $filters['active'] === 'yes' ? 'selected' : '' ?>>Active only</option>
                <option value="any" <?= $filters['active'] === 'any' ? 'selected' : '' ?>>Active and inactive</option>
                <option value="no" <?= $filters['active'] === 'no' ? 'selected' : '' ?>>Inactive only</option>
              </select>
            </div>
            <div class="setmaxx-field">
              <label for="family_friendly">Family friendly</label>
              <select class="setmaxx-select" id="family_friendly" name="family_friendly">
                <option value="any" <?= $filters['family_friendly'] === 'any' ? 'selected' : '' ?>>Any</option>
                <option value="yes" <?= $filters['family_friendly'] === 'yes' ? 'selected' : '' ?>>Family friendly only</option>
                <option value="no" <?= $filters['family_friendly'] === 'no' ? 'selected' : '' ?>>Not family friendly</option>
              </select>
            </div>
            <div class="setmaxx-field">
              <label for="set_count">Number of sets</label>
              <input class="setmaxx-input" id="set_count" name="set_count" type="number" min="1" max="6" value="<?= (int)$filters['set_count'] ?>">
            </div>
            <div class="setmaxx-field">
              <label for="set_minutes">Minutes per set</label>
              <input class="setmaxx-input" id="set_minutes" name="set_minutes" type="number" min="10" max="180" value="<?= (int)$filters['set_minutes'] ?>">
            </div>
            <div class="setmaxx-field">
              <label for="broad_genres">Broad genres</label>
              <select class="setmaxx-select setmaxx-multi-select" id="broad_genres" name="broad_genres[]" multiple size="6">
                <?php foreach ($broadGenreOptions as $genre): ?>
                  <option value="<?= e($genre) ?>" <?= in_array($genre, $filters['broad_genres'], true) ? 'selected' : '' ?>><?= e($genre) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="setmaxx-help">Hold Ctrl or Cmd to choose more than one.</div>
            </div>
            <div class="setmaxx-field">
              <label for="source_genres">Source genres</label>
              <select class="setmaxx-select setmaxx-multi-select" id="source_genres" name="source_genres[]" multiple size="6">
                <?php foreach ($sourceGenreOptions as $genre): ?>
                  <option value="<?= e($genre) ?>" <?= in_array($genre, $filters['source_genres'], true) ? 'selected' : '' ?>><?= e($genre) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="setmaxx-help">Use this when the imported genre is helpful.</div>
            </div>
            <div class="setmaxx-field">
              <label for="vocal_difficulty">Vocal difficulty</label>
              <select class="setmaxx-select" id="vocal_difficulty" name="vocal_difficulty">
                <option value="any" <?= $filters['vocal_difficulty'] === 'any' ? 'selected' : '' ?>>Any</option>
                <option value="easy" <?= $filters['vocal_difficulty'] === 'easy' ? 'selected' : '' ?>>Easy</option>
                <option value="medium" <?= $filters['vocal_difficulty'] === 'medium' ? 'selected' : '' ?>>Medium</option>
                <option value="hard" <?= $filters['vocal_difficulty'] === 'hard' ? 'selected' : '' ?>>Hard</option>
              </select>
            </div>
            <div class="setmaxx-field">
              <label for="prerecorded">Prerecorded tracks</label>
              <select class="setmaxx-select" id="prerecorded" name="prerecorded">
                <option value="any" <?= $filters['prerecorded'] === 'any' ? 'selected' : '' ?>>Any</option>
                <option value="yes" <?= $filters['prerecorded'] === 'yes' ? 'selected' : '' ?>>Prerecorded only</option>
                <option value="no" <?= $filters['prerecorded'] === 'no' ? 'selected' : '' ?>>Live-only songs</option>
              </select>
            </div>
            <div class="setmaxx-field">
              <label for="tempo_order">Tempo pacing</label>
              <select class="setmaxx-select" id="tempo_order" name="tempo_order">
                <option value="none" <?= $filters['tempo_order'] === 'none' ? 'selected' : '' ?>>Natural mix</option>
                <option value="tempo_asc" <?= $filters['tempo_order'] === 'tempo_asc' ? 'selected' : '' ?>>Slow to fast</option>
                <option value="tempo_desc" <?= $filters['tempo_order'] === 'tempo_desc' ? 'selected' : '' ?>>Fast to slow</option>
                <option value="alternate_fast_slow" <?= $filters['tempo_order'] === 'alternate_fast_slow' ? 'selected' : '' ?>>Alternate fast / slow</option>
                <option value="two_fast_one_slow" <?= $filters['tempo_order'] === 'two_fast_one_slow' ? 'selected' : '' ?>>Two fast, one slow</option>
                <option value="two_slow_one_fast" <?= $filters['tempo_order'] === 'two_slow_one_fast' ? 'selected' : '' ?>>Two slow, one fast</option>
              </select>
            </div>
          </div>
          <div class="setmaxx-note setmaxx-help">Songs without saved lengths are planned as 4 minutes and labeled as assumed in the generated setlist.</div>
          <div class="setmaxx-actions">
            <button class="btn btn-primary" type="submit" <?= $isProUser ? '' : 'disabled' ?>>Generate setlist</button>
            <a class="btn btn-outline" href="<?= e(base_url('/setmaxx/songs.php')) ?>">Edit Songs</a>
          </div>
        </form>
      </div>
      <div class="setmaxx-card">
        <h2 style="margin-top:0;">How it chooses songs</h2>
        <p class="setmaxx-help">Songs are placed until each set is near the target time, then ordered by your tempo pacing choice. Missing song lengths count as 4 minutes. Songs without BPM stay after the tempo-shaped portion.</p>
        <?php if (is_post()): ?>
          <div class="setmaxx-list">
            <div class="setmaxx-row"><strong><?= (int)$matchingCount ?></strong><span class="setmaxx-meta">matching songs</span></div>
            <div class="setmaxx-row"><strong><?= count($unknownLengthSongs) ?></strong><span class="setmaxx-meta">songs using assumed 4 min length</span></div>
            <div class="setmaxx-row"><strong><?= count($unusedSongs) ?></strong><span class="setmaxx-meta">unused timed songs</span></div>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <?php if ($generatedSets): ?>
      <section class="setmaxx-card setmaxx-printable-setlist" style="margin-top:1rem;">
        <div class="setmaxx-catalog-toolbar">
          <div>
            <h2 style="margin:0;">Generated setlist</h2>
            <div class="setmaxx-help"><?= (int)$filters['set_count'] ?> sets at about <?= (int)$filters['set_minutes'] ?> minutes each</div>
          </div>
          <button class="btn btn-outline" type="button" onclick="window.print()">Print</button>
        </div>
        <div class="setmaxx-generated-grid">
          <?php foreach ($generatedSets as $setNumber => $set): ?>
            <div class="setmaxx-set-card">
              <div class="setmaxx-set-head">
                <h3>Set <?= (int)$setNumber ?></h3>
                <span class="setmaxx-pill"><?= e(setmaxx_setlist_total_time((int)$set['seconds'])) ?></span>
              </div>
              <ol class="setmaxx-set-songs">
                <?php foreach ($set['songs'] as $song): ?>
                  <li>
                    <div>
                      <strong><?= e($song['title']) ?></strong>
                      <span><?= e((string)($song['artist'] ?: 'Artist not set')) ?></span>
                    </div>
                    <div class="setmaxx-song-badges">
                      <?php if (!empty($song['opening_song'])): ?><span>Opener</span><?php endif; ?>
                      <?php if (!empty($song['song_key'])): ?><span><?= e((string)$song['song_key']) ?></span><?php endif; ?>
                      <?php if (!empty($song['tempo_bpm'])): ?><span><?= (int)$song['tempo_bpm'] ?> bpm</span><?php endif; ?>
                      <span><?= e(setmaxx_setlist_display_length($song)) ?></span>
                      <a href="<?= e(setmaxx_lyrics_url((string)$song['title'], (string)$song['artist'])) ?>" target="_blank" rel="noopener">Lyrics</a>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ol>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($unusedSongs || $unknownLengthSongs): ?>
      <section class="setmaxx-grid" style="margin-top:1rem;">
        <?php if ($unusedSongs): ?>
          <div class="setmaxx-card">
            <h2 style="margin-top:0;">Unused timed songs</h2>
            <div class="setmaxx-list">
              <?php foreach ($unusedSongs as $song): ?>
                <div class="setmaxx-row"><div><strong><?= e($song['title']) ?></strong><div class="setmaxx-meta"><?= e((string)($song['artist'] ?: 'Artist not set')) ?></div></div><span class="setmaxx-meta"><?= e(setmaxx_setlist_seconds((int)$song['track_length_seconds'])) ?></span></div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
        <?php if ($unknownLengthSongs): ?>
          <div class="setmaxx-card">
            <h2 style="margin-top:0;">Assumed 4 min lengths</h2>
            <div class="setmaxx-list">
              <?php foreach ($unknownLengthSongs as $song): ?>
                <div class="setmaxx-row"><div><strong><?= e($song['title']) ?></strong><div class="setmaxx-meta"><?= e((string)($song['artist'] ?: 'Artist not set')) ?></div></div><span class="setmaxx-meta">4:00 assumed</span></div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>
  <?php endif; ?>
</main>
<style>
  .setmaxx-catalog-toolbar { display:flex; justify-content:space-between; gap:1rem; align-items:flex-start; flex-wrap:wrap; margin-bottom:1rem; }
  .setmaxx-generated-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:1rem; }
  .setmaxx-set-card { border:1px solid rgba(255,255,255,.08); border-radius:16px; background:rgba(255,255,255,.035); padding:1rem; }
  .setmaxx-set-head { display:flex; justify-content:space-between; gap:1rem; align-items:center; margin-bottom:.8rem; }
  .setmaxx-set-head h3 { margin:0; }
  .setmaxx-set-songs { display:grid; gap:.7rem; margin:0; padding-left:1.2rem; }
  .setmaxx-set-songs li { padding-bottom:.65rem; border-bottom:1px solid rgba(255,255,255,.07); }
  .setmaxx-set-songs li:last-child { border-bottom:0; padding-bottom:0; }
  .setmaxx-set-songs span { display:block; color:rgba(255,255,255,.72); font-size:.9rem; }
  .setmaxx-multi-select { min-height:132px; }
  .setmaxx-song-badges { display:flex; gap:.35rem; flex-wrap:wrap; margin-top:.3rem; }
  .setmaxx-song-badges span { display:inline-flex; padding:.16rem .48rem; border-radius:999px; background:rgba(255,255,255,.07); color:rgba(255,255,255,.82); font-size:.78rem; }
  .setmaxx-song-badges a { display:inline-flex; padding:.16rem .48rem; border-radius:999px; background:rgba(140,107,255,.16); color:#efe7ff; font-size:.78rem; text-decoration:none; }
  .setmaxx-song-badges a:hover { text-decoration:underline; }
  @media print {
    .setmaxx-site-header, .setmaxx-site-footer, .setmaxx-grid, .setmaxx-actions, .btn { display:none !important; }
    body { background:#fff !important; color:#111 !important; }
    .setmaxx-card, .setmaxx-set-card { box-shadow:none !important; border-color:#ddd !important; background:#fff !important; color:#111 !important; }
    .setmaxx-help, .setmaxx-meta, .setmaxx-set-songs span { color:#444 !important; }
  }
</style>
<?php setmaxx_page_foot(); ?>
