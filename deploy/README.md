# Deploying Digital LEAP

The application is plain PHP 8 with no Composer dependencies and no build
step. `digital-leap/` is the whole application; `database/digital_leap.sql`
is a fresh-install schema and demo seed.

Pick one of the four shapes below. All of them end with the same three
steps: create `config/config.php` from the sample, import the schema into an
empty database, and open the site through its site root (not by opening
`index.php` from disk, because the links and the service worker are
root-relative). The Codespaces shape in section 4 does all three for you.

## 1. XAMPP or shared Apache

1. Copy `digital-leap/` to `C:\xampp\htdocs\digital-leap` (or into your
   hosting account's web space).
2. Import `database/digital_leap.sql` in phpMyAdmin or with
   `mysql -u root < database/digital_leap.sql`. The script drops and recreates
   every application table, so use it only on a disposable installation.
3. `copy config\config.sample.php config\config.php`.
4. Make sure Apache loads `mod_rewrite` and `mod_headers` and that
   `AllowOverride All` applies to the folder; the shipped `.htaccess` files
   rewrite routes and deny `app/`, `bin/`, `config/` and `storage/`.
5. Open `http://localhost/digital-leap/`.

If the folder is served under a different URL, leave `app.base_url` empty.
The application derives it from the request. Set it only when a proxy hides
the real path, for example `'base_url' => 'https://leap.example.sl'`.

The XAMPP-free alternative is the built-in server:

```
C:\xampp\php\php.exe -S 127.0.0.1:8080 router.php
```

`router.php` blocks the same private folders as the `.htaccess` files.

## 2. Docker

`deploy/Dockerfile` builds a PHP 8.3 + Apache image with `pdo_mysql`,
`mbstring` and `zip`, the document root at `digital-leap/`, and
`deploy/apache-vhost.conf` as the default virtual host. `deploy/php.ini`
raises the upload limits to match the 5 MB evidence limit and 8 MB photo
limit and turns off `display_errors`.

The image contains no configuration file; mount one.

1. Create the config:

   ```
   cp digital-leap/config/config.sample.php digital-leap/config/config.php
   ```

   Set `db.host` to `db`, keep `db.user` as `root`, and set `db.pass` to the
   `MARIADB_ROOT_PASSWORD` value in `deploy/docker-compose.yml`
   (`change-me` by default). Change that password before exposing the stack.

2. Start the stack:

   ```
   docker compose -f deploy/docker-compose.yml up --build
   ```

3. Open `http://localhost:8080/`.

The compose file imports `database/digital_leap.sql` only when the database
volume is empty. To reset the demo, `docker compose -f deploy/docker-compose.yml down -v`
and start again; this deletes the database volume and uploads, so do it only
on a disposable stack.

Without compose:

```
docker build -f deploy/Dockerfile -t digital-leap .
docker run -p 8080:80 \
  -v "$PWD/digital-leap/config/config.php:/var/www/html/digital-leap/config/config.php:ro" \
  digital-leap
```

Then import the schema into your own MariaDB host and point `db.host` at it.

**Not yet built here.** The image was authored and syntax-checked with the
rest of the repository, but no Docker engine was available on the build
machine, so `docker build` has not been executed. Treat the first build as
unverified until it runs on a machine with Docker; report anything that
fails rather than editing around it silently.

## 3. GitHub checkout

```
git clone <repository-url> digital-leap-project
cd digital-leap-project
cp digital-leap/config/config.sample.php digital-leap/config/config.php
```

Then import `database/digital_leap.sql` and serve `digital-leap/` as in
section 1. `config/config.php` is ignored by `.gitignore`; if an older
commit already tracks it (this repository also tracks
`storage/logs/php-error.log`), the owner removes those paths once with
`git rm --cached digital-leap/config/config.php digital-leap/storage/logs/php-error.log`
and rotates the password the configuration contained. A fresh clone
therefore never carries someone else's database password.

`php bin/build-diva-index.php` regenerates
`public/assets/data/diva-index.json` from `storage/knowledge/guidebook.txt`.
Run it only when that text extract changes; the shipped index already matches
the shipped guidebook. `php bin/build-diva-corpus.php` does the same from the
PDF and needs `pdftotext`, which is a development tool only.

`.github/workflows/lint.yml` runs on every push and pull request: `php -l`
over every PHP file on PHP 8.0 and `node --check` over every JavaScript file.
It needs no secrets and no database.

## 4. GitHub Codespaces (shareable demo link)

`.devcontainer/` starts the same PHP 8.3 + Apache image as section 2, adds
MariaDB, and imports `database/digital_leap.sql` the first time the container
is created. The application is published on port 8080 and the devcontainer
marks that port **public**, so a forwarded link opens the running app without
a GitHub account. Nothing is installed on the viewer's machine and no
external service is called.

The stack mounts `.devcontainer/config.codespaces.php` as
`config/config.php`. Its only credential is the disposable `leap-demo`
password declared beside it in `.devcontainer/docker-compose.yml`; a
developer's own `config/config.php` is never involved. The application code
is bind-mounted, so an edit in the codespace is served on the next reload,
and `storage/` is a named volume owned by `www-data`.

1. Open
   `https://codespaces.new/EssenceShady/Digital_LEAP_Assignment3_Group_G`
   and choose **Create codespace**. The first start builds the image and
   imports the seed; allow a few minutes.
2. When the editor opens, port 8080 is forwarded automatically. If the
   browser page did not open, go to the **Ports** tab, right-click port
   8080 and choose **Open in Browser**.
3. The address `https://<codespace-name>-8080.app.github.dev/` is the link to
   share. Because the port is public, keep the URL inside the group; set the
   port to **Private** in the **Ports** tab if only signed-in GitHub users
   should reach it.
4. Sign in with any demo account, for example the coordinator
   `bayohmusa001@gmail.com` / `Musa@2026`.

Limits worth stating plainly:

- A codespace stops after its idle timeout (30 minutes by default) and the
  public link stops answering until the codespace is started again. This is a
  demo convenience, not permanent hosting; the free monthly quota applies.
- The database and uploads live in named volumes. **Rebuild Container** keeps
  them; delete the codespace to discard them, and a new codespace re-imports
  the seed.
- HTTPS is terminated by GitHub's forwarder, so the installable app and the
  service worker work over the forwarded URL.

## HTTPS and the installable app

The service worker, and therefore the installable offline app, requires a
secure context: `https://` or `http://localhost`. Over plain HTTP on a
network address the pages still work, but the browser will not offer
**Install app** and offline pages will not be cached.

- Terminate TLS at Apache, or at a reverse proxy in front of it. Send
  `X-Forwarded-Proto: https` if the proxy terminates TLS, and set
  `app.base_url` to the public HTTPS URL so links stay on HTTPS.
- `bootstrap.php` adds HSTS and marks the session cookie `Secure` whenever
  PHP sees the request as HTTPS.
- Browsers validate the certificate chain. A self-signed certificate is
  rejected for service-worker registration on all current browsers, so use a
  certificate the browser trusts, or test on `localhost`.
- After a deploy that changes cached files, the service worker's version
  string in `sw.js` must be bumped; the app otherwise serves the previous
  shell until the old cache is replaced.

## Upgrading an existing installation

`database/digital_leap.sql` is for fresh installs only. For a database that
already holds work, apply the additive scripts in `database/migrations/` in
date order, then deploy the matching code. Current migration:
`2026-11-20_sync_op.sql` adds the offline-sync queue table. Nothing in the
migration path drops a table or deletes a row.

## Checks after any deployment

1. Sign in as the coordinator (`bayohmusa001@gmail.com` / `Musa@2026`) and
   confirm the dashboard loads without a database error.
2. Open **Ask DIVA** and ask "How is my progress calculated?" — a guidebook
   quote with a page number means the text extract and index are present.
3. Open the browser's Application panel and confirm `sw.js` is active and
   `manifest.webmanifest` is fetched as `application/manifest+json`.
4. `php bin/diva-eval.php` should print 74/74 against the seeded database.
