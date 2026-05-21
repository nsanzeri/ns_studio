<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking | Nick Sanzeri</title>
    <meta name="description" content="Book Nick Sanzeri for weddings, private parties, corporate events, restaurants, clubs, casinos, festivals, and community celebrations throughout Chicagoland and the Midwest.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/favicons/favicon-32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="assets/favicons/favicon-16.png">
	<link rel="apple-touch-icon" sizes="180x180" href="assets/favicons/favicon-180.png">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <?php
    require_once __DIR__ . '/includes/schema-global.php';
    ns_schema_output('booking');
    ?>
</head>
<body id="top">
<?php 
include __DIR__ . '/includes/header.php';
?>

    <main>
        <section class="page-hero">
            <div class="container">
                <p class="eyebrow">Booking</p>
                <h1>Let’s make your event unforgettable.</h1>
                <p class="page-intro">
                    Share a few details below and Nick will follow up personally with availability, pricing, and ideas tailored to your event.
                </p>
            </div>
        </section>

        <section class="section">
            <div class="container grid-2 booking-layout">
                <div>
                    <h2>What you can expect.</h2>
                    <ul class="feature-list">
                        <li><span>Premium solo performance with full‑band sound and energy.</span></li>
                        <li><span>Professional‑grade sound system, tailored to your room.</span></li>
                        <li><span>Thoughtful, responsive communication from inquiry to encore.</span></li>
                        <li><span>Optional MC duties and curated background playlists.</span></li>
                    </ul>
                    <p class="muted">
                        Most clients invest between <strong>$700–$1,500</strong> depending on date, length of performance, and travel.
                    </p>
                    <div class="hero-actions">
                        <a href="faq.php" class="btn btn-primary">FAQs</a>
                    </div>
                    
                </div>
                <div>
                    <form class="form" action="booking_submit.php" method="POST">
                        <h2 class="form-title">Booking Inquiry</h2>
                        <div class="form-grid">
                            <div class="form-field">
                                <label for="name">Your name*</label>
                                <input type="text" id="name" name="name" required>
                            </div>
                            <div class="form-field">
                                <label for="email">Email*</label>
                                <input type="email" id="email" name="email" required>
                            </div>
                            <div class="form-field">
                                <label for="phone">Phone*</label>
                                <input type="tel" id="phone" name="phone" required>
                            </div>
                            <div class="form-field">
                                <label for="eventType">Event type*</label>
                                <select id="eventType" name="event_type" required>
                                    <option value="">Select one</option>
                                    <option>Wedding</option>
                                    <option>Private Party</option>
                                    <option>Corporate Event</option>
                                    <option>Club / Restaurant</option>
                                    <option>Festival</option>
                                    <option>Other</option>
                                </select>
                            </div>
                            <div class="form-field">
                                <label for="eventDate">Event date</label>
                                <input type="date" id="eventDate" name="event_date">
                            </div>
                            <div class="form-field">
                                <label for="eventTime">Approx. start time</label>
                                <input type="text" id="eventTime" name="event_time" placeholder="e.g. 7:30 PM">
                            </div>
                            <div class="form-field">
                                <label for="venueName">Venue name</label>
                                <input type="text" id="venueName" name="venue_name" placeholder="Venue or location name">
                            </div>
                            <div class="form-field">
                                <label for="venueLocation">Venue location</label>
                                <input type="text" id="venueLocation" name="venue_location" placeholder="City / address">
                            </div>
                            <div class="form-field">
                                <label for="guestCount">Estimated guest count</label>
                                <input type="number" id="guestCount" name="guest_count" min="1">
                            </div>
                            <div class="form-field">
                                <label for="budgetRange">Budget range (approx.)</label>
                                <select id="budgetRange" name="budget_range">
                                    <option value="">Select one</option>
                                    <option>$500–$800</option>
                                    <option>$800–$1,200</option>
                                    <option>$1,200–$1,800</option>
                                    <option>$1,800–$2,500</option>
                                    <option>Let’s discuss</option>
                                </select>
                            </div>
                        </div>

                        <fieldset class="form-field">
                            <legend>What do you need? (check all that apply)</legend>
                            <div class="checkbox-grid">
                                <label><input type="checkbox" name="needs[]" value="Ceremony music"> Ceremony music</label>
                                <label><input type="checkbox" name="needs[]" value="Cocktail hour"> Cocktail hour</label>
                                <label><input type="checkbox" name="needs[]" value="Dinner set"> Dinner set</label>
                                <label><input type="checkbox" name="needs[]" value="Dance set / party"> Dance set / party</label>
                                <label><input type="checkbox" name="needs[]" value="MC / announcements"> MC / announcements</label>
                                <label><input type="checkbox" name="needs[]" value="Curated playlists between sets"> Playlists between sets (no DJ)</label>
                            </div>
                        </fieldset>

                        <div class="form-field">
                            <label for="vibe">What’s the vibe you’re going for?</label>
                            <textarea id="vibe" name="vibe" rows="3" placeholder="Tell me about your crowd, favorite artists, or the kind of night you’re imagining."></textarea>
                        </div>

                        <div class="form-field">
                            <label for="hearAbout">How did you hear about Nick?</label>
                            <input type="text" id="hearAbout" name="heard_about">
                        </div>

                        <div class="form-field">
                            <label for="otherDetails">Anything else Nick should know?</label>
                            <textarea id="otherDetails" name="other_details" rows="3"></textarea>
                        </div>

                        <p class="muted small">
                            This form starts the conversation — it’s not a contract. Nick will follow up to confirm availability, pricing, and details.
                        </p>
						<input type="text" name="website" style="display:none;">
                        <button type="submit" class="btn btn-primary btn-full">Submit Inquiry</button>
                    </form>
                </div>
            </div>
        </section>
    </main>
	<?php 
	include __DIR__ . '/includes/footer.php';
	?>

</body>
</html>
