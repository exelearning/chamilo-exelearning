// Runs inside the editor iframe (injected by editor.php). Numbers every
// document change and reports it to the parent page, and tells the parent
// which change number an export contains (EXPORT_MARK), so "unsaved changes"
// stays truthful after a save however late the notifications arrive. Relays
// Ctrl/Cmd+S. Talks only to the configured parent origin, never '*'.
(function () {
  'use strict';

  var parentOrigin = (window.__EXE_EMBEDDING_CONFIG__ || {}).parentOrigin;
  if (!parentOrigin || window.parent === window) {
    return;
  }

  function post(message) {
    window.parent.postMessage(message, parentOrigin);
  }

  var seq = 0;
  var watched = null;
  var timer = null;

  function watch() {
    var ydoc = window.eXeLearning && window.eXeLearning.app && window.eXeLearning.app.project
      && window.eXeLearning.app.project._yjsBridge && window.eXeLearning.app.project._yjsBridge.documentManager
      && window.eXeLearning.app.project._yjsBridge.documentManager.ydoc;
    if (!ydoc || typeof ydoc.on !== 'function' || ydoc === watched) {
      return;
    }
    watched = ydoc;
    ydoc.on('update', function () {
      seq += 1;
      if (timer) {
        return;
      }
      timer = setTimeout(function () {
        timer = null;
        post({ type: 'DOCUMENT_CHANGED', seq: seq });
      }, 300);
    });
  }

  // The document is replaced when the project is imported: keep re-attaching.
  setInterval(watch, 1000);

  // Registered before the export runs (the editor exports asynchronously), so
  // the mark carries the last change number included in the export.
  window.addEventListener('message', function (event) {
    if (event.source !== window.parent || event.origin !== parentOrigin) {
      return;
    }
    var data = event.data || {};
    if (data.type === 'REQUEST_EXPORT') {
      post({ type: 'EXPORT_MARK', requestId: data.requestId, seq: seq });
    }
  });

  document.addEventListener('keydown', function (event) {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
      event.preventDefault();
      post({ type: 'SAVE_SHORTCUT' });
    }
  }, true);
})();
