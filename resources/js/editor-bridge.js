// Runs inside the editor iframe (injected by editor.php). Reports every
// document change to the parent page, so "unsaved changes" stays truthful
// after a save, and relays Ctrl/Cmd+S. Posts only to the configured parent
// origin, never '*'.
(function () {
  'use strict';

  var parentOrigin = (window.__EXE_EMBEDDING_CONFIG__ || {}).parentOrigin;
  if (!parentOrigin || window.parent === window) {
    return;
  }

  function post(message) {
    window.parent.postMessage(message, parentOrigin);
  }

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
      if (timer) {
        return;
      }
      timer = setTimeout(function () {
        timer = null;
        post({ type: 'DOCUMENT_CHANGED' });
      }, 300);
    });
  }

  // The document is replaced when the project is imported: keep re-attaching.
  setInterval(watch, 1000);

  document.addEventListener('keydown', function (event) {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
      event.preventDefault();
      post({ type: 'SAVE_SHORTCUT' });
    }
  }, true);
})();
