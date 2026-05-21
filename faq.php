<?php
$faqs = [
    [
        'question' => 'Do you perform at weddings?',
        'answer' => 'Yes. Nick performs for weddings, including ceremonies, cocktail hours, dinners, receptions, and dance portions of the night.'
    ],
    [
        'question' => 'Do you provide sound equipment?',
        'answer' => 'Yes. Nick provides a professional sound system. In most situations, all that is needed is one standard outlet to power the system.'
    ],
    [
        'question' => 'Do you play private parties?',
        'answer' => 'Of course. Nick performs for private parties, backyard events, birthdays, reunions, milestone celebrations, home events, and other private gatherings.'
    ],
    [
        'question' => 'Can you perform during cocktails, dinner, and dancing?',
        'answer' => 'Absolutely. Nick can provide music coverage for cocktails, dinner, background atmosphere, sing-alongs, dancing, and party portions of an event.'
    ],
    [
        'question' => 'Do you travel outside the Chicago area?',
        'answer' => 'Yes. A driving range of about eight hours is reasonable depending on the request. Nick will also consider farther travel when compensation and accommodations make sense for the event.'
    ],
    [
        'question' => 'Can you learn special songs?',
        'answer' => 'Most songs can be learned as long as they are in English and there is enough lead time before the event.'
    ],
    [
        'question' => 'How far in advance should I book?',
        'answer' => 'Nick is often booked about six months out, so the earlier the better. Last-minute accommodations may also be possible depending on the date and event details.'
    ],
    [
        'question' => 'Are you a DJ or live musician?',
        'answer' => 'Nick is a live musician — a singing bassist who performs with a full-band-style sound. The experience has the energy of a band with the simplicity and personal attention of hiring one reliable performer.'
    ],
    [
        'question' => 'What types of events do you play?',
        'answer' => 'Nick plays private parties, corporate events, weddings, restaurants, bars, casinos, festivals, community events, and anywhere live music can elevate the atmosphere.'
    ],
    [
        'question' => 'How do I check availability?',
        'answer' => 'Nick keeps his public calendar linked from the Dates page. Even if a date appears booked, it may still be worth asking because some dates can be switched around depending on the circumstances.'
    ],
    [
        'question' => 'Do I need a deposit and how much?',
        'answer' => 'Nick typically works with a contract and prefers payment in full the night of the event. A deposit is not required to book most dates, although clients are welcome to provide a small $50–$100 deposit if they prefer.'
    ],
];

function ns_faq_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQ | Nick Sanzeri</title>
    <meta name="description" content="Frequently asked questions about booking Nick Sanzeri for weddings, private parties, corporate events, venues, sound equipment, travel, deposits, and availability.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/favicons/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/favicons/favicon-16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/favicons/favicon-180.png">
    <link rel="manifest" href="site.webmanifest">
    <link rel="shortcut icon" href="favicons/favicon.ico">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .faq-wrap {
            max-width: 980px;
            margin: 0 auto;
            display: grid;
            gap: 1rem;
        }
        .faq-card {
            border-radius: 22px;
            background: linear-gradient(135deg, rgba(34,34,44,0.98), rgba(19,19,29,0.98));
            border: 1px solid rgba(255,255,255,.16);
            box-shadow: 0 18px 42px rgba(0,0,0,.22);
            color: #fff;
            overflow: hidden;
        }
        .faq-card summary {
            cursor: pointer;
            list-style: none;
            padding: 1.25rem 1.4rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }
        .faq-card summary::-webkit-details-marker {
            display: none;
        }
        .faq-card summary::after {
            content: '+';
            font-size: 1.5rem;
            line-height: 1;
            color: #f5d08a;
        }
        .faq-card[open] summary::after {
            content: '–';
        }
        .faq-card p {
            margin: 0;
            padding: 0 1.4rem 1.35rem;
            color: rgba(255,255,255,.88);
            line-height: 1.7;
        }
        .faq-note {
            max-width: 850px;
            margin: 2rem auto 0;
            opacity: .82;
            text-align: center;
        }
    </style>
    <?php
    require_once __DIR__ . '/includes/schema-global.php';
    ns_schema_output('faq');
    ?>
</head>
<body id="top">
<?php include __DIR__ . '/includes/header.php'; ?>

<main>
    <section class="page-hero">
        <div class="container">
            <p class="eyebrow">FAQ</p>
            <h1>Booking questions, answered.</h1>
            <p class="page-intro">
                A quick guide to hiring Nick Sanzeri for weddings, private parties, corporate events, restaurants, bars, casinos, festivals, and special occasions.
            </p>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="faq-wrap">
                <?php foreach ($faqs as $index => $faq): ?>
                    <details class="faq-card" <?= $index === 0 ? 'open' : ''; ?>>
                        <summary><?= ns_faq_h($faq['question']); ?></summary>
                        <p><?= ns_faq_h($faq['answer']); ?></p>
                    </details>
                <?php endforeach; ?>
            </div>

            <p class="faq-note">
                Have a date or situation that does not fit neatly into these answers?
                <a href="booking.php">Send the details here</a> and Nick will respond personally.
            </p>
        </div>
    </section>

    <section class="section section-cta">
        <div class="container cta-inner">
            <div>
                <h2>Ready to check your date?</h2>
                <p>Look at the public calendar, then reach out. Some dates may still be flexible depending on the circumstances.</p>
            </div>
            <div class="cta-actions">
                <a href="shows.php" class="btn btn-outline">View dates</a>
                <a href="booking.php" class="btn btn-primary">Start a booking inquiry</a>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
