# Security

## Reporting a vulnerability

Please report vulnerabilities privately through
[GitHub security advisories](https://github.com/exelearning/chamilo-exelearning/security/advisories/new), not in public issues.

## Model

- **Uploading or editing a package is trusted.** A package is HTML and JavaScript written by its author. Only the roles allowed by
  the *Who can manage and edit packages* setting (course teachers by default; platform administrators always) can upload, edit,
  rename, hide or delete packages. Students never can.
- **Validation before storage.** The archive must be a valid ZIP with an ODE 2.0 `content.xml` and an `index.html`. These are
  rejected:
  - path traversal, absolute paths, drive letters, backslashes and control characters;
  - symbolic links and executable or server-configuration file names;
  - more than 10 000 entries or more than 1 GiB uncompressed;
  - suspicious compression ratios, and entries larger than their declared size.

  Nothing is written for a rejected archive.
- **Storage** is Chamilo's private `plugins` filesystem, outside the web root by default.
- **Delivery** goes through `content.php` only:
  - It requires a logged-in user and a token bound to that user, the package and its current version, issued by the viewer after
    Chamilo's course access check. The token expires after 12 hours.
  - Every response has an explicit `Content-Type` and `X-Content-Type-Options: nosniff`.
  - HTML gets a CSP with `frame-ancestors 'self'`, plus `sandbox` (an opaque origin) when opened as a top-level page.
  - SVG and XML are served script-free and sandboxed. Unknown types are downloaded, never rendered.
- **The viewer iframe** is sandboxed but keeps `allow-same-origin`, as in every other eXeLearning integration: packages need it
  for storage and relative assets. It is defence in depth, not an isolation boundary.
- **The embedded editor:**
  - Its parent page accepts a message only when it comes from the editor iframe's own window **and** the site origin, so a
    package cannot spoof the editor.
  - A save validates and stores the new version completely before switching to it, and refuses saves based on an outdated version.
- **State changes** are POST-only and protected by a CSRF token, a capability check and an ownership check on the specific
  package.
