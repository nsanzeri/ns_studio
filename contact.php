<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact | Nick Sanzeri</title>
    <meta name="description" content="Contact Nick Sanzeri about booking, availability, live performances, weddings, private parties, corporate events, and venue entertainment.">
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
    ns_schema_output('contact');
    ?>
</head>
<body id="top">
<?php 
include __DIR__ . '/includes/header.php';
?>


    <main>
        <section class="page-hero">
            <div class="container">
                <p class="eyebrow">Contact</p>
                <h1>Get in touch.</h1>
                <p class="page-intro">
                    Questions, special requests, or just want to say hi? Reach out anytime.
                </p>
            </div>
        </section>

        <section class="section">
            <div class="container grid-2 contact-layout">
                <div class="contact-info-block">
                    <div class="contact-row">
                        <div class="contact-icon"><i class="fas fa-envelope"></i></div>
                        <div>
                            <h3>Email</h3>
                            <p><a href="mailto:nsanzeri@gmail.com">nsanzeri@gmail.com</a></p>
                        </div>
                    </div>
                    <div class="contact-row">
                        <div class="contact-icon"><i class="fas fa-phone"></i></div>
                        <div>
                            <h3>Phone / Text</h3>
                            <p><a href="tel:+12245350104">(224) 535‑0104</a></p>
                        </div>
                    </div>
                    <div class="contact-row">
                        <div class="contact-icon"><i class="fas fa-map-marker-alt"></i></div>
                        <div>
                            <h3>Location</h3>
                            <p>Chicagoland &amp; beyond</p>
                        </div>
                    </div>
                    <p class="muted small">
                        Prefer a more detailed quote? Head over to the <a href="booking.php">Booking</a> page for a full event questionnaire.
                    </p>
                </div>
                <div>
                    <div class="newsletter-block">
                        <h2>Never miss a show.</h2>
                        <p>Get gig announcements, new music, and the occasional behind‑the‑scenes story — no spam.</p>
                        <!-- Mailchimp embed kept from existing site -->
                        <link href="//cdn-images.mailchimp.com/embedcode/classic-061523.css" rel="stylesheet" type="text/css">
                        <div id="mc_embed_signup">
                            <form action="https://nicksanzeri.us2.list-manage.com/subscribe/post?u=758e0b12aa6b2c9e1c489b5f1&amp;id=fa36f06ccb&amp;f_id=002cfae3f0" method="post" target="_blank" novalidate>
                                <div id="mc_embed_signup_scroll">
                                    <div class="mc-field-group">
                                        <label for="mce-EMAIL">Email address <span class="asterisk">*</span></label>
                                        <input type="email" name="EMAIL" class="required email" id="mce-EMAIL" required>
                                    </div>
                                    <div class="optionalParent">
                                        <div class="clear foot">
                                            <input type="submit" value="Subscribe" name="subscribe" class="btn btn-primary">
                                        </div>
                                    </div>
                                    <div style="position: absolute; left: -5000px;" aria-hidden="true">
                                        <input type="text" name="b_758e0b12aa6b2c9e1c489b5f1_fa36f06ccb" tabindex="-1" value="">
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
	<?php 
	include __DIR__ . '/includes/footer.php';
	?>

</body>
</html>
