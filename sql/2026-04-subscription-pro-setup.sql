-- Ready Set Shows Pro subscription seed data
-- Run this once after deploying the PHP drop-ins.

INSERT INTO subscription_plans (slug, name, price_monthly_cents, shop_discount_percent, is_active)
VALUES ('rss-pro', 'Ready Set Shows Pro', 1000, 5.00, 1)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  price_monthly_cents = VALUES(price_monthly_cents),
  shop_discount_percent = VALUES(shop_discount_percent),
  is_active = VALUES(is_active);

INSERT INTO products (slug, name, kind, file_path)
VALUES ('rss-pro', 'Ready Set Shows Pro', 'other', NULL)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  kind = VALUES(kind);

-- Optional aliases so old soft-lock checks keep working even if you later pivot names.
INSERT INTO products (slug, name, kind, file_path)
VALUES
  ('ready-set-shows-pro', 'Ready Set Shows Pro', 'other', NULL),
  ('calendar-tools-pro', 'Ready Set Shows Pro', 'other', NULL)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  kind = VALUES(kind);
