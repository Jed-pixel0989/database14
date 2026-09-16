# InfinityFree deployment

1. Create a MySQL database in the InfinityFree Control Panel. Do not use `root`, and do not try to create the database from PHP; InfinityFree supplies a prefixed database name, username, host, and password.
2. Copy `config/database.local.example.php` to `config/database.local.php` and replace all five values with the credentials shown by InfinityFree.
3. Upload the project contents into `htdocs` (or a subfolder). The application now derives its URL automatically, so a folder name does not need to be `database14`.
4. Open `setup.php` once. The installer creates tables and seed data inside the database selected in `database.local.php`; it does not issue `CREATE DATABASE` or `DROP DATABASE` commands.
5. For manual phpMyAdmin import, use `database/database.sql`. It contains table and seed statements only and is safe to import into the database already created by InfinityFree.
6. Delete or protect `setup.php` after initialization if the site is public. Re-running setup resets the application tables and demo data.

## Schedule blocks

The final scheduling endpoint now validates that the selected blocks exist, belong to the selected clearance, and have capacity. Re-saving a student's schedule releases the student's old block slots before adding the new blocks, and the database has a unique key that prevents duplicate block assignments.
