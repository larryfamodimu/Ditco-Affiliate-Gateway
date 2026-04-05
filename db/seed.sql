
INSERT INTO affiliates (business_name, logo_url, phone, email, slug, password)
VALUES (
    'Acme Technologies',
    'https://placehold.co/200x80?text=Acme+Tech',
    '+234-800-000-0001',
    'hello@acmetech.ng',
    'acme-tech',
    '$2y$10$placeholder_run_seed_admin_php_to_set_real_hash______'
) ON CONFLICT (slug) DO NOTHING;

-- Sample product linked to the first affiliate
INSERT INTO products (affiliate_id, name, description, price, destination_url)
SELECT
    id,
    'Ditcosoft Pro Suite',
    'All-in-one business management platform with CRM, invoicing, and analytics.',
    299.99,
    'https://ditcosoft.com/pro-suite'
FROM affiliates WHERE slug = 'acme-tech'
ON CONFLICT DO NOTHING;
