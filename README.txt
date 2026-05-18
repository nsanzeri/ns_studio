Subscription cancellation patch

Drop these files into the matching paths:

studio/member/settings.php
studio/member/library.php
studio/api/create_customer_portal_session.php

Important Stripe setup:
1. In Stripe Dashboard, enable/configure Customer Portal for both test and live mode.
2. Allow customers to cancel subscriptions in the portal.
3. Keep your existing customer.subscription.updated and customer.subscription.deleted webhooks enabled.

Behavior:
- Settings shows a Manage / Cancel Subscription button for active Stripe subscriptions.
- The button opens Stripe Customer Portal.
- Account deletion is blocked while an active Stripe subscription exists.
- My Products sends paid users to Settings for subscription management.
