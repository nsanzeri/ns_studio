NS Studio - Hybrid Shop Download Setup

1) Install Stripe PHP SDK
   composer require stripe/stripe-php

2) Configure keys + price id
   Edit /stripe_config.php
   - sk_live_REPLACE_ME (or sk_test_...)
   - price_REPLACE_ME (Stripe Price ID for Backing Track Blueprint)
   - Optionally set app.base_url in /_core/config.php (else uses https://nicksanzeri.com)

3) Put the PDF here:
   /private_downloads/Backing-Track-Blueprint.pdf

4) Create the download_tokens table
   Run either:
   - /sql/download_tokens.sql
   - or /migrations/002_download_tokens.sql

5) Test flow
   - Visit /shop/blueprint.php
   - Buy -> Stripe checkout
   - Success page shows Download Now (tokened)

Note: /shop/success.php already verifies payment using Stripe Checkout session.
