# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.1.0] - unreleased

### Added
- Course tool "eXeLearning" for Chamilo 3: list, upload, rename, show/hide, delete and download packages.
- Validation of eXeLearning v4 packages before anything is stored (ZIP integrity, unsafe paths, symlinks,
  executable names, entry count, size and compression-ratio limits); legacy `.elp` files are rejected with a clear message.
- Storage in Chamilo's private plugins filesystem (local or remote) and a content endpoint with per-file headers.
- Viewer with fullscreen, served only through the content endpoint with a short-lived per-user token.
- Embedded eXeLearning editor (create, edit, atomic save with conflict detection, unsaved-changes prompt);
  can be disabled for player-only installs.
- Cleanup on course and session deletion; packages survive the hard deletion of their creator.
- English and Spanish translations.
