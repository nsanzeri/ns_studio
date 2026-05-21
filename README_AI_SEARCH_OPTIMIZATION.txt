AI search optimization package for NickSanzeri.com

Files changed/added:
- includes/schema-global.php
  Shared JSON-LD schema helper for site identity and page-specific schema.
- faq.php
  New FAQ page with FAQPage schema and the deposit question included.
- index.php, booking.php, shows.php, testimonials.php, media.php, contact.php, payments.php
  Old repeated/broken JSON-LD blocks removed where present.
  Added page-specific schema calls.
  Added meta descriptions where missing.

Notes:
- about.php was left as your latest working version because it already has custom dynamic calendar-stat schema.
- PHP syntax was checked with php -l.
- Upload these files over the matching files in the site root.
