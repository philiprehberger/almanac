-- Append-only audit log (PostgreSQL).
--
-- The application connects as a least-privilege role that can INSERT and read
-- audit_events but never UPDATE, DELETE, or TRUNCATE them — so a compromised
-- app cannot rewrite or erase its own audit trail. Run this once, as the table
-- owner or a superuser, AFTER migrations have created audit_events. Replace
-- :app_role with the role in your .env DB_USERNAME.
--
--   psql "$DATABASE_URL" -v app_role=almanac_app -f infra/db/grants.sql

REVOKE UPDATE, DELETE, TRUNCATE ON audit_events FROM :app_role;
GRANT INSERT, SELECT ON audit_events TO :app_role;
