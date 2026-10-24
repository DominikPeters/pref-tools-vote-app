-- Persist a recipient's preferred interface/email locale independently of poll language.
ALTER TABLE users ADD COLUMN preferred_locale VARCHAR(16) DEFAULT NULL;
