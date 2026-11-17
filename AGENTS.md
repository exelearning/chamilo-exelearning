# AGENTS.md

Guidance for coding agents working on **chamilo-exelearning**: the Chamilo LMS 3 plugin that lets
teachers upload, view and edit eXeLearning v4 packages (`.elpx`) in courses. Sibling of
wp-exelearning, mod_exelearning, omeka-s-exelearning and nextcloud-exelearning.
Everything in the repository is in English.

## How it works

| Area | Files |
|---|---|
| Plugin entry points | `plugin.php`, `install.php`, `uninstall.php`, `config.php` (boots Chamilo via `main/inc/global.inc.php`) |
| Plugin class (Chamilo glue only) | `src/ExeLearningPlugin.php` (global namespace, `create()` singleton) |
| Validation (ZIP safety, ODE 2.0, legacy `.elp`) | `src/Service/PackageValidator.php`, `PackagePath.php`, `PackageMetadata.php` |
| Storage (Flysystem `plugins` storage, atomic versions) | `src/Service/PackageStorage.php` |
| Content delivery and headers | `content.php`, `src/Service/ContentPolicy.php`, `ContentToken.php` |
| Permissions (pure functions) | `src/Service/AccessPolicy.php` |
| Embedded editor | `edit.php` (host), `editor.php` (iframe bootstrap), `save.php`, `src/Service/EditorBootstrap.php`, `resources/js/editor.js`, `resources/js/editor-bridge.js` |
| Course tool UI | `start.php`, `view.php`, `download.php`, `resources/` |
| Data model | `src/Entity/ExeLearningPackage.php` → table `plugin_exelearning_package` |
| Cleanup | `src/EventSubscriber/ExeLearningCleanupEventSubscriber.php` |
| Strings | `lang/en_US.php` (source), `lang/es.php` |

## Endpoints and permissions

| Endpoint | Method | Who |
|---|---|---|
| `start.php` | GET | course users; non-editors only if the tool is visible |
| `start.php` (`action=upload/rename/show/hide/delete`) | POST + CSRF | editors, and only for packages of the current course/session |
| `view.php?id=` | GET | course users; non-editors only for visible packages |
| `content.php/<id>/<sha256>/<token>/<path>` | GET | the logged-in user the token was issued to by `view.php` |
| `download.php?id=` | GET | editors |
| `edit.php`, `editor.php` | GET | editors, embedded editor enabled |
| `save.php` | POST + CSRF | editors, embedded editor enabled, package owned by the context |

"Editors" = `AccessPolicy::canEdit()`: platform admins always; otherwise the `edit_roles` setting
(teachers by default). Ownership = same course **and** same session (`AccessPolicy::ownsPackage()`).

## Data model

One row per package: course (FK, cascade), session (nullable FK, cascade), creator (nullable FK,
`SET NULL`), title, metadata from `content.xml`, `content_hash` (SHA-256 of the current `.elpx`), size,
visibility. Files: `ExeLearning/c<course>/p<id>/<sha256>.elpx` and `.../<sha256>/` in the plugins storage.

## Project boundaries

- Target Chamilo **3.x only** (`chamilo-lms` v3.0.2+, PHP 8.3+). Never patch Chamilo core or vendor it.
- Use only Chamilo APIs you have read at the pinned version. Plugin scripts are plain PHP pages, not
  Symfony routes; their POSTs check `Security::check_token('post', null, 'exelearning')` themselves.
- Event subscribers must be in the **global namespace** and named `*EventSubscriber.php`, or Chamilo
  silently ignores them. Entities live under `Chamilo\PluginBundle\ExeLearning\Entity`. New classes need
  `composer dump-autoload` in Chamilo (classmap).
- Package HTML is untrusted author content on the site origin. Do not loosen `ContentPolicy`, do not
  drop the `event.source` + `event.origin` check in `editor.js`, and never take "saved" from a message.
- A save must never replace valid content with invalid content: validate, store the new version
  completely, then switch the row (`ExeLearningPlugin::replacePackage()`).
- `dist/static/` and `exelearning/` are build outputs: never edit or commit them.
- No legacy `.elp`, no eXeLearning Online, no xAPI, no learning-path integration in 0.1.x (see the design).

## Local environment

`make up` → Chamilo 3.0.2 at http://localhost:8080 with the plugin installed and enabled, users
`admin/admin-exelearning`, `teacher/teacher`, `student/student`, `outsider/outsider`, course `EXEDEMO` (id 3) and a
sample package. `make build-editor` builds the editor (needs Bun). Details: `DEVELOPMENT.md`.

## Verification

| Change | Run |
|---|---|
| any PHP | `make lint`, `make phpmd`, `make test` |
| `src/Service/` | `make test-coverage` (MIN_COVERAGE ratchet: raise, never lower) |
| `resources/js/` | `make test-js`, `make lint` |
| pages, entity, subscriber, install | `make up`, `make test-integration`, `make test-e2e` |
| strings | `make check-untranslated` |
| packaging | `make package VERSION=X.Y.Z && make check-package VERSION=X.Y.Z` (source keeps `VERSION = '0.0.0'`; releases follow the editor version, see `DEVELOPMENT.md`) |
| workflows | `actionlint` |

Report what you ran and its output. A check you skipped is reported as skipped.

## Working conventions

- Branches `feature/...` or `hotfix/...` into `main`; small commits; no AI attribution trailers.
- Run Chamilo's rule 13 OWASP checklist (CSRF, access control per resource, SQL parameters, XSS,
  open redirects) on every new or changed endpoint and record the result in the PR.
- Every UI string goes through `lang/` with `en_US` and `es`; settings get a `<name>_help` string.
- Design and decisions: `docs/superpowers/specs/2026-11-16-chamilo-exelearning-design.md`.
