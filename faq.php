<?php
/**
 * FAQ page for NickSanzeri.com
 * Drop-in file: place at /faq.php
 */
declare(strict_types=1);

$calendarUrl = 'https://calendar.google.com/calendar/embed?src=pjjfdgelvdjtuvrr89tun3nu7k%40group.calendar.google.com&ctz=America%2FChicago';

$faqs = [
    [
        'question' => 'Do you perform at weddings?',
        'answer' => 'Yes. Nick performs for wedding ceremonies, cocktail hours, dinners, receptions, and full evening celebrations. The goal is to make the music feel personal, polished, and easy for the couple and their guests.'
    ],
    [
        'question' => 'Do you provide sound equipment?',
        'answer' => 'Yes. Nick brings a professional sound system. In most situations, all that is needed is one standard electrical outlet to power the system.'
    ],
    [
        'question' => 'Do you play private parties?',
        'answer' => 'Of course. Private parties are one of Nick’s specialties, from backyard parties and milestone birthdays to home events, lake house gatherings, reunions, and private celebrations.'
    ],
    [
        'question' => 'Can you perform during cocktails, dinner, and dancing?',
        'answer' => 'Absolutely. Nick can provide music coverage throughout the event, including guest arrival, cocktails, dinner, background music, sing-alongs, dance energy, and closing songs.'
    ],
    [
        'question' => 'Do you travel outside the Chicago area?',
        'answer' => 'Yes. A driving range of about eight hours from the Chicago area is reasonable depending on the request. Farther travel may also be considered when compensation and accommodations are included.'
    ],
    [
        'question' => 'Can you learn special songs?',
        'answer' => 'Most songs can be learned as long as they are in English and there is enough lead time to prepare them well. Special song requests are best discussed during the booking process.'
    ],
    [
        'question' => 'How far in advance should I book?',
        'answer' => 'Nick is often booked about six months out, so earlier is better. That said, last-minute accommodations can sometimes be made depending on the date, location, and event details.'
    ],
    [
        'question' => 'Are you a DJ or a live musician?',
        'answer' => 'Nick is a live musician, singer, and bassist with a full-band sound. He is not a traditional DJ, but he can keep music flowing between live sets and tailor the energy of the room throughout the event.'
    ],
    [
        'question' => 'What types of events do you play?',
        'answer' => 'Nick performs anywhere there is a need to elevate the atmosphere: private parties, corporate events, weddings, restaurants, bars, casinos, festivals, community events, and more.'
    ],
    [
        'question' => 'How do I check availability?',
        'answer' => 'Nick’s public calendar is available from the Dates page. If your date appears booked, it still may be worth asking because some dates can sometimes be switched or accommodated depending on the circumstances.'
    ],
	[
			'question' => 'Do I need a deposit and how much?',
			'answer' => 'Nick typically works with a contract and prefers payment in full the night of the event. A deposit is not required to book most dates, although clients are welcome to provide a small $50–$100 deposit if they prefer.'
	],
];

$faqSchema = [];
foreach ($faqs as $faq) {
    $faqSchema[] = [
        '@type' => 'Question',
        'name' => $faq['question'],
        'acceptedAnswer' => [
            '@type' => 'Answer',
            'text' => $faq['answer'],
        ],
    ];
}

$schema = [
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'WebPage',
            '@id' => 'https://nicksanzeri.com/faq.php#webpage',
            'url' => 'https://nicksanzeri.com/faq.php',
            'name' => 'FAQ | Nick Sanzeri Music',
            'description' => 'Frequently asked questions about booking Nick Sanzeri for weddings, private parties, corporate events, restaurants, casinos, festivals, and live music events in Chicagoland and beyond.',
            'isPartOf' => [
                '@id' => 'https://nicksanzeri.com/#website',
            ],
            'about' => [
                '@id' => 'https://nicksanzeri.com/#nick-sanzeri',
            ],
            'mainEntity' => [
                '@id' => 'https://nicksanzeri.com/faq.php#faq',
            ],
        ],
        [
            '@type' => 'FAQPage',
            '@id' => 'https://nicksanzeri.com/faq.php#faq',
            'mainEntity' => $faqSchema,
        ],
        [
            '@type' => 'WebSite',
            '@id' => 'https://nicksanzeri.com/#website',
            'url' => 'https://nicksanzeri.com/',
            'name' => 'Nick Sanzeri Music',
            'publisher' => [
                '@id' => 'https://nicksanzeri.com/#nick-sanzeri',
            ],
        ],
        [
            '@type' => ['Person', 'MusicGroup', 'PerformingGroup', 'LocalBusiness'],
            '@id' => 'https://nicksanzeri.com/#nick-sanzeri',
            'name' => 'Nick Sanzeri',
            'alternateName' => 'Nick Sanzeri Music',
            'url' => 'https://nicksanzeri.com/',
            'image' => 'https://nicksanzeri.com/assets/img/paint.png',
            'description' => 'Nick Sanzeri is a Chicagoland singer, bassist, and live entertainer providing full-band-sounding live music for weddings, private parties, corporate events, restaurants, casinos, clubs, festivals, and community events.',
            'slogan' => 'One man. Full-band experience.',
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => 'Carol Stream',
                'addressRegion' => 'IL',
                'addressCountry' => 'US',
            ],
            'areaServed' => [
                ['@type' => 'Place', 'name' => 'Chicago, IL'],
                ['@type' => 'Place', 'name' => 'Chicagoland'],
                ['@type' => 'Place', 'name' => 'Midwest'],
            ],
            'sameAs' => [
                'https://www.facebook.com/nicksanzeri13',
                'https://open.spotify.com/artist/6xRrH2IVMxSkMihQUXcYdJ?si=O4zvZ3xUQyWhO1Jhsq-dnQ',
                'https://twitter.com/nick_sanzeri',
                'https://www.tiktok.com/@nicksanzeri?lang=en',
                'https://www.instagram.com/nick_sanzeri/',
                'https://www.youtube.com/channel/UCnTEOsjjmdnM0jBZyJY6jfg',
            ],
            'knowsAbout' => [
                'Wedding entertainment',
                'Private event entertainment',
                'Corporate event music',
                'Live music for restaurants',
                'Casino entertainment',
                'Festival entertainment',
                'Singing bassist',
                'One-man band entertainment',
            ],
            'makesOffer' => [
                [
                    '@type' => 'Offer',
                    'itemOffered' => [
                        '@type' => 'Service',
                        'name' => 'Wedding Entertainment and Live Music',
                        'description' => 'Live vocals, bass, full-band-style backing tracks, professional sound, and event music coverage for wedding ceremonies, cocktail hours, dinners, and receptions.',
                    ],
                ],
                [
                    '@type' => 'Offer',
                    'itemOffered' => [
                        '@type' => 'Service',
                        'name' => 'Private Party Entertainment',
                        'description' => 'Live music for birthdays, backyard parties, milestone events, reunions, home events, and private celebrations.',
                    ],
                ],
                [
                    '@type' => 'Offer',
                    'itemOffered' => [
                        '@type' => 'Service',
                        'name' => 'Corporate Event Music',
                        'description' => 'Professional live entertainment for company parties, grand openings, client events, staff celebrations, and business functions.',
                    ],
                ],
            ],
        ],
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
    <title>FAQ | Nick Sanzeri Music</title>
    <meta name="description" content="Frequently asked questions about booking Nick Sanzeri for weddings, private parties, corporate events, restaurants, casinos, festivals, and live music events in Chicagoland and beyond.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/favicons/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/favicons/favicon-16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/favicons/favicon-180.png">
    <link rel="manifest" href="site.webmanifest">
    <link rel="shortcut icon" href="assets/favicons/favicon.ico">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .faq-hero .page-intro {
            max-width: 850px;
        }
        .faq-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 340px;
            gap: 2rem;
            align-items: start;
        }
        .faq-list {
            display: grid;
            gap: 1rem;
        }
        .faq-item {
            border-radius: 22px;
            overflow: hidden;
            background: linear-gradient(135deg, rgba(35,35,48,0.98), rgba(16,16,25,0.98));
            border: 1px solid rgba(255,255,255,0.14);
            box-shadow: 0 18px 45px rgba(0,0,0,.18);
            color: #fff;
        }
        .faq-item summary {
            cursor: pointer;
            list-style: none;
            padding: 1.2rem 1.35rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }
        .faq-item summary::-webkit-details-marker {
            display: none;
        }
        .faq-item summary::after {
            content: '+';
            width: 32px;
            height: 32px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            background: rgba(255,255,255,.1);
            color: #f5d08a;
            font-size: 1.3rem;
            line-height: 1;
        }
        .faq-item[open] summary::after {
            content: '−';
        }
        .faq-answer {
            padding: 0 1.35rem 1.35rem;
            color: rgba(255,255,255,.86);
            line-height: 1.75;
        }
        .faq-side-card {
            position: sticky;
            top: 110px;
            border-radius: 28px;
            padding: 1.5rem;
            background: linear-gradient(135deg, rgba(50,37,27,0.98), rgba(22,22,32,0.98));
            border: 1px solid rgba(255,255,255,.16);
            color: #fff8ec;
            box-shadow: 0 22px 55px rgba(0,0,0,.24);
        }
        .faq-side-card h2 {
            margin-top: 0;
        }
        .faq-side-card p {
            color: rgba(255,248,236,.88);
            line-height: 1.75;
        }
        .faq-side-actions {
            display: grid;
            gap: .75rem;
            margin-top: 1.25rem;
        }
        .faq-mini-proof {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }
        .faq-proof-card {
            border-radius: 20px;
            padding: 1.25rem;
            background: rgba(255,255,255,.08);
            border: 1px solid rgba(255,255,255,.16);
        }
        .faq-proof-card i {
            color: #f5d08a;
            font-size: 1.3rem;
            margin-bottom: .75rem;
        }
        .faq-proof-card h3 {
            margin-top: 0;
            margin-bottom: .35rem;
        }
        .faq-proof-card p {
            margin-bottom: 0;
            opacity: .86;
        }
        @media (max-width: 900px) {
            .faq-layout,
            .faq-mini-proof {
                grid-template-columns: 1fr;
            }
            .faq-side-card {
                position: static;
            }
        }
    </style>
    <script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); ?></script>
</head>
<body id="top">
<?php include __DIR__ . '/includes/header.php'; ?>

<main>
    <section class="page-hero faq-hero">
        <div class="container">
            <p class="eyebrow">Frequently Asked Questions</p>
            <h1>Booking live music should feel easy.</h1>
            <p class="page-intro">
                Here are the questions people usually ask before hiring Nick Sanzeri for weddings, private parties, corporate events, venues, restaurants, casinos, festivals, and community celebrations.
            </p>
        </div>
    </section>

    <section class="section section-dark">
        <div class="container faq-layout">
            <div class="faq-list" aria-label="Frequently asked questions">
                <?php foreach ($faqs as $index => $faq): ?>
                    <details class="faq-item"<?= $index === 0 ? ' open' : ''; ?>>
                        <summary><?= ns_faq_h($faq['question']); ?></summary>
                        <div class="faq-answer">
                            <?= ns_faq_h($faq['answer']); ?>
                            <?php if ($faq['question'] === 'How do I check availability?'): ?>
                                <br><br>
                                <a href="shows.php" class="btn btn-outline">View the Dates page</a>
                            <?php endif; ?>
                        </div>
                    </details>
                <?php endforeach; ?>
            </div>

            <aside class="faq-side-card">
                <p class="eyebrow">Still wondering?</p>
                <h2>Ask about your date.</h2>
                <p>
                    Even if the calendar looks busy, it is still worth checking. Some dates can be adjusted depending on the event, location, timing, and compensation.
                </p>
                <div class="faq-side-actions">
                    <a href="booking.php" class="btn btn-primary">Start a booking inquiry</a>
                    <a href="<?= ns_faq_h($calendarUrl); ?>" target="_blank" rel="noopener" class="btn btn-outline">Open public calendar</a>
                </div>
            </aside>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="faq-mini-proof">
                <article class="faq-proof-card">
                    <i class="fas fa-plug" aria-hidden="true"></i>
                    <h3>Simple setup</h3>
                    <p>Professional sound, clean footprint, and usually just one outlet needed.</p>
                </article>
                <article class="faq-proof-card">
                    <i class="fas fa-music" aria-hidden="true"></i>
                    <h3>Live energy</h3>
                    <p>A singing bassist with a full-band sound and room-reading experience.</p>
                </article>
                <article class="faq-proof-card">
                    <i class="fas fa-calendar-check" aria-hidden="true"></i>
                    <h3>Real availability</h3>
                    <p>Use the public calendar as a guide, then ask directly about your date.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="section section-cta">
        <div class="container cta-inner">
            <div>
                <h2>Ready to see if your date works?</h2>
                <p>Send the details and Nick will respond personally.</p>
            </div>
            <div class="cta-actions">
                <a href="shows.php" class="btn btn-outline">View calendar</a>
                <a href="booking.php" class="btn btn-primary">Book Nick</a>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
