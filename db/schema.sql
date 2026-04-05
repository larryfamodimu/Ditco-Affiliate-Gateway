CREATE TABLE IF NOT EXISTS admins (
    id         SERIAL       PRIMARY KEY,
    username   VARCHAR(100) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,          -- bcrypt hash
    created_at TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);


CREATE TABLE IF NOT EXISTS affiliates (
    id            SERIAL       PRIMARY KEY,
    business_name VARCHAR(255) NOT NULL,
    logo_url      VARCHAR(500) DEFAULT NULL,
    phone         VARCHAR(30)  DEFAULT NULL,
    email         VARCHAR(255) NOT NULL UNIQUE,
    slug          VARCHAR(100) NOT NULL UNIQUE,  -- URL-safe lookup key
    password      VARCHAR(255) NOT NULL,          -- bcrypt hash for affiliate login
    created_at    TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at    TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);


CREATE TABLE IF NOT EXISTS products (
    id              SERIAL        PRIMARY KEY,
    affiliate_id    INTEGER       NOT NULL REFERENCES affiliates(id) ON DELETE CASCADE,
    name            VARCHAR(255)  NOT NULL,
    description     TEXT          DEFAULT NULL,
    price           NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    destination_url VARCHAR(1000) NOT NULL,    -- final redirect destination
    created_at      TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ   NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_products_affiliate ON products(affiliate_id);


CREATE TABLE IF NOT EXISTS click_logs (
    id           SERIAL       PRIMARY KEY,
    affiliate_id INTEGER      NOT NULL REFERENCES affiliates(id) ON DELETE CASCADE,
    ip_address   VARCHAR(45)  NOT NULL,    
    user_agent   VARCHAR(500) DEFAULT NULL,
    timestamp    TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);


CREATE INDEX IF NOT EXISTS idx_click_logs_ip_ts        ON click_logs(ip_address, timestamp);
CREATE INDEX IF NOT EXISTS idx_click_logs_affiliate_ts ON click_logs(affiliate_id, timestamp);
