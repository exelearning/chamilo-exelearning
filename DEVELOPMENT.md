# Development

## Requirements

- Docker (Docker Desktop: give it at least 4 GB of RAM)
- PHP 8.3+ and Composer, for the unit tests and linters
- Node 20+ and npm, for Vitest, ESLint and Playwright
- [Bun](https://bun.sh), only to build the embedded editor

## Local environment

```bash
make up           # builds the image, installs Chamilo 3.0.2, enables the plugin, seeds demo data
```

The first run pulls the Chamilo image and takes about a minute after that. Then open
<http://localhost:8080>:

| User | Password | Role |
|---|---|---|
| `admin` | `admin-exelearning` | platform administrator |
| `teacher` | `teacher` | teacher of the *eXeLearning demo* course |
| `student` | `student` | student of that course |
| `outsider` | `outsider` | student, not enrolled (for denied-access checks) |

The course tool is at `/plugin/ExeLearning/start.php?cid=3`. These credentials are for local use only.

How it works:

- **Image:** `docker/Dockerfile` extends [`erseco/alpine-chamilo`](https://erseco.github.io/alpine-chamilo/) (Alpine, nginx,
  PHP 8.3, Chamilo from the stable release). Set `CHAMILO_VERSION` in `.env` to another published tag.
- **Unattended install:** done by the base image (`CHAMILO_AUTO_INSTALL=true`). Data lives in the `chamilo_state`,
  `chamilo_config`, `chamilo_var` and `db_data` volumes, so `make down && make up` keeps everything.
- **Every boot:** `docker/30-plugin-autoload.sh` runs `composer dump-autoload`, because plugin classes are in Chamilo's
  classmap. That is the only thing this image adds to the base, besides Composer itself.
- **Seed:** `make seed` (`scripts/seed.php`) installs and enables the plugin for the default access URL, creates the users and
  the course, adds the tool, and uploads `tests/fixtures/sample.elpx`.
- **Mount:** the repository is mounted at `public/plugin/ExeLearning`. `vendor/`, `node_modules/` and `exelearning/` are masked
  inside the container, so Chamilo's classmap never scans them.

Other targets: `make down`, `make logs`, `make shell`, `make clean` (drops the data volumes), `make destroy` (everything).
`make help` lists them all.

## Embedded editor

```bash
make build-editor      # fetches exelearning/exelearning at .editor-version (v4.0.6) and builds dist/static/
```

`dist/static/` and the `exelearning/` checkout are gitignored and never edited by hand. Without `dist/static/` the plugin works in
player-only mode. Override the source with `EXELEARNING_EDITOR_REPO_URL`, `EXELEARNING_EDITOR_REF` and
`EXELEARNING_EDITOR_REF_TYPE` (`auto|branch|tag`) in `.env`.

## Checks

```bash
make install-deps        # composer + npm
make lint                # ECS with Chamilo's own ruleset (read-only) + ESLint; `make fix` applies fixes
make phpmd               # PHPMD 3; on a PHP 8.5 host it can exit 0 without reporting: trust CI (PHP 8.3)
make test                # PHPUnit unit suite (no Chamilo needed); FILTER=Name to narrow
make test-coverage       # with the MIN_COVERAGE ratchet (never lower it)
make test-js             # Vitest: editor bridge
make test-integration    # inside the container: install, course/session/user deletion; ARGS=--uninstall
make test-e2e            # Playwright against make up
make check-untranslated  # lang/*.php against lang/en_US.php
make package VERSION=0.1.0 && make check-package VERSION=0.1.0
```

## Layout

| Path | What |
|---|---|
| `plugin.php`, `install.php`, `uninstall.php` | Chamilo plugin entry points |
| `start.php` | Course tool: list, upload, rename, show/hide, delete |
| `view.php`, `content.php` | Viewer page and the token-gated content endpoint (`content.php/<id>/<hash>/<token>/<path>`) |
| `edit.php`, `editor.php`, `save.php` | Editor host page, editor iframe bootstrap, save endpoint |
| `download.php` | Original `.elpx` (editors; also what the editor opens) |
| `src/ExeLearningPlugin.php` | Plugin class: Chamilo glue only |
| `src/Service/` | Validation, storage, headers, tokens, permissions, editor bootstrap (unit-tested) |
| `src/Entity/`, `src/EventSubscriber/` | Doctrine entity; course/session cleanup subscriber (global namespace on purpose) |
| `resources/` | CSS and JS (viewer, editor bridge on both sides) |
| `lang/` | `en_US.php` (source), `es.php` |
| `tests/` | `Unit/`, `Integration/run.php`, `js/`, `e2e/`, `fixtures/` |
