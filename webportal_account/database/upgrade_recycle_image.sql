-- Run this if your accounts table does not yet have image_path / deleted_at / deleted_by.
USE account_portal;

ALTER TABLE accounts
    ADD COLUMN image_path VARCHAR(255) NULL AFTER user_notes,
    ADD COLUMN deleted_at DATETIME NULL,
    ADD COLUMN deleted_by INT NULL,
    ADD CONSTRAINT fk_acc_deleted FOREIGN KEY (deleted_by) REFERENCES users(user_id) ON DELETE SET NULL;
