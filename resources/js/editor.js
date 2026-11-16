// Bridge between the editor host page (edit.php) and the embedded editor.
//
// The editor iframe and the package viewer share the site origin, so an
// origin check alone would let package content spoof editor messages: every
// message must come from the editor iframe's own window AND the site origin.
// "Saved" is never taken from a message, only from save.php's response.

/**
 * Whether a message event comes from the editor iframe on the expected origin.
 */
export function isEditorMessage(event, frame, origin) {
  return Boolean(
    frame
      && frame.contentWindow
      && event.source === frame.contentWindow
      && event.origin === origin
      && event.data
      && typeof event.data === 'object'
      && typeof event.data.type === 'string',
  );
}

/**
 * Wire the editor page. Dependencies are injected for tests.
 */
export function createEditorController({ frame, config, ui, win = window, fetchFn = fetch }) {
  const state = { loaded: false, dirty: false, saving: false, pending: null, changes: 0, changesAtExport: 0, packageId: config.packageId, expectedHash: config.expectedHash };

  const post = (message) => frame.contentWindow.postMessage(message, config.origin);

  const setStatus = (text, kind = 'info') => ui.status(text, kind);

  async function upload(bytes) {
    const body = new FormData();
    body.append('package', new Blob([bytes], { type: 'application/zip' }), 'package.elpx');
    body.append('id', String(state.packageId || 0));
    body.append('expected_hash', state.expectedHash || '');
    body.append('title', ui.title());
    body.append(config.tokenName, config.token);

    let response;
    let result = null;
    try {
      response = await fetchFn(config.saveUrl, { method: 'POST', body, credentials: 'same-origin' });
      result = await response.json();
    } catch {
      result = null;
    }

    state.saving = false;
    ui.setSaving(false);

    if (!response || !response.ok || !result || result.ok !== true) {
      const conflict = response && response.status === 409;
      setStatus(conflict ? config.i18n.conflict : (result && result.error) || config.i18n.saveFailed, 'error');
      return false;
    }

    state.packageId = result.id;
    state.expectedHash = result.hash;
    // Changes made while the export and upload were running are still unsaved.
    state.dirty = state.changes !== state.changesAtExport;
    setStatus(config.i18n.saved, 'success');
    sync();
    return true;
  }

  function save() {
    if (!state.loaded || state.saving) return false;
    state.saving = true;
    state.changesAtExport = state.changes;
    ui.setSaving(true);
    setStatus(config.i18n.saving);
    state.pending = `save-${Date.now()}-${Math.random().toString(36).slice(2)}`;
    post({ type: 'REQUEST_EXPORT', requestId: state.pending, data: { format: 'elpx', filename: 'package.elpx' } });
    return true;
  }

  function close() {
    if (state.dirty && !win.confirm(config.i18n.unsaved)) return false;
    state.dirty = false;
    win.location.assign(config.returnUrl);
    return true;
  }

  const sync = () => ui.sync && ui.sync(state);

  function onMessage(event) {
    if (!isEditorMessage(event, frame, config.origin)) return;
    handle(event.data);
    sync();
  }

  function handle(message) {

    switch (message.type) {
      case 'EXELEARNING_READY':
        post({ type: 'CONFIGURE', requestId: 'configure', data: { hideUI: { fileMenu: true, saveButton: true, userMenu: true } } });
        break;
      case 'DOCUMENT_LOADED':
        state.loaded = true;
        ui.setSaving(false);
        setStatus('');
        break;
      case 'DOCUMENT_CHANGED':
        state.changes += 1;
        state.dirty = true;
        break;
      case 'EXELEARNING_EVENT':
        if (message.event === 'PROJECT_DIRTY') state.dirty = true;
        break;
      case 'SAVE_SHORTCUT':
        save();
        break;
      case 'EXPORT_FILE':
        if (message.requestId === state.pending && message.bytes) {
          state.pending = null;
          upload(message.bytes);
        }
        break;
      case 'REQUEST_EXPORT_ERROR':
        if (message.requestId === state.pending) {
          state.pending = null;
          state.saving = false;
          ui.setSaving(false);
          setStatus(config.i18n.saveFailed, 'error');
        }
        break;
      default:
        break;
    }
  }

  function onBeforeUnload(event) {
    if (!state.dirty) return undefined;
    event.preventDefault();
    event.returnValue = '';
    return '';
  }

  win.addEventListener('message', onMessage);
  win.addEventListener('beforeunload', onBeforeUnload);

  return { state, save, close, onMessage, upload };
}

function init() {
  const root = document.querySelector('[data-exelearning-editor]');
  if (!root) return;

  const config = JSON.parse(root.dataset.config);
  const frame = root.querySelector('iframe');
  const saveButton = root.querySelector('[data-exelearning-save]');
  const status = root.querySelector('[data-exelearning-status]');
  const title = root.querySelector('[data-exelearning-title]');

  const controller = createEditorController({
    frame,
    config,
    ui: {
      status(text, kind) {
        status.textContent = text;
        status.dataset.state = kind;
      },
      setSaving(saving) {
        saveButton.disabled = saving;
      },
      title: () => (title ? title.value : ''),
      sync(state) {
        root.dataset.dirty = String(state.dirty);
        root.dataset.loaded = String(state.loaded);
      },
    },
  });

  saveButton.addEventListener('click', () => controller.save());
  root.querySelector('[data-exelearning-close]').addEventListener('click', () => controller.close());
  document.addEventListener('keydown', (event) => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
      event.preventDefault();
      controller.save();
    }
  });
}

if (typeof document !== 'undefined') {
  init();
}
