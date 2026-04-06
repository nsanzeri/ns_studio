<?php
$pageTitle = 'Wedding Outline | Nick Sanzeri';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="robots" content="noindex,nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/favicons/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/favicons/favicon-16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/favicons/favicon-180.png">

<style>
    .wedding-form-wrap {
        max-width: 980px;
        margin: 0 auto;
    }

    .wedding-section {
        margin-bottom: 2.5rem;
        padding: 1.5rem;
        border: 1px solid rgba(255,255,255,.08);
        border-radius: 18px;
        background: rgba(255,255,255,.04);
        box-shadow: 0 10px 30px rgba(0,0,0,.18);
        backdrop-filter: blur(4px);
    }

    .wedding-section h2,
    .wedding-section h3 {
        color: #f3efe7;
    }

    .section-note,
    .wedding-section .muted {
        color: rgba(255,255,255,.72);
    }

    .form-grid-2,
    .form-grid-3 {
        display: grid;
        gap: 1rem;
    }

    .form-grid-2 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .form-grid-3 {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .radio-group {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
        margin-top: .35rem;
    }

    .radio-group label {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        font-weight: 400;
        color: rgba(255,255,255,.9);
    }

    .party-row {
        margin-top: 1rem;
        padding: 1rem;
        border: 1px solid rgba(255,255,255,.08);
        border-radius: 14px;
        background: rgba(255,255,255,.03);
    }

    .party-row h3 {
        margin-bottom: .85rem;
        font-size: 1rem;
    }

    .form-field textarea {
        min-height: 110px;
    }

    .form-actions-inline {
        display: flex;
        gap: .75rem;
        flex-wrap: wrap;
        margin-top: 1rem;
    }

    .btn-outline-light {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: .9rem 1.2rem;
        border-radius: 999px;
        border: 1px solid rgba(255,255,255,.18);
        background: transparent;
        color: #fff;
        text-decoration: none;
        cursor: pointer;
        transition: .2s ease;
    }

    .btn-outline-light:hover {
        background: rgba(255,255,255,.08);
        border-color: rgba(255,255,255,.28);
    }

    .remove-row-btn {
        margin-top: .75rem;
    }

    @media (max-width: 768px) {
        .form-grid-2,
        .form-grid-3 {
            grid-template-columns: 1fr;
        }
    }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('bridal-party-rows');
    const addBtn = document.getElementById('add-bridal-party-row');

    if (!container || !addBtn) return;

    function updateRowTitles() {
        const rows = container.querySelectorAll('.bridal-party-row');
        rows.forEach((row, index) => {
            const number = index + 1;
            row.dataset.index = number;

            const title = row.querySelector('h3');
            if (title) {
                title.textContent = 'Bridal Party Pair ' + number;
            }

            row.querySelectorAll('label[for], input[id][name]').forEach((el) => {
                if (el.tagName.toLowerCase() === 'label') {
                    const currentFor = el.getAttribute('for');
                    if (currentFor) {
                        el.setAttribute('for', currentFor.replace(/\d+/, number));
                    }
                    el.textContent = el.textContent.replace(/\d+/, number);
                } else {
                    const currentId = el.getAttribute('id');
                    const currentName = el.getAttribute('name');
                    if (currentId) el.setAttribute('id', currentId.replace(/\d+/, number));
                    if (currentName) el.setAttribute('name', currentName.replace(/\d+/, number));
                }
            });
        });
    }

    function createRemoveButton() {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn-outline-light remove-row-btn';
        btn.textContent = 'Remove This Pair';

        btn.addEventListener('click', function () {
            const rows = container.querySelectorAll('.bridal-party-row');
            if (rows.length <= 1) return;
            btn.closest('.bridal-party-row').remove();
            updateRowTitles();
        });

        return btn;
    }

    addBtn.addEventListener('click', function () {
        const rows = container.querySelectorAll('.bridal-party-row');
        const newIndex = rows.length + 1;
        const clone = rows[rows.length - 1].cloneNode(true);

        clone.querySelectorAll('input').forEach((input) => {
            input.value = '';
        });

        clone.querySelectorAll('.remove-row-btn').forEach((btn) => btn.remove());
        clone.appendChild(createRemoveButton());

        container.appendChild(clone);
        updateRowTitles();
    });

    updateRowTitles();
});
</script>
</head>
<body id="top">
<?php include __DIR__ . '/includes/header.php'; ?>

<main>
    <section class="page-hero">
        <div class="container wedding-form-wrap">
            <p class="eyebrow">Wedding Planning</p>
            <h1>Wedding Reception Outline</h1>
            <p class="page-intro">
                Please feel free to leave fields blank that are not necessary for your particular wedding or any info already communicated.
                This helps us design a night that feels effortless, fun, and completely you.
            </p>
        </div>
    </section>

    <section class="section">
        <div class="container wedding-form-wrap">
            <form class="form" action="wedding_outline_submit.php" method="POST">
                <input type="text" name="website" style="display:none;">

                <div class="wedding-section">
                    <h2>General Info</h2>
                    <div class="form-grid-3">
                        <div class="form-field">
                            <label for="reception_date">Reception Date</label>
                            <input type="date" id="reception_date" name="reception_date">
                        </div>
                        <div class="form-field">
                            <label for="start_time">Start Time</label>
                            <input type="text" id="start_time" name="start_time" placeholder="e.g. 5:00 PM">
                        </div>
                        <div class="form-field">
                            <label for="end_time">End Time</label>
                            <input type="text" id="end_time" name="end_time" placeholder="e.g. 10:00 PM">
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-field">
                            <label for="reception_location_name">Reception Location Name</label>
                            <input type="text" id="reception_location_name" name="reception_location_name">
                        </div>
                        <div class="form-field">
                            <label for="phone_number">Phone Number</label>
                            <input type="text" id="phone_number" name="phone_number">
                        </div>
                    </div>

                    <div class="form-field">
                        <label for="address_city_state">Address (City State)</label>
                        <input type="text" id="address_city_state" name="address_city_state">
                    </div>

                    <div class="form-field">
                        <label for="specific_area">Specific room / hall / pavilion area if applicable</label>
                        <input type="text" id="specific_area" name="specific_area">
                    </div>

                    <div class="form-field">
                        <label for="coordinator_name">Banquet Manager / Function Coordinator / Contact Person Name</label>
                        <input type="text" id="coordinator_name" name="coordinator_name">
                    </div>
                </div>

                <div class="wedding-section">
                    <h2>Names</h2>

                    <div class="form-grid-2">
                        <div class="form-field">
                            <label for="bride_name">Bride’s Name</label>
                            <input type="text" id="bride_name" name="bride_name">
                        </div>
                        <div class="form-field">
                            <label for="bride_pronounced">Pronounced</label>
                            <input type="text" id="bride_pronounced" name="bride_pronounced">
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-field">
                            <label for="groom_name">Groom’s Name</label>
                            <input type="text" id="groom_name" name="groom_name">
                        </div>
                        <div class="form-field">
                            <label for="groom_pronounced">Pronounced</label>
                            <input type="text" id="groom_pronounced" name="groom_pronounced">
                        </div>
                    </div>

                    <h3 style="margin-top:1.5rem;">Bride’s Parents</h3>
                    <div class="form-grid-2">
                        <div class="form-field">
                            <label for="bride_father">Father</label>
                            <input type="text" id="bride_father" name="bride_father">
                        </div>
                        <div class="form-field">
                            <label for="bride_father_pronounced">Pronounced</label>
                            <input type="text" id="bride_father_pronounced" name="bride_father_pronounced">
                        </div>
                    </div>
                    <div class="form-grid-2">
                        <div class="form-field">
                            <label for="bride_mother">Mother</label>
                            <input type="text" id="bride_mother" name="bride_mother">
                        </div>
                        <div class="form-field">
                            <label for="bride_mother_pronounced">Pronounced</label>
                            <input type="text" id="bride_mother_pronounced" name="bride_mother_pronounced">
                        </div>
                    </div>

                    <h3 style="margin-top:1.5rem;">Groom’s Parents</h3>
                    <div class="form-grid-2">
                        <div class="form-field">
                            <label for="groom_father">Father</label>
                            <input type="text" id="groom_father" name="groom_father">
                        </div>
                        <div class="form-field">
                            <label for="groom_father_pronounced">Pronounced</label>
                            <input type="text" id="groom_father_pronounced" name="groom_father_pronounced">
                        </div>
                    </div>
                    <div class="form-grid-2">
                        <div class="form-field">
                            <label for="groom_mother">Mother</label>
                            <input type="text" id="groom_mother" name="groom_mother">
                        </div>
                        <div class="form-field">
                            <label for="groom_mother_pronounced">Pronounced</label>
                            <input type="text" id="groom_mother_pronounced" name="groom_mother_pronounced">
                        </div>
                    </div>
                </div>

                <div class="wedding-section">
                    <h2>Reception</h2>
                    <div class="form-grid-3">
                        <div class="form-field">
                            <label for="guests_arrival_time">Guests Arrival Time</label>
                            <input type="text" id="guests_arrival_time" name="guests_arrival_time">
                        </div>
                        <div class="form-field">
                            <label for="cocktail_start_time">Cocktail Start Time</label>
                            <input type="text" id="cocktail_start_time" name="cocktail_start_time">
                        </div>
                        <div class="form-field">
                            <label for="dinner_start_time">Dinner Start Time</label>
                            <input type="text" id="dinner_start_time" name="dinner_start_time">
                        </div>
                    </div>

                    <div class="form-field">
                        <label>Is Nick playing a 40 minute live set during dinner in addition to pre-recorded music?</label>
                        <div class="radio-group">
                            <label><input type="radio" name="dinner_live_set" value="Yes"> Yes</label>
                            <label><input type="radio" name="dinner_live_set" value="No"> No</label>
                        </div>
                    </div>
                </div>

<div class="wedding-section">
    <h2>Bridal Party Introduction</h2>
    <p class="section-note">This will be pre-recorded for the best presentation, so it needs to be thought out ahead of time.</p>

    <div class="form-field">
        <label>Is Nick introducing the Bridal Party?</label>
        <div class="radio-group">
            <label><input type="radio" name="introducing_bridal_party" value="Yes"> Yes</label>
            <label><input type="radio" name="introducing_bridal_party" value="No"> No</label>
        </div>
    </div>

    <div class="form-field">
        <label>Same song for entire Bridal Party?</label>
        <div class="radio-group">
            <label><input type="radio" name="same_song_entire_party" value="Yes"> Yes</label>
            <label><input type="radio" name="same_song_entire_party" value="No"> No</label>
        </div>
    </div>

    <div class="form-grid-2">
        <div class="form-field">
            <label for="entire_party_song">Song</label>
            <input type="text" id="entire_party_song" name="entire_party_song">
        </div>
        <div class="form-field">
            <label for="entire_party_artist">Artist</label>
            <input type="text" id="entire_party_artist" name="entire_party_artist">
        </div>
    </div>

    <div id="bridal-party-rows">
        <div class="party-row bridal-party-row" data-index="1">
            <h3>Bridal Party Pair 1</h3>

            <div class="form-grid-2">
                <div class="form-field">
                    <label for="bridesmaid_1">Bridesmaid 1</label>
                    <input type="text" id="bridesmaid_1" name="bridesmaid_1">
                </div>
                <div class="form-field">
                    <label for="bridesmaid_1_pronounced">Pronounced</label>
                    <input type="text" id="bridesmaid_1_pronounced" name="bridesmaid_1_pronounced">
                </div>
            </div>

            <div class="form-grid-2">
                <div class="form-field">
                    <label for="groomsman_1">Groomsman 1</label>
                    <input type="text" id="groomsman_1" name="groomsman_1">
                </div>
                <div class="form-field">
                    <label for="groomsman_1_pronounced">Pronounced</label>
                    <input type="text" id="groomsman_1_pronounced" name="groomsman_1_pronounced">
                </div>
            </div>

            <div class="form-grid-2">
                <div class="form-field">
                    <label for="pair_1_song">Introduced to Song</label>
                    <input type="text" id="pair_1_song" name="pair_1_song">
                </div>
                <div class="form-field">
                    <label for="pair_1_artist">Artist</label>
                    <input type="text" id="pair_1_artist" name="pair_1_artist">
                </div>
            </div>
        </div>
    </div>

    <div class="form-actions-inline">
        <button type="button" class="btn-outline-light" id="add-bridal-party-row">+ Add Bridal Party Pair</button>
    </div>

    <p class="section-note">(Please ask your parents if they would like to be included - most usually do!)</p>

    <div class="party-row">
        <h3>Father of the Groom / Mother of the Groom</h3>
        <div class="form-grid-2">
            <div class="form-field">
                <label for="fog_name">Father of the Groom</label>
                <input type="text" id="fog_name" name="fog_name">
            </div>
            <div class="form-field">
                <label for="fog_pronounced">Pronounced</label>
                <input type="text" id="fog_pronounced" name="fog_pronounced">
            </div>
        </div>
        <div class="form-grid-2">
            <div class="form-field">
                <label for="mog_name">Mother of the Groom</label>
                <input type="text" id="mog_name" name="mog_name">
            </div>
            <div class="form-field">
                <label for="mog_pronounced">Pronounced</label>
                <input type="text" id="mog_pronounced" name="mog_pronounced">
            </div>
        </div>
        <div class="form-grid-2">
            <div class="form-field">
                <label for="groom_parents_song">Introduced to Song</label>
                <input type="text" id="groom_parents_song" name="groom_parents_song">
            </div>
            <div class="form-field">
                <label for="groom_parents_artist">Artist</label>
                <input type="text" id="groom_parents_artist" name="groom_parents_artist">
            </div>
        </div>
    </div>

    <div class="party-row">
        <h3>Father of the Bride / Mother of the Bride</h3>
        <div class="form-grid-2">
            <div class="form-field">
                <label for="fob_name">Father of the Bride</label>
                <input type="text" id="fob_name" name="fob_name">
            </div>
            <div class="form-field">
                <label for="fob_pronounced">Pronounced</label>
                <input type="text" id="fob_pronounced" name="fob_pronounced">
            </div>
        </div>
        <div class="form-grid-2">
            <div class="form-field">
                <label for="mob_name">Mother of the Bride</label>
                <input type="text" id="mob_name" name="mob_name">
            </div>
            <div class="form-field">
                <label for="mob_pronounced">Pronounced</label>
                <input type="text" id="mob_pronounced" name="mob_pronounced">
            </div>
        </div>
        <div class="form-grid-2">
            <div class="form-field">
                <label for="bride_parents_song">Introduced to Song</label>
                <input type="text" id="bride_parents_song" name="bride_parents_song">
            </div>
            <div class="form-field">
                <label for="bride_parents_artist">Artist</label>
                <input type="text" id="bride_parents_artist" name="bride_parents_artist">
            </div>
        </div>
    </div>

    <div class="party-row">
        <h3>Bride and Groom</h3>
        <div class="form-field">
            <label for="announcement_wording">How would you like to be announced?</label>
            <textarea id="announcement_wording" name="announcement_wording" placeholder='Example: "For the first time, Mr. and Mrs. Smith" or custom wording here'></textarea>
        </div>
        <div class="form-grid-2">
            <div class="form-field">
                <label for="couple_intro_song">Introduced to Song</label>
                <input type="text" id="couple_intro_song" name="couple_intro_song">
            </div>
            <div class="form-field">
                <label for="couple_intro_artist">Artist</label>
                <input type="text" id="couple_intro_artist" name="couple_intro_artist">
            </div>
        </div>
    </div>
</div>                <div class="wedding-section">
                    <h2>First Dance / Other Traditional Dances</h2>
                    <p class="section-note">For best impact and minimal distraction will use recorded music.</p>

                    <?php
                    $danceRows = [
                        'bride_groom'   => 'Bride and Groom',
                        'father_daughter' => 'Father / Daughter',
                        'mother_son'    => 'Mother / Son',
                        'bridal_party'  => 'Bridal Party',
                        'other_special' => 'Other Special Dance',
                    ];
                    foreach ($danceRows as $key => $label):
                    ?>
                    <div class="party-row">
                        <h3><?= htmlspecialchars($label) ?></h3>
                        <div class="form-field">
                            <label>Include this?</label>
                            <div class="radio-group">
                                <label><input type="radio" name="<?= $key ?>_yesno" value="Yes"> Yes</label>
                                <label><input type="radio" name="<?= $key ?>_yesno" value="No"> No</label>
                            </div>
                        </div>
                        <div class="form-grid-2">
                            <div class="form-field">
                                <label for="<?= $key ?>_song">Song</label>
                                <input type="text" id="<?= $key ?>_song" name="<?= $key ?>_song">
                            </div>
                            <div class="form-field">
                                <label for="<?= $key ?>_artist">Artist</label>
                                <input type="text" id="<?= $key ?>_artist" name="<?= $key ?>_artist">
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="wedding-section">
                    <h2>Other Special Music</h2>

                    <?php
                    $specialRows = [
                        'cake_cutting'   => 'Cake Cutting',
                        'bouquet_toss'   => 'Bouquet Toss',
                        'garter_removal' => 'Garter Removal',
                    ];
                    foreach ($specialRows as $key => $label):
                    ?>
                    <div class="party-row">
                        <h3><?= htmlspecialchars($label) ?></h3>
                        <div class="form-field">
                            <label>Include this?</label>
                            <div class="radio-group">
                                <label><input type="radio" name="<?= $key ?>_yesno" value="Yes"> Yes</label>
                                <label><input type="radio" name="<?= $key ?>_yesno" value="No"> No</label>
                            </div>
                        </div>
                        <div class="form-grid-2">
                            <div class="form-field">
                                <label for="<?= $key ?>_song">Song</label>
                                <input type="text" id="<?= $key ?>_song" name="<?= $key ?>_song">
                            </div>
                            <div class="form-field">
                                <label for="<?= $key ?>_artist">Artist</label>
                                <input type="text" id="<?= $key ?>_artist" name="<?= $key ?>_artist">
                            </div>
                        </div>
                        <div class="form-field">
                            <label>Or let Nick decide?</label>
                            <div class="radio-group">
                                <label><input type="radio" name="<?= $key ?>_let_nick_decide" value="Yes"> Yes</label>
                                <label><input type="radio" name="<?= $key ?>_let_nick_decide" value="No"> No</label>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="wedding-section">
                    <h2>Special Requests</h2>
                    <p class="section-note">
                        Songs that you would like me to learn. Depending on style, time, etc. these may or may not be played, but I will do my best.
                    </p>

                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <div class="form-grid-2" style="margin-bottom:1rem;">
                        <div class="form-field">
                            <label for="request_song_<?= $i ?>">Song <?= $i ?></label>
                            <input type="text" id="request_song_<?= $i ?>" name="request_song_<?= $i ?>">
                        </div>
                        <div class="form-field">
                            <label for="request_artist_<?= $i ?>">Artist <?= $i ?></label>
                            <input type="text" id="request_artist_<?= $i ?>" name="request_artist_<?= $i ?>">
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>

<!--                 <div class="wedding-section">
                    <h2>Speeches</h2>
                    <div class="form-field">
                        <label for="speeches_notes">Best Man / Maid of Honor / Other</label>
                        <textarea id="speeches_notes" name="speeches_notes" placeholder="These usually occur during dinner. We provide our wireless mic and the folks introduce themselves. Add any notes here."></textarea>
                    </div>
                </div> -->

                <div class="wedding-section">
                    <h2>Contact</h2>
                    <div class="form-grid-2">
                        <div class="form-field">
                            <label for="contact_name">Your Name</label>
                            <input type="text" id="contact_name" name="contact_name">
                        </div>
                        <div class="form-field">
                            <label for="contact_email">Your Email</label>
                            <input type="email" id="contact_email" name="contact_email">
                        </div>
                    </div>
                </div>

                <p class="muted small">
                    Final details and balance can be handled on the day — we’ll keep everything easy and stress-free.
                </p>

                <button type="submit" class="btn btn-primary btn-full">Submit Wedding Outline</button>
            </form>
        </div>
    </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>