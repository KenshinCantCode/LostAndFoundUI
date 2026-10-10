# Hosting configuration and secrets

The application reads settings from server environment variables first, then
from a PHP private config file, and finally from `.env` for local development.
Never commit real credentials or put them in source code.

## InfinityFree

For the preferred setup, create `private-config.php` one directory above the
website's document root (for example, next to `htdocs`, not inside it). Copy
the example settings from `config/private.example.php` into that file, replace
the placeholder values, and upload the file using your hosting file manager or
FTP. The app loads that file directly from PHP; it is not served as a public
website file. Restrict its filesystem permissions if the host allows it.

If the host prevents PHP from reading a file outside the document root, copy
the example settings into `config/private.php` instead. Do not replace
`config/config.php`: it is the application's configuration loader. The
`config/.htaccess` file blocks direct web requests to that directory. Do not
remove that access rule.

The project root `.htaccess` also denies direct requests for `.env`, database
PHP files, and the SQL dump. These web-server rules are an extra safeguard, not
a replacement for keeping credentials out of source control.

## Friends and private messages

When adding this feature to an existing database, select the application's
database in phpMyAdmin and import `social.sql` once. Do not import `bugas.sql`
into a database that already contains the application tables; it is the full
initial-install schema. Deploy `friends.php`, `conversation.php`, and the
updated `includes/header.php` alongside the rest of the application.

## Admin approval of item reports

For an existing installation, select the application database in phpMyAdmin
and import `approval.sql` once. Existing reports remain approved; new reports
and reports edited by their owners wait for admin review. New installations
already have the approval columns in `bugas.sql`. Deploy the updated report,
search, item, claim, and admin files together.

## Rotate credentials

Credentials that have ever been pasted into source code or shared should be
treated as compromised. Change the database password in the hosting control
panel and update the SMTP app password with its provider, then update the
private config file. Do not reuse either old password.
