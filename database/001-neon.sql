CREATE TABLE IF NOT EXISTS users (
 user_id SERIAL PRIMARY KEY, auth_id TEXT UNIQUE NOT NULL, first_name TEXT NOT NULL, last_name TEXT NOT NULL,
 email TEXT UNIQUE NOT NULL, phone TEXT, date_of_birth DATE, is_active SMALLINT NOT NULL DEFAULT 1,
 created_at TIMESTAMPTZ NOT NULL DEFAULT now(), updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TABLE IF NOT EXISTS admin_users (
 admin_id SERIAL PRIMARY KEY, username TEXT UNIQUE NOT NULL, email TEXT UNIQUE NOT NULL,
 password_hash TEXT NOT NULL, full_name TEXT NOT NULL, role TEXT NOT NULL DEFAULT 'admin',
 is_active SMALLINT NOT NULL DEFAULT 1, last_login TIMESTAMPTZ,
 created_at TIMESTAMPTZ NOT NULL DEFAULT now(), updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TABLE IF NOT EXISTS packages (
 package_id SERIAL PRIMARY KEY, package_number INTEGER UNIQUE, package_name TEXT NOT NULL,
 description TEXT NOT NULL DEFAULT '', price NUMERIC(12,2) NOT NULL CHECK (price >= 0), max_guests INTEGER,
 classic_sauces INTEGER DEFAULT 0, premium_sauces INTEGER DEFAULT 0, classic_toppings INTEGER DEFAULT 0,
 premium_toppings INTEGER DEFAULT 0, fruit_choices INTEGER DEFAULT 0, setup_hours INTEGER DEFAULT 2,
 is_active SMALLINT NOT NULL DEFAULT 1, created_at TIMESTAMPTZ NOT NULL DEFAULT now(), updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TABLE IF NOT EXISTS reservations (
 reservation_id SERIAL PRIMARY KEY, user_id INTEGER REFERENCES users(user_id), customer_name TEXT NOT NULL,
 contact_number TEXT NOT NULL, email TEXT NOT NULL, event_datetime TIMESTAMP NOT NULL,
 package_id INTEGER NOT NULL REFERENCES packages(package_id), additional_notes TEXT, total_amount NUMERIC(12,2) NOT NULL,
 status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','confirmed','completed','cancelled')),
 payment_status TEXT NOT NULL DEFAULT 'unpaid' CHECK (payment_status IN ('unpaid','paid','partial')),
 created_at TIMESTAMPTZ NOT NULL DEFAULT now(), updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS reservations_user_id_idx ON reservations(user_id);
CREATE TABLE IF NOT EXISTS reservation_attachments (
 attachment_id UUID PRIMARY KEY, reservation_id INTEGER NOT NULL REFERENCES reservations(reservation_id) ON DELETE CASCADE,
 object_key TEXT UNIQUE NOT NULL, file_name TEXT NOT NULL, content_type TEXT NOT NULL, created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TABLE IF NOT EXISTS toppings (topping_id SERIAL PRIMARY KEY, topping_name TEXT NOT NULL, topping_type TEXT NOT NULL, is_available SMALLINT NOT NULL DEFAULT 1);
CREATE TABLE IF NOT EXISTS syrups (syrup_id SERIAL PRIMARY KEY, syrup_name TEXT NOT NULL, syrup_type TEXT NOT NULL, is_available SMALLINT NOT NULL DEFAULT 1);
CREATE TABLE IF NOT EXISTS fruits (fruit_id SERIAL PRIMARY KEY, fruit_name TEXT NOT NULL, is_available SMALLINT NOT NULL DEFAULT 1);
INSERT INTO packages (package_number, package_name, price, description)
VALUES (1,'Package 1',10999,'Yogee event package 1'),(2,'Package 2',13999,'Yogee event package 2'),(3,'Package 3',16999,'Yogee event package 3')
ON CONFLICT (package_number) DO NOTHING;
