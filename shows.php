<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shows | Nick Sanzeri</title>
    <meta name="description" content="View upcoming public shows and calendar availability for Nick Sanzeri, a Chicagoland singing bassist and live entertainer.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/favicons/favicon-32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="assets/favicons/favicon-16.png">
	<link rel="apple-touch-icon" sizes="180x180" href="assets/favicons/favicon-180.png">
	<link rel="manifest" href="site.webmanifest">
	<link rel="shortcut icon" href="favicons/favicon.ico">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <?php
    require_once __DIR__ . '/includes/schema-global.php';
    ns_schema_output('shows');
    ?>
</head>
<body id="top">
<?php 
include __DIR__ . '/includes/header.php';
?>


    <main>
        <section class="page-hero">
            <div class="container">
                <p class="eyebrow">Live Shows</p>
                <h1>Upcoming Performances</h1>
                <p class="page-intro">
                    Come see the one‑man full‑band experience live. Check out where Nick is playing next and plan your night out.
                </p>
            </div>
        </section>

        <section class="section">
            <div class="container">
				<!-- <div id="nextShows" class="next-shows"> -->
                <div id="eventsList" class="events-list">
                    <!-- Populated by events.js -->
                </div>
                <div class="external-calendar">
                    <a href="https://calendar.google.com/calendar/u/0?cid=cGpqZmRnZWx2ZGp0dXZycjg5dHVuM251N2tAZ3JvdXAuY2FsZW5kYXIuZ29vZ2xlLmNvbQ" 
                       target="_blank" 
                       class="text-link">
                        View full Google Calendar
                    </a>
                </div>
            </div>
        </section>
    </main>
	<?php 
	include __DIR__ . '/includes/footer.php';
	?>
</body>
</html>
