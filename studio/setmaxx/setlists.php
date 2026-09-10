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

function setmaxx_setlist_ensure_favorites_table(PDO $pdo): void {
    if (setmaxx_table_exists($pdo, 'setmaxx_favorite_setlists')) return;
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `setmaxx_favorite_setlists` (
          `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
          `user_id` int(10) unsigned NOT NULL,
          `name` varchar(190) NOT NULL,
          `criteria_json` longtext DEFAULT NULL,
          `sets_json` longtext NOT NULL,
          `created_at` datetime NOT NULL DEFAULT current_timestamp(),
          `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`id`),
          KEY `idx_setmaxx_favorite_setlists_user` (`user_id`,`updated_at`),
          CONSTRAINT `fk_setmaxx_favorite_setlists_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

function setmaxx_setlist_difficulty_values(string $difficulty): array {
    if ($difficulty === 'easy') return ['easy'];
    if ($difficulty === 'medium') return ['easy', 'medium'];
    if ($difficulty === 'hard') return ['easy', 'medium', 'hard'];
    return [];
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
$replacementSongOptions = [];
$favoriteSetlists = [];

if ($tablesReady) {
    setmaxx_setlist_ensure_broad_genre($pdo);
    setmaxx_setlist_ensure_favorites_table($pdo);
    $sourceGenreStmt = $pdo->prepare("SELECT DISTINCT genre FROM setmaxx_songs WHERE user_id = ? AND genre IS NOT NULL AND genre <> '' ORDER BY genre ASC");
    $sourceGenreStmt->execute([$userId]);
    $sourceGenreOptions = array_map('strval', array_column($sourceGenreStmt->fetchAll(PDO::FETCH_ASSOC), 'genre'));

    $broadGenreStmt = $pdo->prepare("SELECT DISTINCT broad_genre FROM setmaxx_songs WHERE user_id = ? AND broad_genre IS NOT NULL AND broad_genre <> '' ORDER BY broad_genre ASC");
    $broadGenreStmt->execute([$userId]);
    $broadGenreOptions = array_map('strval', array_column($broadGenreStmt->fetchAll(PDO::FETCH_ASSOC), 'broad_genre'));

    $favoriteStmt = $pdo->prepare("SELECT id, name, sets_json, updated_at FROM setmaxx_favorite_setlists WHERE user_id = ? ORDER BY updated_at DESC LIMIT 8");
    $favoriteStmt->execute([$userId]);
    $favoriteSetlists = $favoriteStmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($tablesReady && is_post()) {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    } else {
        $action = (string)($_POST['action'] ?? 'generate');
        if ($action === 'delete_favorite') {
            $favoriteId = (int)($_POST['favorite_id'] ?? 0);
            if ($favoriteId > 0) {
                $delete = $pdo->prepare("DELETE FROM setmaxx_favorite_setlists WHERE id = ? AND user_id = ?");
                $delete->execute([$favoriteId, $userId]);
                $messages[] = $delete->rowCount() > 0 ? 'Favorite setlist deleted.' : 'That favorite setlist was not found.';
                $favoriteStmt = $pdo->prepare("SELECT id, name, sets_json, updated_at FROM setmaxx_favorite_setlists WHERE user_id = ? ORDER BY updated_at DESC LIMIT 8");
                $favoriteStmt->execute([$userId]);
                $favoriteSetlists = $favoriteStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } elseif ($action === 'save_favorite') {
            if (!$isProUser) {
                $errors[] = 'Saving favorite setlists is included with Pro.';
            } else {
            $name = mb_substr(trim((string)($_POST['favorite_name'] ?? '')), 0, 190);
            if ($name === '') $name = 'Favorite setlist';
            $payloadRaw = trim((string)($_POST['favorite_payload'] ?? ''));
            $payload = json_decode($payloadRaw, true);
            if (!is_array($payload) || empty($payload['sets']) || !is_array($payload['sets'])) {
                $errors[] = 'Could not save that favorite. Generate a setlist first, then try again.';
            } else {
                $criteriaRaw = trim((string)($_POST['criteria_json'] ?? ''));
                $criteria = json_decode($criteriaRaw, true);
                $insert = $pdo->prepare("
                    INSERT INTO setmaxx_favorite_setlists (user_id, name, criteria_json, sets_json)
                    VALUES (?, ?, ?, ?)
                ");
                $insert->execute([
                    $userId,
                    $name,
                    is_array($criteria) ? json_encode($criteria) : null,
                    json_encode($payload['sets']),
                ]);
                $messages[] = 'Saved "' . $name . '" to favorite setlists.';
                $favoriteStmt = $pdo->prepare("SELECT id, name, sets_json, updated_at FROM setmaxx_favorite_setlists WHERE user_id = ? ORDER BY updated_at DESC LIMIT 8");
                $favoriteStmt->execute([$userId]);
                $favoriteSetlists = $favoriteStmt->fetchAll(PDO::FETCH_ASSOC);
            }
            }
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

        $difficultyValues = setmaxx_setlist_difficulty_values($filters['vocal_difficulty']);
        if ($difficultyValues) {
            $where[] = 'vocal_difficulty IN (' . implode(',', array_fill(0, count($difficultyValues), '?')) . ')';
            $params = array_merge($params, $difficultyValues);
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
                    vocal_difficulty, song_key, tempo_bpm, family_friendly, is_active, performance_notes
             FROM setmaxx_songs
             WHERE " . implode(' AND ', $where) . "
             ORDER BY opening_song DESC, title ASC, artist ASC"
        );
        $songStmt->execute($params);
        $songs = $songStmt->fetchAll(PDO::FETCH_ASSOC);
        $matchingCount = count($songs);
        $replacementSongOptions = $songs;

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
}

setmaxx_page_head('Set Maxx | Setlist Generator');
?>
<main class="container setmaxx-shell">
  <?php setmaxx_flash($messages, $errors); ?>

  <?php if (!$tablesReady): ?><?php setmaxx_install_notice(); ?><?php else: ?>
    <section class="setmaxx-grid">
      <div class="setmaxx-card">
        <div class="setmaxx-section-head">
          <h2>Criteria</h2>
          <button class="setmaxx-help-button" type="button" id="setmaxxCriteriaHelpBtn" aria-label="Show criteria help" aria-haspopup="dialog">?</button>
        </div>
        <form method="post" class="setmaxx-stack" action="">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="generate">
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
            <button class="btn btn-primary" type="submit">Generate setlist</button>
            <a class="btn btn-outline" href="<?= e(base_url('/setmaxx/songs.php')) ?>">Edit Songs</a>
          </div>
        </form>
      </div>
      <div class="setmaxx-card">
        <div class="setmaxx-section-head">
          <h2>Favorite setlists</h2>
        </div>
        <?php if (!$favoriteSetlists): ?>
          <p class="setmaxx-help" style="margin:0;">Saved setlists will appear here after you generate and save one.</p>
        <?php else: ?>
          <div class="setmaxx-list setmaxx-favorite-list">
            <?php foreach ($favoriteSetlists as $favorite): ?>
              <?php
                $favoriteSets = json_decode((string)$favorite['sets_json'], true);
                $favoriteSetCount = is_array($favoriteSets) ? count($favoriteSets) : 0;
                $favoriteSongCount = 0;
                if (is_array($favoriteSets)) {
                    foreach ($favoriteSets as $favoriteSet) {
                        $favoriteSongCount += is_array($favoriteSet['songs'] ?? null) ? count($favoriteSet['songs']) : 0;
                    }
                }
              ?>
              <div class="setmaxx-row setmaxx-favorite-row">
                <div>
                  <strong><?= e((string)$favorite['name']) ?></strong>
                  <div class="setmaxx-meta"><?= (int)$favoriteSetCount ?> sets &middot; <?= (int)$favoriteSongCount ?> songs &middot; <?= e((new DateTime((string)$favorite['updated_at']))->format('M j, Y')) ?></div>
                  <?php if (is_array($favoriteSets)): ?>
                    <details class="setmaxx-favorite-detail">
                      <summary>View songs</summary>
                      <?php foreach ($favoriteSets as $favoriteSet): ?>
                        <div class="setmaxx-favorite-set">
                          <strong>Set <?= (int)($favoriteSet['number'] ?? 0) ?></strong>
                          <ol>
                            <?php foreach ((array)($favoriteSet['songs'] ?? []) as $favoriteSong): ?>
                              <li>
                                <?= e((string)($favoriteSong['title'] ?? 'Untitled')) ?> <span><?= e((string)($favoriteSong['artist'] ?? 'Artist not set')) ?></span>
                                <?php if (trim((string)($favoriteSong['performanceNotes'] ?? '')) !== ''): ?>
                                  <div class="setmaxx-favorite-notes"><?= nl2br(e((string)$favoriteSong['performanceNotes'])) ?></div>
                                <?php endif; ?>
                              </li>
                            <?php endforeach; ?>
                          </ol>
                        </div>
                      <?php endforeach; ?>
                    </details>
                  <?php endif; ?>
                </div>
                <form method="post" action="" onsubmit="return confirm('Delete this saved setlist?');">
                  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="delete_favorite">
                  <input type="hidden" name="favorite_id" value="<?= (int)$favorite['id'] ?>">
                  <button class="setmaxx-icon-button" type="submit" aria-label="Delete favorite setlist">&times;</button>
                </form>
              </div>
            <?php endforeach; ?>
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
          <div class="setmaxx-actions">
            <button class="btn btn-outline" type="button" onclick="window.print()">Print</button>
          </div>
        </div>
        <form method="post" class="setmaxx-favorite-form" id="setmaxxFavoriteForm" action="">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="save_favorite">
          <input type="hidden" name="criteria_json" value="<?= e(json_encode($filters)) ?>">
          <input type="hidden" name="favorite_payload" id="setmaxxFavoritePayload" value="">
          <div class="setmaxx-save-row">
            <?php if ($isProUser): ?>
              <input class="setmaxx-input" name="favorite_name" value="<?= e('Setlist ' . date('M j, Y')) ?>" aria-label="Favorite setlist name">
              <button class="btn btn-primary" type="submit">Save Favorite</button>
            <?php else: ?>
              <div class="setmaxx-pro-save-note">
                <span class="setmaxx-pill">Pro</span>
                <span>Save favorite setlists with Pro.</span>
              </div>
              <a class="btn btn-primary" href="<?= e($upgradeUrl) ?>">Upgrade</a>
            <?php endif; ?>
          </div>
        <div class="setmaxx-generated-grid">
          <?php foreach ($generatedSets as $setNumber => $set): ?>
            <div class="setmaxx-set-card" data-setlist-card data-set-number="<?= (int)$setNumber ?>">
              <div class="setmaxx-set-head">
                <h3>Set <?= (int)$setNumber ?></h3>
                <span class="setmaxx-pill" data-set-total><?= e(setmaxx_setlist_total_time((int)$set['seconds'])) ?></span>
              </div>
              <ol class="setmaxx-set-songs">
                <?php foreach ($set['songs'] as $song): ?>
                  <li data-setlist-song>
                    <div class="setmaxx-song-editor">
                      <select class="setmaxx-select setmaxx-song-swap" aria-label="Swap song">
                        <?php foreach ($replacementSongOptions as $optionSong): ?>
                          <?php
                            $optionSeconds = setmaxx_setlist_planning_seconds($optionSong);
                            $optionArtist = (string)($optionSong['artist'] ?: 'Artist not set');
                            $optionTitle = (string)$optionSong['title'];
                            $optionLyrics = setmaxx_lyrics_url($optionTitle, $optionArtist);
                          ?>
                          <option
                            value="<?= (int)$optionSong['id'] ?>"
                            data-title="<?= e($optionTitle) ?>"
                            data-artist="<?= e($optionArtist) ?>"
                            data-seconds="<?= (int)$optionSeconds ?>"
                            data-length="<?= e(setmaxx_setlist_display_length($optionSong)) ?>"
                            data-opener="<?= !empty($optionSong['opening_song']) ? '1' : '0' ?>"
                            data-song-key="<?= e((string)($optionSong['song_key'] ?? '')) ?>"
                            data-bpm="<?= (int)($optionSong['tempo_bpm'] ?? 0) ?>"
                            data-lyrics="<?= e($optionLyrics) ?>"
                            data-performance-notes="<?= e((string)($optionSong['performance_notes'] ?? '')) ?>"
                            <?= (int)$optionSong['id'] === (int)$song['id'] ? 'selected' : '' ?>
                          ><?= e($optionTitle . ' - ' . $optionArtist) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="setmaxx-song-display">
                      <strong data-song-title><?= e($song['title']) ?></strong>
                      <span data-song-artist><?= e((string)($song['artist'] ?: 'Artist not set')) ?></span>
                    </div>
                    <div class="setmaxx-song-badges" data-song-badges>
                      <?php if (!empty($song['opening_song'])): ?><span>Opener</span><?php endif; ?>
                      <?php if (!empty($song['song_key'])): ?><span><?= e((string)$song['song_key']) ?></span><?php endif; ?>
                      <?php if (!empty($song['tempo_bpm'])): ?><span><?= (int)$song['tempo_bpm'] ?> bpm</span><?php endif; ?>
                      <span><?= e(setmaxx_setlist_display_length($song)) ?></span>
                      <a href="<?= e(setmaxx_lyrics_url((string)$song['title'], (string)$song['artist'])) ?>" target="_blank" rel="noopener">Lyrics</a>
                    </div>
                    <div class="setmaxx-performance-notes" data-performance-notes><?= trim((string)($song['performance_notes'] ?? '')) !== '' ? nl2br(e((string)$song['performance_notes'])) : '' ?></div>
                  </li>
                <?php endforeach; ?>
              </ol>
            </div>
          <?php endforeach; ?>
        </div>
        </form>
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
    <dialog class="setmaxx-dialog" id="setmaxxCriteriaHelpDialog" aria-labelledby="setmaxxCriteriaHelpTitle">
      <div class="setmaxx-dialog-inner">
        <div class="setmaxx-dialog-head">
          <div>
            <div class="setmaxx-pill">Setlist help</div>
            <h2 class="setmaxx-dialog-title" id="setmaxxCriteriaHelpTitle">Using setlist criteria</h2>
          </div>
          <button class="setmaxx-dialog-close" type="button" id="setmaxxCriteriaHelpClose" aria-label="Close">&times;</button>
        </div>
        <ul class="setmaxx-format-list">
          <li>Year from and year to limit songs by saved release year. Leave them blank to use all years.</li>
          <li>Active songs controls whether inactive catalog songs are allowed into the generated set.</li>
          <li>Family friendly uses the family-friendly flag from the song catalog.</li>
          <li>Number of sets and minutes per set define the target show shape.</li>
          <li>Broad genres are your cleaned-up categories. Source genres are the imported or lookup genres.</li>
          <li>Vocal difficulty is a threshold: hard allows hard, medium, and easy; medium allows medium and easy; easy only allows easy.</li>
          <li>Tempo pacing changes the order inside each generated set when songs have BPM saved.</li>
        </ul>
      </div>
    </dialog>
    <dialog class="setmaxx-dialog" id="setmaxxChooserHelpDialog" aria-labelledby="setmaxxChooserHelpTitle">
      <div class="setmaxx-dialog-inner">
        <div class="setmaxx-dialog-head">
          <div>
            <div class="setmaxx-pill">Generator help</div>
            <h2 class="setmaxx-dialog-title" id="setmaxxChooserHelpTitle">How songs are chosen</h2>
          </div>
          <button class="setmaxx-dialog-close" type="button" id="setmaxxChooserHelpClose" aria-label="Close">&times;</button>
        </div>
        <ul class="setmaxx-format-list">
          <li>The generator first filters your catalog using the criteria on the left.</li>
          <li>Songs marked as openers are considered first, then the remaining matching songs are mixed in.</li>
          <li>Each song is placed into the set where it gets closest to the target time without already being over.</li>
          <li>Songs with no saved length are planned as 4 minutes and are listed as assumed after generation.</li>
          <li>After songs are placed, tempo pacing reorders each set when BPM is available.</li>
          <li>Songs without BPM stay after the tempo-shaped part of the set.</li>
          <li>Treat the result as a strong starting draft, then adjust the final order for the actual room.</li>
        </ul>
      </div>
    </dialog>
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
  .setmaxx-song-display { display:none; }
  .setmaxx-song-editor { margin-bottom:.45rem; }
  .setmaxx-song-editor .setmaxx-select { padding:.52rem .65rem; border-radius:10px; font-size:.88rem; }
  .setmaxx-save-row { display:grid; grid-template-columns:minmax(220px, 1fr) auto; gap:.75rem; align-items:center; margin-bottom:1rem; }
  .setmaxx-pro-save-note { display:flex; align-items:center; gap:.65rem; min-height:44px; color:rgba(255,255,255,.78); }
  .setmaxx-favorite-row { display:grid; grid-template-columns:minmax(0, 1fr) auto; align-items:start; }
  .setmaxx-icon-button { width:34px; height:34px; display:inline-flex; align-items:center; justify-content:center; border-radius:999px; border:1px solid rgba(255,255,255,.14); background:rgba(255,255,255,.05); color:#fff; cursor:pointer; font-size:1.25rem; line-height:1; }
  .setmaxx-icon-button:hover, .setmaxx-icon-button:focus-visible { border-color:rgba(255,130,130,.55); background:rgba(255,130,130,.16); outline:none; }
  .setmaxx-favorite-detail { margin-top:.5rem; }
  .setmaxx-favorite-detail summary { cursor:pointer; color:#efe7ff; font-weight:600; }
  .setmaxx-favorite-set { margin-top:.55rem; }
  .setmaxx-favorite-set ol { margin:.25rem 0 0; padding-left:1.25rem; color:rgba(255,255,255,.82); }
  .setmaxx-favorite-set li span { color:rgba(255,255,255,.58); }
  .setmaxx-favorite-notes { margin:.18rem 0 .45rem; color:#efe7ff; font-size:.86rem; white-space:pre-wrap; }
  .setmaxx-multi-select { min-height:132px; }
  .setmaxx-song-badges { display:flex; gap:.35rem; flex-wrap:wrap; margin-top:.3rem; }
  .setmaxx-song-badges span { display:inline-flex; padding:.16rem .48rem; border-radius:999px; background:rgba(255,255,255,.07); color:rgba(255,255,255,.82); font-size:.78rem; }
  .setmaxx-song-badges a { display:inline-flex; padding:.16rem .48rem; border-radius:999px; background:rgba(140,107,255,.16); color:#efe7ff; font-size:.78rem; text-decoration:none; }
  .setmaxx-song-badges a:hover { text-decoration:underline; }
  .setmaxx-performance-notes { margin-top:.42rem; color:#efe7ff; font-size:.96rem; line-height:1.45; white-space:pre-wrap; }
  .setmaxx-performance-notes:empty { display:none; }
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
  @media print {
    @page { margin:.45in; }
    .setmaxx-site-header, .setmaxx-site-footer, .setmaxx-grid, .setmaxx-actions, .btn, .setmaxx-song-editor, .setmaxx-save-row, .setmaxx-song-badges, .setmaxx-printable-setlist .setmaxx-help, .setmaxx-printable-setlist .setmaxx-pill, .setmaxx-card:not(.setmaxx-printable-setlist) { display:none !important; }
    body { background:#fff !important; color:#111 !important; }
    .setmaxx-shell { padding:0 !important; }
    .setmaxx-card, .setmaxx-set-card { box-shadow:none !important; border-color:#ddd !important; background:#fff !important; color:#111 !important; padding:0 !important; }
    .setmaxx-printable-setlist { display:block !important; border:0 !important; }
    .setmaxx-catalog-toolbar { margin:0 0 .18in !important; display:block !important; }
    .setmaxx-catalog-toolbar h2 { font-size:16pt !important; margin:0 !important; }
    .setmaxx-generated-grid { grid-template-columns:repeat(3, 1fr) !important; gap:.18in !important; align-items:start !important; }
    .setmaxx-set-card { border:1px solid #ddd !important; border-radius:0 !important; padding:.12in !important; break-inside:avoid !important; }
    .setmaxx-set-head { display:block !important; margin:0 0 .08in !important; }
    .setmaxx-set-head h3 { font-size:12pt !important; margin:0 !important; }
    .setmaxx-set-songs { gap:0 !important; padding-left:.18in !important; font-size:9pt !important; line-height:1.18 !important; }
    .setmaxx-set-songs li { border:0 !important; padding:0 0 .035in !important; }
    .setmaxx-set-songs strong { font-weight:500 !important; }
    .setmaxx-song-display { display:block !important; }
    .setmaxx-set-songs span { display:none !important; }
    .setmaxx-performance-notes { display:block !important; color:#333 !important; font-size:8pt !important; line-height:1.15 !important; margin:.02in 0 .04in !important; white-space:pre-wrap !important; }
  }
  @media (max-width: 640px) { .setmaxx-save-row, .setmaxx-favorite-row { grid-template-columns:1fr; } }
</style>
<script>
(function() {
  function selectedSong(select) {
    const option = select.options[select.selectedIndex];
    return {
      id: parseInt(option.value || '0', 10),
      title: option.getAttribute('data-title') || '',
      artist: option.getAttribute('data-artist') || 'Artist not set',
      seconds: parseInt(option.getAttribute('data-seconds') || '240', 10),
      length: option.getAttribute('data-length') || '4:00 assumed',
      opener: option.getAttribute('data-opener') === '1',
      songKey: option.getAttribute('data-song-key') || '',
      bpm: parseInt(option.getAttribute('data-bpm') || '0', 10),
      lyrics: option.getAttribute('data-lyrics') || '#',
      performanceNotes: option.getAttribute('data-performance-notes') || ''
    };
  }

  function totalLabel(seconds) {
    return Math.floor(seconds / 60) + ' min';
  }

  function renderBadges(container, song) {
    if (!container) return;
    container.innerHTML = '';
    const badges = [];
    if (song.opener) badges.push({ text: 'Opener' });
    if (song.songKey) badges.push({ text: song.songKey });
    if (song.bpm > 0) badges.push({ text: song.bpm + ' bpm' });
    badges.push({ text: song.length });
    badges.forEach(function(badge) {
      const span = document.createElement('span');
      span.textContent = badge.text;
      container.appendChild(span);
    });
    const link = document.createElement('a');
    link.href = song.lyrics;
    link.target = '_blank';
    link.rel = 'noopener';
    link.textContent = 'Lyrics';
    container.appendChild(link);
    const row = container.closest('[data-setlist-song]');
    const notes = row ? row.querySelector('[data-performance-notes]') : null;
    if (notes) notes.textContent = song.performanceNotes;
  }

  function refreshSetTotals() {
    document.querySelectorAll('[data-setlist-card]').forEach(function(card) {
      let seconds = 0;
      card.querySelectorAll('.setmaxx-song-swap').forEach(function(select) {
        seconds += selectedSong(select).seconds;
      });
      const total = card.querySelector('[data-set-total]');
      if (total) total.textContent = totalLabel(seconds);
    });
  }

  function refreshFavoritePayload() {
    const payload = document.getElementById('setmaxxFavoritePayload');
    if (!payload) return;
    const sets = [];
    document.querySelectorAll('[data-setlist-card]').forEach(function(card) {
      const songs = [];
      card.querySelectorAll('.setmaxx-song-swap').forEach(function(select) {
        songs.push(selectedSong(select));
      });
      sets.push({
        number: parseInt(card.getAttribute('data-set-number') || '0', 10),
        songs: songs
      });
    });
    payload.value = JSON.stringify({ sets: sets });
  }

  document.addEventListener('change', function(event) {
    if (!event.target.matches('.setmaxx-song-swap')) return;
    const select = event.target;
    const song = selectedSong(select);
    const row = select.closest('[data-setlist-song]');
    if (row) {
      const title = row.querySelector('[data-song-title]');
      const artist = row.querySelector('[data-song-artist]');
      if (title) title.textContent = song.title;
      if (artist) artist.textContent = song.artist;
      renderBadges(row.querySelector('[data-song-badges]'), song);
    }
    refreshSetTotals();
    refreshFavoritePayload();
  });

  const favoriteForm = document.getElementById('setmaxxFavoriteForm');
  if (favoriteForm) {
    refreshFavoritePayload();
    favoriteForm.addEventListener('submit', refreshFavoritePayload);
  }

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

  setupDialog('setmaxxCriteriaHelpBtn', 'setmaxxCriteriaHelpDialog', 'setmaxxCriteriaHelpClose');
  setupDialog('setmaxxChooserHelpBtn', 'setmaxxChooserHelpDialog', 'setmaxxChooserHelpClose');
})();
</script>
<?php setmaxx_page_foot(); ?>
