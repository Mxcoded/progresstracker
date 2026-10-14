VALINO PMS GO-LIVE TRACKER — DEPLOYMENT NOTES
=============================================

WHAT THIS IS
- index.html: one-page browser dashboard.
- api.php: PHP backend for login, shared data and updates.
- config.php: database credentials and shared access code (create from config.sample.php).
- schema.sql: MySQL tables and starter checklist.

IMPORTANT
An HTML file alone cannot securely share updates across devices. This package includes a small PHP/MySQL backend.
The dashboard polls the server every 5 seconds, so changes appear on other signed-in devices after the next refresh.
The access code is checked server-side. This is a shared-code gate, not individual staff identity or a full enterprise authentication system.
For more sensitive use, add individual accounts and an audit-log table.

DEPLOYMENT ON CPANEL
1. In cPanel, create a MySQL database and a database user. Assign the user all privileges on that database.
2. Open phpMyAdmin, select the new database, and import schema.sql.
3. Upload index.html and api.php to the same directory, for example public_html/valino-tracker/.
4. Copy config.sample.php to config.php using File Manager, then set DB_HOST, DB_NAME, DB_USER, DB_PASS and a long unique TRACKER_ACCESS_CODE.
   If you cannot copy/rename through File Manager, create config.php manually using the sample as a template.
5. Ensure the directory is served over HTTPS. Do not share the access code over a public group.
6. Visit https://your-branch-domain.example/valino-tracker/ and sign in with the shared code and your name/department.
7. Test from two separate devices/browsers: change a test task on one, and confirm the other shows the update within about 5 seconds.
8. Keep a backup of the database before any major change.

SECURITY AND OPERATING NOTES
- Use HTTPS only.
- Set a strong, unique shared code and change the sample value before launch.
- Do not place real guest names, room-level guest personal data, payment card data or sensitive financial details in task notes.
- Do not expose config.php contents; PHP executes it server-side, but confirm the host is configured to run PHP.
- If the API reports a server/database error, check PHP error logs and database settings in config.php.
- Set correct cPanel file permissions; normally files 644 and directories 755.
- Consider restricting access with cPanel Directory Privacy or an IP allowlist if appropriate.
- The dashboard is designed for an internal implementation checklist, not a substitute for Valino or the hotel's official accounting records.
