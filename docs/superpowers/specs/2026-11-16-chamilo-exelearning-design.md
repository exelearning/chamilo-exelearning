# chamilo-exelearning — Phase 0 design

- Date: 2026-11-16
- Status: **Approved** ("continúa hasta tener funcionando el plugin"); v0.1.0 implemented on `feature/plugin-v0.1.0`
- Target: Chamilo LMS `v3.0.2` (PHP `^8.3`, Symfony 7.4, Doctrine ORM 3, API Platform 4)
- Editor: `exelearning/exelearning` `v4.0.6` (the value all four siblings pin in `.editor-version`)

Why this lives in `docs/superpowers/specs/` and not `docs/architecture/`: in every sibling,
`docs/architecture/` holds ADRs and change records whose ID is the number of the PR that carries
them (`ADR-<pr>-<nn>-<slug>.md`, validated by the upstream `architecture-records.mts`). This document
predates any PR and covers many decisions at once, so it does not fit that contract. Once it is
approved, step 9 turns its decisions into individual ADRs carried by real PRs, and this file stays as
the design history.

Evidence convention: `chamilo:` paths are relative to `chamilo-lms@v3.0.2`; sibling paths are
prefixed `wp:`, `moodle:`, `omeka:`, `nc:` (relative to each repository's `main`, read on 2026-11-16).

---

## 1. Convention matrix

| Concern | wp-exelearning | mod_exelearning (Moodle) | omeka-s-exelearning | nextcloud-exelearning | Family / specific |
|---|---|---|---|---|---|
| Ingestion | Server-side ZIP, `content.xml` required, `contentv3.xml` rejected; 10 000 entries, 1 GB uncompressed (filters); unsafe names rejected (`\`, leading `/`, stream wrappers, `..`, `.htaccess`, `php*`); two-pass validate-then-write; symlinks written as plain files; no ratio limit | `.elpx`/`.zip` with root `content.xml` (rejects legacy); Moodle file API extraction; no count/size/ratio caps beyond upload limit; requires `index.html` | `ZipSafety`: validate all entries before writing, zip-slip, PHP/server deny-list, 50 000 entries, 1 GiB written; **accepts** `contentv3.xml` (legacy not rejected); no ratio/symlink check | No server extraction: browser unzips (fflate, 5 000 entries, 500 MiB); path rule "validate, never rewrite" (ADR-96-01) | **Family:** validate everything before writing, content.xml = v4. **Gap in all four:** compression ratio, explicit symlink rejection |
| Storage | `uploads/exelearning/<sha1>/` (web-reachable, `.htaccess` blocks HTML/SVG/XML) | Moodle filestore `content/{revision}` (not web-reachable) | `<files>/exelearning/<sha1>/` (web-reachable, `.htaccess` + nginx 403 snippet) | Original file only; SW serves from memory | **Specific.** Two of four rely on web-server rules to block direct access |
| Delivery | REST proxy `/exelearning/v1/content/{hash}/{file}`, `realpath` containment, `nosniff`, CSP, sandbox-CSP for SVG/XML | `pluginfile.php`, login + capability, **no CSP/sandbox** (TRACKING.md threat #10) | `/exelearning/content/{40hex}/{file}`, `nosniff`, HTML CSP, sandboxed CSP for SVG/XML, **no authorization** (hash = capability) | Service Worker scoped to `/apps/exelearning/runtime/`; PHP fallback refuses `Sec-Fetch-Dest: document` | **Family:** path-based proxy keyed by hash, `nosniff`, distinct HTML vs SVG/XML policy |
| Iframe | `allow-scripts allow-same-origin allow-popups allow-downloads`, site origin | adds `allow-forms allow-popups-to-escape-sandbox`, `allow="fullscreen"` | same set as Moodle minus `allow-forms` | same set as Moodle | **Family:** `allow-same-origin` kept and documented as *not* a security boundary |
| Editor | `dist/static/` in release ZIP; injected `__EXE_EMBEDDING_CONFIG__`, `<base href>` | same; editor's own `EmbeddingBridge` protocol (`EXELEARNING_READY`→`CONFIGURE`, `REQUEST_EXPORT`→`EXPORT_FILE`) | same bundle; own message names (`exelearning-request-save`, …) | same bundle; `trustedOrigins` = own origin | **Family:** prebuilt `dist/static` pinned by `.editor-version`, release-only (omeka ADR-28-01) |
| Bridge checks | parent checks `origin` **and** `source` | parent checks both (`moodle:amd/src/editor_modal.js:422-430`) | both (`omeka:asset/js/exelearning-editor.js:311-316`) | `source` only, posts `'*'` | **Family:** both checks; NC is the outlier |
| Save | validate+extract new before touching old, per-item lock, copy (not rename), old hash kept as redirect | parent gets bytes in `EXPORT_FILE`, POSTs `FormData`+`sesskey`, lock, stage next revision, advance pointer, prune | iframe POSTs raw bytes with `X-CSRF-Token`, stage, copy with size check, delete old | `If-Match`/etag (412), `putContent` without validation | **Family:** a corrupt save never replaces valid content |
| Player-only | no | `exelearning/editordisabled` (DEC-108-01) enforced on bootstrap, static, save | no | no | Moodle-specific, requested here |
| Tracking | none | SCORM 1.2 only (`window.API` shim in parent, DEC-122-01 retired xAPI); time not captured | none | none | Moodle-specific |
| i18n | WP gettext | Moodle lang strings | gettext `.po/.mo`, CI fails on untranslated | none | Platform-specific |
| Tests | PHPUnit (wp-env), Vitest, Playwright | PHPUnit, Vitest, Behat | PHPUnit with stubs, `MIN_COVERAGE ?= 90` ratchet (ADR-32-01) | PHPUnit, Vitest, enable smoke matrix | **Family:** PHPUnit + Vitest; coverage ratchet from Omeka |
| CI | version-tag pins (`actions/checkout@v7`, `shivammathur/setup-php@v2`, `codecov/codecov-action@v7`, …), daily editor check, architecture-records drift check, weekly `update-agent-skills` | same | same; release split into read-only build job + write publish job | same | **Family** |
| Release | `.distignore` single source of truth, root-anchored (ADR-86-01); `.gitattributes` separate contract | `git archive` + checks | `.distignore`; package fails without `dist/static/index.html` and `.editor-version` | rsync with `.distignore` | **Family** |
| Agent guidance | `CLAUDE.md` = `@AGENTS.md`; `.claude/skills/*` → `../../.agents/skills/*`; vendored skills with `metadata.github-repo/ref/tree-sha` + `.agents/licenses/` | same | same | same (vendored copy lacks provenance) | **Family** |

Upstream skill provenance (from wp's frontmatter): `github-actions-hardening` ← `github/awesome-copilot`,
`security-audit` ← `cloudflare/security-audit-skill`, `playwright-cli` ← `microsoft/playwright-cli`
(wp pins `v0.1.24`; the editor repo pins `v0.1.23`; we take the newer, `v0.1.24`).

## 2. Chamilo mapping

| Family behaviour | Chamilo mechanism | Evidence (`chamilo-lms@v3.0.2`) |
|---|---|---|
| Plugin registration | `plugin.php` + global `ExeLearningPlugin extends Plugin`, `create()` singleton | `public/main/inc/lib/plugin.class.php:72` (ctor `$version, $author, $settings`), `:135` `get_name()` |
| Class loading | Composer classmap on `public/plugin` → `composer dump-autoload` after install | `composer.json` `autoload.classmap` includes `public/plugin` |
| Entities | `src/Entity/` under `Chamilo\PluginBundle\ExeLearning\Entity`, attributes; tables via `SchemaTool` in `install()` | `src/CoreBundle/DependencyInjection/Compiler/PluginEntityPass.php` (namespace from dir name), `src/CoreBundle/Helpers/PluginEntityLoaderHelper.php` (`Entity` dirs at depth ≤ 2) |
| Cleanup | Global-namespace `ExeLearningCourseDeletedEventSubscriber` etc. on `Events::COURSE_DELETED`, `SESSION_DELETED`, `USER_DELETED`; `TYPE_PRE`; guard `AppPlugin::getInstance()->isInstalled()`; users on hard delete only | `src/CoreBundle/DependencyInjection/Compiler/PluginEventSubscriberPass.php` (`*EventSubscriber.php`, `class_exists(<filename>)`), `src/CoreBundle/Event/Events.php:13,16,21`, wiki `Plugin-development.md` "Cleaning up plugin data on deletion" |
| Install / enable | `install.php` → `$plugin->install()`; enabling is a per-access-URL `PluginEntity::enable($accessUrl)`; enabling a course plugin calls `install_course_fields_in_all_courses(true)` | `public/main/inc/lib/plugin.lib.php:252` `AppPlugin::install()`, `public/main/inc/ajax/plugin.ajax.php` (install/enable/disable switch and course propagation) |
| Course tool | `$isCoursePlugin = true` → `createLinkToCourseTool()` creates `tool`/`c_tool` + `ResourceLink`; Vue lists it through `LegacyPluginCourseTool` | `plugin.class.php:541-1043`; `src/CoreBundle/Tool/LegacyPluginCourseTool.php` |
| Settings per URL | `Plugin::get()` reads the configuration of the current access URL | `plugin.class.php:379`, `:1123` `isEnabledForCurrentAccessUrl()` |
| Storage | Flysystem `oneup_flysystem.plugins_filesystem`, `var/upload/plugins`, `visibility: private`, swappable for S3/Azure/GCS | `src/CoreBundle/Framework/Container.php:781` `getPluginsFileSystem()`, `config/packages/oneup_flysystem.yaml:106-151` |
| Legacy page bootstrap | `require_once __DIR__.'/../../main/inc/global.inc.php'` then `api_protect_course_script()`, `api_is_allowed_to_edit()` | `public/main/inc/global.inc.php`; H5pImport `start.php` |
| CSRF for plugin forms | `Security::get_token($prefix)` / `Security::check_token('post', null, $prefix)` | `public/main/inc/lib/security.lib.php:151,249` |
| Translations | `lang/en_US.php`, `lang/es.php`, `$strings[...]`; `get_lang()` via `Plugin::get_lang()` | `plugin.class.php:447`; `public/plugin/H5pImport/lang/{en_US,es}.php` |
| Coding style | Mirror `ecs.php` (ECS: COMMON, CLEAN_CODE, `@Symfony`, PSR-12, `@PhpCsFixer`, `@Symfony:risky`, Yoda for `==`/`===`, `concat_space: none`, `declare_strict_types`) | `ecs.php` |

## 3. Open questions, answered

### 3.1 Routing and CSRF for plugin endpoints

**Answer: plain PHP scripts under `public/plugin/ExeLearning/`, each including `global.inc.php`, as
H5pImport does. The plugin validates its own CSRF token on every POST and does not rely on
`CsrfProtectionListener`.**

- A third-party plugin has no supported way to add Symfony routes. `config/routes` doesn't scan
  `public/plugin`, and only entities and `*EventSubscriber.php` files are picked up by compiler passes.
- `CsrfProtectionListener` (`src/CoreBundle/EventListener/CsrfProtectionListener.php`) guards
  non-safe methods only when the request has a session and no credential header, and then only if:
  - the path starts with `/main/inc/ajax/` (`:173`), **or**
  - the router resolved a `_route` (`:189-201`).
- Plugin scripts are executed by the web server and only then boot the kernel
  (`global.inc.php:95` `$kernel->handle(...)`).
- **Unresolved, to settle by experiment at checkpoint 2:**
  - For a script request, `Request::createFromGlobals()` folds the script name into the base URL, so
    the path info may be `/`. If so, the router could match `IndexController` `/` (`name: 'index'`,
    `methods: GET, POST`), and the listener would *also* run its same-origin check on plugin POSTs.
  - The listener's own docblock claims legacy pages resolve no route.
  - The experiment: POST to a plugin script with a cross-site `Origin`/`Sec-Fetch-Site` and log `_route`.
- **Design consequence (holds either way):** every state-changing plugin endpoint:
  - accepts POST only;
  - calls `Security::check_token('post', null, 'exelearning')`;
  - re-checks capability and resource ownership.

  If the listener also fires, that is defence in depth. Our requests are all same-origin, so it can
  never reject them.

### 3.2 Where extracted packages live

**Answer: `Container::getPluginsFileSystem()` (H5pImport's choice), under
`ExeLearning/c<courseId>/p<packageId>/<sha256>/…` plus the original `.elpx` at
`ExeLearning/c<courseId>/p<packageId>/<sha256>.elpx`.**

- The local adapter writes to `var/upload/plugins`, which is outside `public/`, so it is never
  directly web-reachable. No `.htaccess`/nginx rule is needed for the default install. This beats wp
  and omeka, which both depend on server rules.
- It honours whatever remote storage the admin configured (S3, Azure, GCS adapters at
  `oneup_flysystem.yaml:110-125`). A public directory would break that, and would need server rules.
- **Consequence:** the content endpoint streams through Flysystem (`readStream`); it never builds
  filesystem paths. Containment is enforced on the *key* (validated relative path, no `..`, no
  backslash, no NUL, no leading `/`), not by `realpath`.
- Extraction validates the ZIP in a temp file, then writes each entry with `writeStream` into a new
  `<sha256>` prefix.
- The README still documents a snippet for admins who point the plugins filesystem at a public
  location, since `var/` exposure is a deployment mistake we can't detect.
- H5pImport serves stored files through `package_asset.php?path=…` with only
  `api_block_anonymous_users()`, which has no course check. We do **not** copy that.

### 3.3 Course tool icon and URL in the Vue course home

**Answer: fully automatic, and not customisable without core.**

- `CourseHome.vue` calls `GET /api/c_tools` (`CToolStateProvider`). For a plugin tool it builds
  `LegacyPluginCourseTool::fromLegacyPlugin()` (`src/CoreBundle/Tool/LegacyPluginCourseTool.php`):
  - **URL:** the first of `start.php`, `index.php`, `admin.php` found in the plugin dir.
    `CourseToolDataTranformer` appends `?cid=&sid=&gid=0`. We ship `start.php` (as H5pImport does),
    so the tool links to `/plugin/ExeLearning/start.php?cid=…&sid=…&gid=0`.
  - **Title:** `plugin_title` from our lang file.
  - **Icon:** hard-coded `mdi-puzzle-outline`. The class is `final`, and a custom icon needs a class
    under `src/CoreBundle/Tool/` (as `Zoom.php`). That is a core change, so **v0.1.0 accepts the
    generic puzzle icon.** An upstream PR adding an icon hook is listed as follow-up, not a dependency.
- **Show/hide** (the eye icon) flips the tool's `ResourceLink` between PUBLISHED and DRAFT. It is only
  enforced in the course-home listing (`CToolStateProvider.php:176-190`).
  - `api_protect_course_script()`'s tool check reads a column C2 no longer has (`api.lib.php:991-999`).
  - So **our pages must check tool visibility themselves for non-editors.** H5pImport doesn't.
  - The exact API for reading the `ResourceLink` visibility is to be confirmed at step 3, with a test.
- Third-party plugins are hidden from Administration → Plugins unless `?show_all_plugins=1`
  (`public/main/admin/settings.lib.php:299,405-416`). This affects the README, the E2E flow and the
  checkpoint-2 evidence.

### 3.4 Learning-path item type without patching core

**Answer: no. The `TOOL_H5P` path is not open to an external plugin. In v3.0.2 it is dead even
for H5pImport.**

- **`TOOL_H5P` is not defined anywhere in core:**
  - `grep -rn TOOL_H5P public src` hits only `public/plugin/H5pImport/` (2 uses).
  - Only `TOOL_XAPI` is defined (`api.lib.php:169`).
  - `H5pImportPlugin::getLpResourceBlock()` is never called.
- **Item types are closed allow-lists:**
  - `src/CoreBundle/State/LearningPath/LearningPathBuilderMutationProcessor.php:1045-1054`:
    `match` over document, video, quiz, link, student_publication, forum, thread, survey, and
    `default => throw BadRequestHttpException`.
  - `LearningPathBuilderProvider.php:639-642` and `LearningPathRuntimeProvider.php:752-921` are the
    same, and the runtime returns `''` for unknown types.
  - The legacy `learnpath.class.php` switches have no default case.
- **No event or registry exists:** the only LP events are `LP_CREATED`, `LP_ITEM_VIEWED`, `LP_ENDED`
  (`Events.php:32-34`).
- **Routes that need no core patch:**
  - **(a) SCORM import:** `scorm::import_package()`, `parse_manifest()`, `import_manifest()`
    (`public/main/lp/scorm.class.php:496,88,259`). CStudio uses this.
  - **(b) A `link` item:** a `CLink` pointing at our viewer.
- The trade-off is in §6.2.

### 3.5 Automating a Chamilo 3 install (dev and CI)

**Answer: option (b), a CLI script that replays the web installer's fresh-install branch with
Chamilo's own functions. It is idempotent, and option (a) stays the fallback.**

- **What the installer does:**
  - `public/main/install/index.php:840-980` writes `.env` with `updateEnvFile()`;
  - boots `Kernel`;
  - runs `doctrine:schema:create`;
  - seeds migration metadata (`doctrine:migrations:sync-metadata-storage`, then
    `doctrine:migrations:version --add --all`);
  - runs `doctrine:fixtures:load`;
  - calls `finishInstallationWithContainer(…21 args…)` and `chamilo:remote-storage:upload-themes`.
- **All of these are callable from a CLI script** that requires `install.lib.php` and Chamilo's
  autoloader. That uses core without patching it.
  - `global.inc.php:43-71` already has a CLI branch that sets up a request for legacy APIs.
  - The seed uses that branch afterwards.
- **Why not (a):**
  - Chamilo's CI drives the installer with Playwright (`tests/playwright/features/actionInstall.feature`).
  - Playwright's browsers don't run on Alpine (musl), so (a) would need a ~2 GB sidecar
    (`mcr.microsoft.com/playwright`) and a minute of clicking at every first boot.
- **Idempotency:**
  - The script exits early if `.env` has `APP_INSTALLED=1` and `isAlreadyInstalledSystem()` (`install.lib.php:50`) agrees.
  - `.env` lives in a named volume, so `make down && make up` is a no-op; `make destroy` resets.
- **Risk:** the script copies a call sequence, not a stable API. The `master` CI job (continue-on-error)
  is the drift alarm. If (b) produces an install that differs from a UI install (checked at checkpoint 2
  by logging in, opening admin and course creation), we fall back to (a) with the sidecar.
- **Enabling the plugin unattended:**
  - Mirror `plugin.ajax.php`: `AppPlugin::install('ExeLearning')`, persist `PluginEntity` with
    `setInstalled(true)->setInstalledVersion()`, `enable($accessUrl)`, then
    `install_course_fields_in_all_courses(true)`.
  - Then `composer dump-autoload` and `cache:clear`.
- **Users and course:** created through `UserManager`/`CourseManager` from the CLI bootstrap. Exact
  signatures are to be verified at step 2.
- **CI (GitHub runner):**
  - A note: `.github/actions/setup-chamilo-playwright/action.yml` named in the brief does **not exist at
    `v3.0.2`**; it exists on `master`. At `v3.0.2` the steps are inline in `.github/workflows/playwright.yml`.
  - We reuse **our Docker image** in CI so dev and CI share one install path. MySQL 8.4 runs as a
    service container on `127.0.0.1` and the Chamilo container joins the host network.

### 3.6 Release assets of `chamilo-lms` v3.0.2

**Answer: one asset, `chamilo-3.0.2.zip` (247 MB, 58 450 files, published 2026-11-03), with
built front-end assets.**

- Verified by downloading and listing it. It contains `vendor/` (5 508 entries), `public/build/`
  (`manifest.json`, `entrypoints.json`), `.env.dist`, `composer.json`, `composer.lock` and `bin/console`.
- There is no top-level directory and no `node_modules/`.
- **Consequence:** the Docker image for a tagged version downloads the ZIP and needs no Composer or
  Yarn build. Only the `master` CI entry builds from source (`composer install`, `yarn install`,
  `yarn encore production`). That build is where the ~4 GB RAM note applies; GitHub runners have enough.

## 4. Trust boundaries

Uploaded package HTML is untrusted author content. As omeka ADR-39-02 states, uploading an `.elpx` is
equivalent to running JavaScript on the site. The boundary is therefore the **capability to upload or
edit** (teachers and course admins only). Headers and sandbox are defence in depth, and the docs say so.

| Surface | Policy | Why |
|---|---|---|
| Viewer iframe | `sandbox="allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox allow-forms allow-downloads"`, `allow="fullscreen"`, `referrerpolicy="no-referrer"`, same origin | Family consensus. Packages need storage and relative assets (wp `AGENTS.md:58-62`; omeka ADR-39-02). The future SCORM bridge is same-origin (moodle `docs/TRACKING.md`). `allow-downloads` keeps the in-package download iDevice working (wp ADR-156-01). We do not drop `allow-same-origin` piecemeal; an isolated content origin is a later, whole change |
| Content endpoint, HTML | `Content-Type` from an extension allow-list (never sniffed), `X-Content-Type-Options: nosniff`, `Referrer-Policy: same-origin`, CSP `default-src 'self' 'unsafe-inline' 'unsafe-eval' data: blob:; frame-ancestors 'self'; form-action 'self'; base-uri 'self'; object-src 'none'` (exact string copied from omeka's proxy at implementation), `Cache-Control: private` | Matches omeka and wp; `frame-ancestors 'self'` prevents third-party framing |
| Content endpoint, top-level HTML | If `Sec-Fetch-Dest: document`, add CSP `sandbox allow-scripts` (opaque origin) | From nextcloud's fallback. A package page opened in a new tab cannot read site cookies or storage or call same-origin APIs as the user |
| Content endpoint, SVG/XML | CSP `default-src 'none'; style-src 'unsafe-inline'; sandbox`, `nosniff` | Distinct script-free policy (omeka skill `elpx-editor-boundaries`; wp proxy) |
| Content endpoint, other | Allow-listed MIME types; anything else is `application/octet-stream` + `Content-Disposition: attachment` | No sniffing into HTML |
| Content endpoint, access | Package → its course/session; the user must have course access (subscribed, session member for that course, teacher, or admin); students also need the package and the tool to be visible | Unlike H5pImport (`package_asset.php`: anonymous-block only) and omeka (hash = capability) |
| Editor iframe | Same origin, no sandbox (the editor is our own release-built code), served by `editor.php` with `X-Frame-Options: SAMEORIGIN` and an injected `__EXE_EMBEDDING_CONFIG__ = {parentOrigin, trustedOrigins:[own origin], hideUI}` | Family pattern (moodle, omeka, wp, nc) |
| Bridge (parent) | Accept a message only if `event.origin === location.origin` **and** `event.source === editorFrame.contentWindow`; save-complete is never taken from a message, only from our own `fetch` response | Viewer content is same-origin, so an origin check alone lets a package spoof save or close (omeka skill) |
| Bridge (editor side) | `EmbeddingBridge` checks origin only and falls back to `'*'` when `trustedOrigins` is empty; we always set it | Upstream limitation, recorded |
| Writes | POST-only, `Security::check_token`, capability **and** resource ownership (package's `c_id`/`session_id` must equal the current context), per-package lock | Chamilo rule 13 |
| Direct storage access | Impossible with the default local adapter (`var/upload/plugins` is outside the web root) | §3.2 |

## 5. Phased scope

**v0.1.0 (in):**
- Admin settings (`tool_enable`, `editor_enabled`, `max_upload_size_mb`, `edit_roles`), each with
  a `_help` string.
- Course tool `start.php`: list packages, upload, rename, delete, show/hide, download the original.
- Ingestion and validation (§4, §6.1).
- Metadata extracted from `content.xml`.
- Viewer with fullscreen.
- Content endpoint.
- Embedded editor: create, edit, save with atomic replace and optimistic concurrency (expected hash →
  409), close with unsaved-changes prompt.
- Player-only mode.
- Permissions.
- Install/uninstall.
- Course, session and user cleanup subscribers.
- `en_US` and `es`.
- Docker dev env, CI, release pipeline.
- Agent guidance and ADRs.
- A real screenshot at `.github/screenshot.png`, referenced from `README.md`, taken once the viewer works.

**v0.2.0 (designed now, built only after confirmation):** learning-path integration and SCORM 1.2
tracking (§6.2), then the gradebook link.

**Out:** Chamilo 1.11.x, core patches, a custom course-tool icon (needs core), group-owned packages,
an isolated content origin, eXeLearning Online, xAPI, legacy `.elp`, a PR-preview playground (README note only).

## 6. Approaches considered

### 6.1 Content delivery

| Option | For | Against |
|---|---|---|
| **A. Flysystem `plugins` storage + PHP content endpoint with path-info URLs `content.php/<packageId>/<sha256>/<relpath>` (recommended)** | Never web-reachable; works with remote storage; one place for auth and headers; relative URLs inside packages resolve because the path is real path segments, not a query string; hash in the URL busts caches after each save | Every asset goes through PHP plus a kernel boot (packages are typically < 50 MB of small files; acceptable, `Cache-Control: private, max-age` limits repeats). Needs `PATH_INFO` (see note) |
| B. Extract into `public/plugin/ExeLearning/data/` or `var/` exposed by alias, block HTML/SVG/XML with server rules (wp/omeka style) | Static assets fast | Depends on admins applying nginx/Apache rules (our plugin can't ship an `.htaccess` for nginx); breaks with remote storage; access control only by unguessable hash |
| C. Browser-side unzip + Service Worker (nextcloud) | No server extraction | Large client complexity; SW scope under `/plugin/ExeLearning/` needs a `Service-Worker-Allowed` header from a PHP script; diverges from the PHP siblings |

**`PATH_INFO`:** nginx needs `fastcgi_split_path_info` for `content.php/…`. Chamilo's
recommended configs must be checked at step 5. If they don't pass it, the fallback is
`content.php?p=<packageId>/<sha256>/<relpath>`, with a `<base href>` injected into served HTML. That is
the same rewriting technique the family already uses for the editor's `index.html`. It covers relative
links in HTML but not CSS `url()` references, so path-info stays the preferred form.

### 6.2 Learning-path and tracking integration (v0.2.0)

| Option | For | Against |
|---|---|---|
| **A. "Publish to learning path": the embedded editor exports SCORM 1.2 in the browser (`REQUEST_EXPORT`, SCORM format), the plugin imports it with `scorm::import_package()` / `parse_manifest()` / `import_manifest()` (recommended)** | No core patch; uses Chamilo's native SCORM player, LP progress, score, time and gradebook; matches Moodle's "SCORM 1.2 is the only tracking channel" | The LP is a *copy*: edits need an explicit re-publish (we store the LP id and re-import over it, to be verified); needs the editor (not available in player-only installs: then the teacher uploads a SCORM export manually); the exact export `format` value must be confirmed in `EmbeddingBridge.js` |
| B. `link` LP item pointing at our viewer + our own `window.API` shim recording into a plugin table | Single source of truth | No LP progress, score or gradebook (the runtime treats links as untracked); a parallel tracking store |
| C. Upstream PR to make LP item types pluggable, then a native `exelearning` item | Cleanest long-term | Core change, so out of scope here; nothing ships until a Chamilo release carries it |

Recommendation: A for v0.2.0, and C proposed upstream in parallel without blocking on it.

---

## Decisions made on your behalf (please confirm or override)

1. **Spec location:** `docs/superpowers/specs/` for this document; ADRs go in `docs/architecture/` per PR (reason at the top).
2. **License: keep the AGPLv3 `LICENSE` already committed in `Initial commit`.**
   - wp, omeka, nextcloud and the editor are AGPLv3; Moodle is GPLv3 because Moodle requires it.
   - AGPLv3 and Chamilo's GPLv3+ are compatible under section 13 of both.
   - More decisive: the release ZIP bundles `dist/static/`, which is AGPLv3 editor code, so the package is AGPL-bound regardless.
3. **Storage:** Flysystem `plugins` storage, keyed `ExeLearning/c<cid>/p<id>/<sha256>/`. The directory hash is the SHA-256 of the `.elpx` bytes (wp/omeka use SHA-1 or a random ID).
4. **Content URL:** `content.php/<packageId>/<sha256>/<relpath>` (path info), with the query-string fallback in §6.1.
5. **Top-level navigation:** package HTML opened as a top-level document gets an opaque-origin `sandbox` CSP (taken from nextcloud).
6. **Validation limits** (each overridable by an admin setting later; only `max_upload_size_mb` is a setting in v0.1.0):
   - 10 000 entries, 1 GiB uncompressed, ratio cap 100:1 per entry and overall;
   - symlinks rejected outright (no sibling does this);
   - executable-name deny-list copied from wp/omeka;
   - legacy detection: `contentv3.xml` without `content.xml`, or a `.elp` extension.
7. **Editor protocol:** the editor's own `EmbeddingBridge` messages (Moodle's choice), not Omeka's custom names. The parent posts the exported bytes to `save.php` with the CSRF token.
8. **Concurrency:** saves carry the expected hash and get 409 on mismatch (nextcloud's idea). There is also a per-package lock.
9. **`edit_roles` setting:** a multi-select of `teacher` (course teachers), `session_coach` and `platform_admin`. Platform admins can always manage. Students never edit.
10. **Group context:** packages are course- or session-scoped. In group context (`gid`), group tutors get no extra rights and the group sees the course's packages. Group-owned packages are out of v0.1.0.
11. **Course tool icon:** accept core's fixed `mdi-puzzle-outline`. A custom icon would need a core class.
12. **Tool visibility:** checked inside our pages for non-editors, because core doesn't enforce it outside the listing.
13. **Install automation:** CLI replay of the installer (option b). The Playwright sidecar (option a) is the fallback.
14. **Docker base:** `ghcr.io/erseco/alpine-php-webserver` (nginx + PHP-FPM, `docker-entrypoint-init.d/*.sh`, as in `alpine-omeka-s`).
    - The family tag is `3.24-php84`. PHP 8.4 satisfies `^8.3` and is in our CI matrix.
    - I couldn't list the image's tags (my token lacks `read:packages`). If a `php83` tag exists I'll use it for the 8.3 floor; otherwise `php84`.
    - The Chamilo tarball is the release ZIP for tags, and a source build for `master`.
15. **Dev bind mount:** the repo mounts at `/var/www/chamilo/public/plugin/ExeLearning`, with anonymous volumes masking `vendor/`, `node_modules/` and `exelearning/` inside it.
    - Chamilo's classmap (`public/plugin`) and the subscriber `Finder` scan the plugin directory recursively.
    - Our dev `vendor/` (PHPUnit, ECS) would collide with Chamilo's classes, and the editor checkout contains PHP files (`exelearning/test/e2e/moodle/cli/*.php`).
16. **CI integration:**
    - It runs our Docker image with MySQL 8.4 as a service container on `127.0.0.1`.
    - It installs the **packaged ZIP**, not the working tree, so the release artifact is what gets tested.
    - `master` is built from source with `continue-on-error: true`.
17. **ADR validator path:** the brief says `scripts/architecture-records.mts`.
    - That is the upstream path in `exelearning/exelearning`. Siblings keep their copy at `bin/` (wp), `tools/` (nextcloud) or another local path.
    - I'll use `scripts/architecture-records.mts`, as the brief asks, and the drift check compares it with upstream `scripts/architecture-records.mts`.
18. **`playwright-cli` skill:** taken from `microsoft/playwright-cli` `v0.1.24` (wp's pin).
19. **Coverage gate:** starts at the measured value once the first services exist, never lowered (Omeka's ratchet, ADR-32-01). Codecov is informational.
20. **Version format:** `X.Y.Z`. The single source is `$plugin_info['version']` in `plugin.php`; `check-version` asserts the class and `CHANGELOG.md` agree.

## Contradictions with the brief (the code wins)

- `.github/actions/setup-chamilo-playwright/action.yml` does not exist at `v3.0.2`; it exists only on `master`.
  At the tag, the install steps are inline in `.github/workflows/playwright.yml`. `master`'s action notes
  that MySQL must be a service container on ubuntu-26.04 runners, which we follow.
- H5pImport's learning-path integration is not a working model: `TOOL_H5P` is undefined in v3.0.2 core.
- Brief §1 "Facts" are confirmed. One addition: enabling a course plugin already propagates the tool to
  all courses (`plugin.ajax.php`), and core adds plugin tools on course creation (`CourseHelper.php:985`),
  so we need no `COURSE_CREATED` subscriber for that. H5pImport's subscriber duplicates core.
- Siblings checked in this brief claim things the code doesn't fully show:
  - omeka's `elpx-editor-boundaries` skill says the preview sandbox excludes `allow-same-origin`, but its renderer includes it (ADR-39-02);
  - omeka doesn't reject legacy `.elp`;
  - wp has no dedicated `allow-same-origin` ADR (the rule is in `AGENTS.md:58-62`);
  - no sibling has a `build-editor-if-needed` target except wp;
  - no sibling enforces a compression-ratio limit.

  Our skills will state the behaviour we actually ship.

## Still to verify by experiment (scheduled, not assumed)

| Item | When |
|---|---|
| Whether `CsrfProtectionListener` fires for plugin POSTs (§3.1) | Checkpoint 2 |
| Whether option (b) produces a valid install (§3.5) | Checkpoint 2 |
| `PATH_INFO` passthrough for `content.php/…` on our nginx and Chamilo's documented configs | Step 5 |
| API for `ResourceLink` tool visibility and course-access checks for an arbitrary package course (no `cid` in asset requests) | Steps 3 and 5 |
| `EmbeddingBridge` `OPEN_FILE` payload for loading an existing `.elpx`, and the SCORM export `format` value (v0.2) | Step 6 |
| `UserManager`/`CourseManager` CLI signatures for the seed | Step 2 |

## Experiment results (implementation, 2026-11-16)

| Question | Result | Evidence |
|---|---|---|
| CSRF listener and plugin scripts (§3.1) | **Fires for plugin POSTs.** A cross-site POST to `start.php` (Origin `https://evil.example`, `Sec-Fetch-Site: cross-site`) is turned away before plugin code runs, which matches the router resolving `/` to `index`. A same-origin POST without our token is rejected by `Security::check_token`. Both layers stay | curl probes against `make up` |
| Installer replay (§3.5, option b) | **Valid install.** Login, admin, course creation and plugin enable all work. First `make up` from empty volumes takes 61 s; a second `make up` is idempotent (36 s). The installer leaves `dev` mode, whose cache rebuild exhausts 512 MB, so the script switches to `prod` as Chamilo's CI does | `docker/rootfs/usr/local/bin/chamilo-install.php` |
| `PATH_INFO` (§6.1) | **Works** on the alpine-php-webserver nginx (`location ~ [^/]\.php(/\|$)` + `fastcgi_split_path_info`). Chamilo's router would 404 on the extra path, so `content.php` hides it (`REQUEST_URI` = script name) before booting Chamilo | `content.php` |
| Asset requests have no course context (§3.3 / §4) | Solved with `ContentToken`: `view.php` checks course access through Chamilo, then mints an HMAC token bound to user, package and version (12 h) | `src/Service/ContentToken.php` |
| Tool visibility API | `CTool::getVisibility()` (resource link of the course) | `src/ExeLearningPlugin.php::isToolVisible()` |
| CLI seeding | Needs `_user` and `access_url_id` in the session and a security token, or `ResourceListener` refuses to persist | `scripts/cli-bootstrap.php` |
| Editor protocol | `initialProjectUrl` loads the package (no `OPEN_FILE` needed); `REQUEST_EXPORT {format:'elpx'}` returns `EXPORT_FILE`. The editor reports `PROJECT_DIRTY` once and never learns about our saves, so an injected `editor-bridge.js` reports every Y.Doc change | `resources/js/editor-bridge.js` |
| Demo course visibility | `CourseManager::create_course()` defaults to `OPEN_PLATFORM` (any logged-in user may enter). The seed sets `REGISTERED` so the "not enrolled" path is testable | `scripts/seed.php` |

Deviations from the brief:
- CI uses the same Docker image with MariaDB, as in development, not a MySQL 8.4 service container.
- Group-owned packages and a custom tool icon are out (decisions 10 and 11).
- Admin-side enabling is done by the seed, not clicked in E2E.
- Chamilo `master` is not built or tested: by decision, only stable releases are supported. The CI matrix entry and the source build were removed.
