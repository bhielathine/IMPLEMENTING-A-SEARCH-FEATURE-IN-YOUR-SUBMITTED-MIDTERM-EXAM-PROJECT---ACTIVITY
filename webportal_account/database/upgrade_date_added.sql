-- Run this ONLY if your database already exists WITHOUT a date_added column.
USE account_portal;

-- Parent entity: users already has created_at in older installs; this adds
-- date_added as the column name requested by the activity, backfilled from
-- the existing created_at value so no history is lost.
ALTER TABLE users
    ADD COLUMN date_added TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER updated_by;
UPDATE users SET date_added = created_at WHERE created_at IS NOT NULL;
-- (keep created_at too, or drop it with: ALTER TABLE users DROP COLUMN created_at;)

-- Children entity: accounts had no creation-timestamp column at all.
ALTER TABLE accounts
    ADD COLUMN date_added TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER updated_by;
-- existing rows get "now" as their date_added, since the real creation time was never recorded

ALTER TABLE accounts ADD FULLTEXT INDEX idx_search (pseudonym, description, featured_items);
