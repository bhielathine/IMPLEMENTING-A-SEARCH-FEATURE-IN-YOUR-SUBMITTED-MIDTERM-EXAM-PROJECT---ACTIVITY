WEBPORTAL_ACCOUNT - ACCOUNT RECORD KEEPING / SELLING PORTAL  (PHP + PDO + MySQL)

SETUP (XAMPP)
1. Copy this folder to C:\xampp\htdocs\webportal_account
2. Start Apache and MySQL.
3. NEW install: phpMyAdmin > Import > database/schema.sql > Go.
   EXISTING database without date_added: Import > database/upgrade_date_added.sql > Go.
4. config/config.php: keep BASE_URL as /webportal_account, change APP_KEY.
5. Open http://localhost/webportal_account/

SEARCH + UPDATE/DELETE (this update)
  PARENT (users):   members.php has a search box (name or username) ->
                     member_edit.php updates OR deletes the found record.
                     updated_by / last_updated change to the CURRENT logged-in
                     user and NOW() on every save.
  CHILDREN (accounts): seller/records.php has a search box (pseudonym, game,
                     or description) -> seller/record_form.php updates that
                     record (Delete button is on the records table itself).
                     Same updated_by / last_updated stamping.

DATE ADDED
  users.date_added    - set automatically (DEFAULT CURRENT_TIMESTAMP) when a
                         user registers or is added as a member.
  accounts.date_added - set automatically when a record is inserted.
  Shown on: members.php / member_edit.php / profile.php (users) and
  seller/records.php / seller/record_form.php (accounts).

TRACKING COLUMNS (both entities)
  added_by     - user_id of whoever inserted the record
  updated_by   - "last_updated_by": user_id of whoever last updated it
  date_added   - timestamp set once, at insert
  last_updated - timestamp refreshed on every update

XSS PROTECTION: includes/functions.php -> e() wraps htmlspecialchars();
every user-supplied value is printed through it (see comments in
market/view.php, seller/records.php, seller/record_form.php).

PASSWORD POLICY: includes/functions.php -> validate_password_strength():
min 8 characters, 1 lowercase, 1 uppercase, 1 number, enforced server-side.

ONE-TO-MANY: users (PARENT) 1 --- * accounts (CHILDREN)
