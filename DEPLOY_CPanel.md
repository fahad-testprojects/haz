# cPanel deployment and database upgrade

1. Back up the existing site files and database in cPanel/phpMyAdmin before upgrading.
2. Upload the changed application files, including `index.html`, `sw.js`, `vendor/qrcode.js`, `api/extras.php`, and any changed admin files, preserving their directory paths. Do not overwrite `api/db_config.php` with credentials from another server.
3. In phpMyAdmin select the **existing HazariVai database** and use Import to upload `cpanel_migration.sql`. This adds the PIN column/index if missing and creates the optional `match_reactions` table; it does not delete existing data. It is safe to import again. Do not import the old sample database dump over your live data.
4. Reload the application online and refresh once more if the old service worker still shows the previous version. Test the QR viewer, a completed match, cloud leaderboard, and reactions on two devices.

The leaderboard is public and groups finished matches by exact player name. Series summaries and the detailed match archive use the local device's recorded matches. Reactions require the new table and an online server; speech on receiving devices depends on browser voice/audio permissions and the auto-announcer setting. Wake lock and image sharing depend on browser support. The SQL file upgrades an existing installation; it is not a full database backup or a fresh installation schema.
