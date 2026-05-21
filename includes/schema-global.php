<?php
/**
 * Shared JSON-LD schema for NickSanzeri.com.
 *
 * Usage in page <head>:
 * require_once __DIR__ . '/includes/schema-global.php';
 * ns_schema_output('home'); // home, booking, shows, testimonials, media, contact, payments, faq
 */
if (!function_exists('ns_schema_json')) {
    function ns_schema_json(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}

if (!function_exists('ns_schema_base_graph')) {
    function ns_schema_base_graph(): array
    {
        $site = 'https://nicksanzeri.com';

        return [
            [
                '@type' => 'WebSite',
                '@id' => $site . '/#website',
                'url' => $site . '/',
                'name' => 'Nick Sanzeri Music',
                'description' => 'Official website for Nick Sanzeri, a Chicagoland singing bassist and live entertainer for weddings, private parties, corporate events, restaurants, clubs, casinos, and community events.',
                'publisher' => ['@id' => $site . '/#nick-sanzeri'],
                'inLanguage' => 'en-US'
            ],
            [
                '@type' => ['Person', 'MusicGroup', 'PerformingGroup', 'LocalBusiness'],
                '@id' => $site . '/#nick-sanzeri',
                'name' => 'Nick Sanzeri',
                'alternateName' => ['Nick Sanzeri Music', 'Nick Sanzeri - One Man Full-Band Experience'],
                'url' => $site . '/',
                'image' => $site . '/assets/img/paint.png',
                'description' => 'Nick Sanzeri is a Chicagoland singing bassist and live entertainer providing full-band-sounding live music for weddings, private parties, corporate events, restaurants, casinos, clubs, festivals, and community events.',
                'slogan' => 'One man. Full-band experience.',
                'priceRange' => '$$',
                'address' => [
                    '@type' => 'PostalAddress',
                    'addressLocality' => 'Carol Stream',
                    'addressRegion' => 'IL',
                    'addressCountry' => 'US'
                ],
                'areaServed' => [
                    ['@type' => 'Place', 'name' => 'Chicago, IL'],
                    ['@type' => 'Place', 'name' => 'Chicagoland'],
                    ['@type' => 'Place', 'name' => 'Illinois'],
                    ['@type' => 'Place', 'name' => 'Wisconsin'],
                    ['@type' => 'Place', 'name' => 'Midwest']
                ],
                'sameAs' => [
                    'https://www.facebook.com/nicksanzeri13',
                    'https://open.spotify.com/artist/6xRrH2IVMxSkMihQUXcYdJ',
                    'https://twitter.com/nick_sanzeri',
                    'https://www.tiktok.com/@nicksanzeri',
                    'https://www.instagram.com/nick_sanzeri/',
                    'https://www.youtube.com/channel/UCnTEOsjjmdnM0jBZyJY6jfg'
                ],
                'knowsAbout' => [
                    'Wedding entertainment',
                    'Private party entertainment',
                    'Corporate event music',
                    'Live music for restaurants',
                    'Casino entertainment',
                    'Festival entertainment',
                    'Cocktail hour music',
                    'Dinner music',
                    'Dance music',
                    'Singing bassist',
                    'One-man band entertainment',
                    'Professional live sound'
                ],
                'makesOffer' => [
                    [
                        '@type' => 'Offer',
                        'itemOffered' => [
                            '@type' => 'Service',
                            '@id' => $site . '/#wedding-entertainment',
                            'name' => 'Wedding Entertainment and Live Music',
                            'description' => 'Live vocals, bass, full-band-style backing tracks, professional sound, and event music coverage for wedding ceremonies, cocktail hours, dinners, and receptions.',
                            'provider' => ['@id' => $site . '/#nick-sanzeri'],
                            'areaServed' => ['@type' => 'Place', 'name' => 'Chicagoland and the Midwest']
                        ]
                    ],
                    [
                        '@type' => 'Offer',
                        'itemOffered' => [
                            '@type' => 'Service',
                            '@id' => $site . '/#private-party-entertainment',
                            'name' => 'Private Party Entertainment',
                            'description' => 'Live music for birthdays, backyard parties, milestone events, reunions, home events, and private celebrations.',
                            'provider' => ['@id' => $site . '/#nick-sanzeri'],
                            'areaServed' => ['@type' => 'Place', 'name' => 'Chicagoland and the Midwest']
                        ]
                    ],
                    [
                        '@type' => 'Offer',
                        'itemOffered' => [
                            '@type' => 'Service',
                            '@id' => $site . '/#corporate-event-music',
                            'name' => 'Corporate Event Music',
                            'description' => 'Professional live entertainment for company parties, grand openings, client events, staff celebrations, and business functions.',
                            'provider' => ['@id' => $site . '/#nick-sanzeri'],
                            'areaServed' => ['@type' => 'Place', 'name' => 'Chicagoland and the Midwest']
                        ]
                    ],
                    [
                        '@type' => 'Offer',
                        'itemOffered' => [
                            '@type' => 'Service',
                            '@id' => $site . '/#venue-entertainment',
                            'name' => 'Restaurant, Club, Casino, Festival, and Community Event Entertainment',
                            'description' => 'Crowd-friendly live music designed to keep guests engaged, entertained, and staying longer.',
                            'provider' => ['@id' => $site . '/#nick-sanzeri'],
                            'areaServed' => ['@type' => 'Place', 'name' => 'Chicagoland and the Midwest']
                        ]
                    ]
                ]
            ]
        ];
    }
}

if (!function_exists('ns_schema_page_node')) {
    function ns_schema_page_node(string $type): array
    {
        $site = 'https://nicksanzeri.com';
        $map = [
            'home' => [
                'url' => $site . '/',
                'name' => 'Nick Sanzeri | One Man Full-Band Experience',
                'description' => 'Nick Sanzeri is a Chicagoland singing bassist and live entertainer for weddings, private events, corporate functions, restaurants, clubs, casinos, and community events.'
            ],
            'booking' => [
                'url' => $site . '/booking.php',
                'name' => 'Book Nick Sanzeri for Weddings, Private Parties, Corporate Events, and Venues',
                'description' => 'Request Nick Sanzeri for live music at weddings, private parties, corporate events, restaurants, clubs, casinos, festivals, and community events.'
            ],
            'shows' => [
                'url' => $site . '/shows.php',
                'name' => 'Nick Sanzeri Performance Calendar and Public Shows',
                'description' => 'View upcoming public performances and calendar availability for Nick Sanzeri, Chicagoland singing bassist and live entertainer.'
            ],
            'testimonials' => [
                'url' => $site . '/testimonials.php',
                'name' => 'Nick Sanzeri Reviews and Testimonials',
                'description' => 'Client reviews, wedding testimonials, audience comments, and professional praise for Nick Sanzeri.'
            ],
            'media' => [
                'url' => $site . '/media.php',
                'name' => 'Nick Sanzeri Videos and Music Media',
                'description' => 'Watch and listen to performance clips, live videos, and music from Nick Sanzeri.'
            ],
            'contact' => [
                'url' => $site . '/contact.php',
                'name' => 'Contact Nick Sanzeri',
                'description' => 'Contact Nick Sanzeri about booking, availability, live performances, media, and event entertainment.'
            ],
            'payments' => [
                'url' => $site . '/payments.php',
                'name' => 'Nick Sanzeri Payments',
                'description' => 'Payment page for confirmed Nick Sanzeri bookings and services.'
            ],
            'faq' => [
                'url' => $site . '/faq.php',
                'name' => 'Nick Sanzeri Booking FAQ',
                'description' => 'Frequently asked questions about booking Nick Sanzeri for weddings, private parties, corporate events, venues, travel, sound equipment, deposits, and availability.'
            ]
        ];
        $page = $map[$type] ?? $map['home'];

        $node = [
            '@type' => 'WebPage',
            '@id' => $page['url'] . '#webpage',
            'url' => $page['url'],
            'name' => $page['name'],
            'description' => $page['description'],
            'isPartOf' => ['@id' => $site . '/#website'],
            'about' => ['@id' => $site . '/#nick-sanzeri'],
            'mainEntity' => ['@id' => $site . '/#nick-sanzeri'],
            'inLanguage' => 'en-US'
        ];

        if ($type === 'booking') {
            $node['@type'] = ['WebPage', 'ContactPage'];
            $node['mainEntity'] = [
                '@type' => 'Service',
                '@id' => $site . '/booking.php#booking-service',
                'name' => 'Live Event Entertainment Booking',
                'description' => 'Booking inquiries for Nick Sanzeri live entertainment, including weddings, private parties, corporate events, restaurants, clubs, casinos, festivals, and community events.',
                'provider' => ['@id' => $site . '/#nick-sanzeri'],
                'serviceType' => 'Live music entertainment',
                'areaServed' => ['@type' => 'Place', 'name' => 'Chicagoland and the Midwest']
            ];
        }

        if ($type === 'shows') {
            $node['mainEntity'] = [
                '@type' => 'Schedule',
                '@id' => $site . '/shows.php#performance-calendar',
                'name' => 'Nick Sanzeri Public Performance Calendar',
                'description' => 'A public calendar of Nick Sanzeri performance dates and shows.',
                'performer' => ['@id' => $site . '/#nick-sanzeri']
            ];
        }

        if ($type === 'media') {
            $node['mainEntity'] = [
                '@type' => 'VideoGallery',
                '@id' => $site . '/media.php#video-gallery',
                'name' => 'Nick Sanzeri Performance Videos',
                'description' => 'A collection of performance videos, music clips, and live recordings featuring Nick Sanzeri.',
                'about' => ['@id' => $site . '/#nick-sanzeri']
            ];
        }

        if ($type === 'testimonials') {
            $node['mainEntity'] = [
                '@type' => 'ItemList',
                '@id' => $site . '/testimonials.php#reviews',
                'name' => 'Nick Sanzeri Reviews and Testimonials',
                'itemListElement' => [
                    [
                        '@type' => 'Review',
                        'reviewBody' => 'Nick sang at our wedding and was truly amazing. His voice was absolutely beautiful and added such a special, emotional, and elegant touch to our day.',
                        'author' => ['@type' => 'Person', 'name' => 'Nancy M.'],
                        'itemReviewed' => ['@id' => $site . '/#nick-sanzeri']
                    ],
                    [
                        '@type' => 'Review',
                        'reviewBody' => 'We have booked Nick multiple times for our community events. Residents absolutely love him — every time the crowd gets bigger.',
                        'author' => ['@type' => 'Person', 'name' => 'Andy V.'],
                        'itemReviewed' => ['@id' => $site . '/#nick-sanzeri']
                    ],
                    [
                        '@type' => 'Review',
                        'reviewBody' => 'Nick is the whole package — he is a super-talented musician, knows what people like, and knows how to work the crowd.',
                        'author' => ['@type' => 'Person', 'name' => 'Sandy White'],
                        'itemReviewed' => ['@id' => $site . '/#nick-sanzeri']
                    ]
                ]
            ];
        }

        return $node;
    }
}

if (!function_exists('ns_schema_faq_nodes')) {
    function ns_schema_faq_nodes(): array
    {
        return [
            [
                '@type' => 'Question',
                'name' => 'Do you perform at weddings?',
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Yes. Nick performs for weddings, including ceremonies, cocktail hours, dinners, receptions, and dance portions of the night.']
            ],
            [
                '@type' => 'Question',
                'name' => 'Do you provide sound equipment?',
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Yes. Nick provides a professional sound system. In most situations, all that is needed is one standard outlet to power the system.']
            ],
            [
                '@type' => 'Question',
                'name' => 'Do you play private parties?',
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Of course. Nick performs for private parties, backyard events, birthdays, reunions, milestone celebrations, home events, and other private gatherings.']
            ],
            [
                '@type' => 'Question',
                'name' => 'Can you perform during cocktails, dinner, and dancing?',
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Absolutely. Nick can provide music coverage for cocktails, dinner, background atmosphere, sing-alongs, dancing, and party portions of an event.']
            ],
            [
                '@type' => 'Question',
                'name' => 'Do you travel outside the Chicago area?',
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Yes. A driving range of about eight hours is reasonable depending on the request. Nick will also consider farther travel when compensation and accommodations make sense for the event.']
            ],
            [
                '@type' => 'Question',
                'name' => 'Can you learn special songs?',
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Most songs can be learned as long as they are in English and there is enough lead time before the event.']
            ],
            [
                '@type' => 'Question',
                'name' => 'How far in advance should I book?',
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Nick is often booked about six months out, so the earlier the better. Last-minute accommodations may also be possible depending on the date and event details.']
            ],
            [
                '@type' => 'Question',
                'name' => 'Are you a DJ or live musician?',
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Nick is a live musician — a singing bassist who performs with a full-band-style sound. The experience has the energy of a band with the simplicity and personal attention of hiring one reliable performer.']
            ],
            [
                '@type' => 'Question',
                'name' => 'What types of events do you play?',
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Nick plays private parties, corporate events, weddings, restaurants, bars, casinos, festivals, community events, and anywhere live music can elevate the atmosphere.']
            ],
            [
                '@type' => 'Question',
                'name' => 'How do I check availability?',
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Nick keeps his public calendar linked from the Dates page. Even if a date appears booked, it may still be worth asking because some dates can be switched around depending on the circumstances.']
            ],
            [
                '@type' => 'Question',
                'name' => 'Do I need a deposit and how much?',
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Nick typically works with a contract and prefers payment in full the night of the event. A deposit is not required to book most dates, although clients are welcome to provide a small $50 to $100 deposit if they prefer.']
            ]
        ];
    }
}

if (!function_exists('ns_schema_output')) {
    function ns_schema_output(string $type = 'home'): void
    {
        $site = 'https://nicksanzeri.com';
        $graph = ns_schema_base_graph();
        $graph[] = ns_schema_page_node($type);

        if ($type === 'faq') {
            $graph[] = [
                '@type' => 'FAQPage',
                '@id' => $site . '/faq.php#faq',
                'url' => $site . '/faq.php',
                'name' => 'Nick Sanzeri Booking FAQ',
                'mainEntity' => ns_schema_faq_nodes(),
                'about' => ['@id' => $site . '/#nick-sanzeri']
            ];
        }

        echo "<script type=\"application/ld+json\">\n";
        echo ns_schema_json([
            '@context' => 'https://schema.org',
            '@graph' => $graph
        ]);
        echo "\n</script>\n";
    }
}
