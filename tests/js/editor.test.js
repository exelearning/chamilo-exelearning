import { describe, it, expect, vi, beforeEach } from 'vitest';
import { isEditorMessage, createEditorController } from '../../resources/js/editor.js';

const ORIGIN = 'https://lms.example';

function setup(overrides = {}) {
  const editorWindow = { postMessage: vi.fn() };
  const frame = { contentWindow: editorWindow };
  const listeners = {};
  const win = {
    addEventListener: (type, fn) => { listeners[type] = fn; },
    confirm: vi.fn(() => false),
    location: { assign: vi.fn() },
  };
  const ui = { status: vi.fn(), setSaving: vi.fn(), title: () => 'New title', sync: vi.fn() };
  const fetchFn = overrides.fetchFn || vi.fn(async () => ({ ok: true, status: 200, json: async () => ({ ok: true, id: 9, hash: 'h2' }) }));
  const config = {
    origin: ORIGIN,
    saveUrl: '/save.php',
    returnUrl: '/start.php',
    packageId: 1,
    expectedHash: 'h1',
    tokenName: 'exelearning_sec_token',
    token: 'tok',
    i18n: { saving: 'saving', saved: 'saved', saveFailed: 'failed', conflict: 'conflict', unsaved: 'unsaved?' },
  };
  const controller = createEditorController({ frame, config, ui, win, fetchFn });
  const send = (data, source = editorWindow, origin = ORIGIN) => listeners.message({ data, source, origin });
  return { controller, editorWindow, frame, win, ui, fetchFn, listeners, send };
}

const flush = () => new Promise((resolve) => setTimeout(resolve, 0));

describe('isEditorMessage', () => {
  const editorWindow = {};
  const frame = { contentWindow: editorWindow };

  it('accepts only the editor window on the site origin', () => {
    expect(isEditorMessage({ source: editorWindow, origin: ORIGIN, data: { type: 'X' } }, frame, ORIGIN)).toBe(true);
  });

  it('rejects a same-origin message from another window (the package viewer)', () => {
    expect(isEditorMessage({ source: {}, origin: ORIGIN, data: { type: 'EXPORT_FILE' } }, frame, ORIGIN)).toBe(false);
  });

  it('rejects the editor window on a foreign origin', () => {
    expect(isEditorMessage({ source: editorWindow, origin: 'https://evil.example', data: { type: 'X' } }, frame, ORIGIN)).toBe(false);
  });

  it('rejects malformed data', () => {
    expect(isEditorMessage({ source: editorWindow, origin: ORIGIN, data: 'EXPORT_FILE' }, frame, ORIGIN)).toBe(false);
    expect(isEditorMessage({ source: editorWindow, origin: ORIGIN, data: null }, frame, ORIGIN)).toBe(false);
    expect(isEditorMessage({ source: editorWindow, origin: ORIGIN, data: { type: 1 } }, frame, ORIGIN)).toBe(false);
  });
});

describe('editor controller', () => {
  let t;
  beforeEach(() => { t = setup(); });

  it('configures the editor when it announces itself, posting only to the site origin', () => {
    t.send({ type: 'EXELEARNING_READY' });
    expect(t.editorWindow.postMessage).toHaveBeenCalledWith(expect.objectContaining({ type: 'CONFIGURE' }), ORIGIN);
  });

  it('does not save before the document is loaded', () => {
    expect(t.controller.save()).toBe(false);
    expect(t.editorWindow.postMessage).not.toHaveBeenCalled();
  });

  it('saves: requests an export, uploads the bytes with the CSRF token and the expected hash', async () => {
    t.send({ type: 'DOCUMENT_LOADED' });
    t.send({ type: 'DOCUMENT_CHANGED' });
    expect(t.controller.save()).toBe(true);
    const request = t.editorWindow.postMessage.mock.calls.at(-1)[0];
    expect(request).toMatchObject({ type: 'REQUEST_EXPORT', data: { format: 'elpx' } });

    t.send({ type: 'EXPORT_FILE', requestId: request.requestId, bytes: new ArrayBuffer(4) });
    await flush();

    const [url, init] = t.fetchFn.mock.calls[0];
    expect(url).toBe('/save.php');
    expect(init.method).toBe('POST');
    expect(init.body.get('exelearning_sec_token')).toBe('tok');
    expect(init.body.get('expected_hash')).toBe('h1');
    expect(init.body.get('id')).toBe('1');
    expect(t.controller.state.expectedHash).toBe('h2');
    expect(t.controller.state.dirty).toBe(false);
    expect(t.ui.status).toHaveBeenLastCalledWith('saved', 'success');
  });

  it('ignores an EXPORT_FILE spoofed by the package viewer (same origin, other window)', async () => {
    t.send({ type: 'DOCUMENT_LOADED' });
    t.controller.save();
    const { requestId } = t.editorWindow.postMessage.mock.calls.at(-1)[0];

    t.send({ type: 'EXPORT_FILE', requestId, bytes: new ArrayBuffer(4) }, { postMessage() {} });
    await flush();

    expect(t.fetchFn).not.toHaveBeenCalled();
  });

  it('ignores an EXPORT_FILE that answers no pending request', async () => {
    t.send({ type: 'DOCUMENT_LOADED' });
    t.send({ type: 'EXPORT_FILE', requestId: 'forged', bytes: new ArrayBuffer(4) });
    await flush();
    expect(t.fetchFn).not.toHaveBeenCalled();
  });

  it('keeps the page dirty when the editor changed after the exported state', async () => {
    t.send({ type: 'DOCUMENT_LOADED' });
    t.send({ type: 'DOCUMENT_CHANGED', seq: 1 });
    t.controller.save();
    const { requestId } = t.editorWindow.postMessage.mock.calls.at(-1)[0];
    t.send({ type: 'EXPORT_MARK', requestId, seq: 1 });
    t.send({ type: 'DOCUMENT_CHANGED', seq: 2 });
    t.send({ type: 'EXPORT_FILE', requestId, bytes: new ArrayBuffer(4) });
    await flush();
    expect(t.controller.state.dirty).toBe(true);
  });

  it('is clean after a save even when the last change is reported late', async () => {
    t.send({ type: 'DOCUMENT_LOADED' });
    t.send({ type: 'EXELEARNING_EVENT', event: 'PROJECT_DIRTY' });
    t.controller.save();
    const { requestId } = t.editorWindow.postMessage.mock.calls.at(-1)[0];
    t.send({ type: 'EXPORT_MARK', requestId, seq: 3 });
    // The debounced notification for changes 1..3 arrives after the export started.
    t.send({ type: 'DOCUMENT_CHANGED', seq: 3 });
    t.send({ type: 'EXPORT_FILE', requestId, bytes: new ArrayBuffer(4) });
    await flush();
    expect(t.controller.state.dirty).toBe(false);
  });

  it('ignores an EXPORT_MARK for another request', async () => {
    t.send({ type: 'DOCUMENT_LOADED' });
    t.send({ type: 'DOCUMENT_CHANGED', seq: 5 });
    t.controller.save();
    const { requestId } = t.editorWindow.postMessage.mock.calls.at(-1)[0];
    t.send({ type: 'EXPORT_MARK', requestId: 'other', seq: 0 });
    t.send({ type: 'EXPORT_FILE', requestId, bytes: new ArrayBuffer(4) });
    await flush();
    expect(t.controller.state.dirty).toBe(false);
  });

  it('reports a conflict on 409 and keeps the old expected hash', async () => {
    t = setup({ fetchFn: vi.fn(async () => ({ ok: false, status: 409, json: async () => ({ ok: false, error: 'x' }) })) });
    t.send({ type: 'DOCUMENT_LOADED' });
    t.send({ type: 'DOCUMENT_CHANGED' });
    t.controller.save();
    const { requestId } = t.editorWindow.postMessage.mock.calls.at(-1)[0];
    t.send({ type: 'EXPORT_FILE', requestId, bytes: new ArrayBuffer(4) });
    await flush();
    expect(t.ui.status).toHaveBeenLastCalledWith('conflict', 'error');
    expect(t.controller.state.expectedHash).toBe('h1');
    expect(t.controller.state.dirty).toBe(true);
  });

  it('treats a non-JSON answer (e.g. a 413 from the web server) as a failure', async () => {
    t = setup({ fetchFn: vi.fn(async () => ({ ok: false, status: 413, json: async () => { throw new Error('html'); } })) });
    t.send({ type: 'DOCUMENT_LOADED' });
    t.controller.save();
    const { requestId } = t.editorWindow.postMessage.mock.calls.at(-1)[0];
    t.send({ type: 'EXPORT_FILE', requestId, bytes: new ArrayBuffer(4) });
    await flush();
    expect(t.ui.status).toHaveBeenLastCalledWith('failed', 'error');
  });

  it('reports export errors from the editor', () => {
    t.send({ type: 'DOCUMENT_LOADED' });
    t.controller.save();
    const { requestId } = t.editorWindow.postMessage.mock.calls.at(-1)[0];
    t.send({ type: 'REQUEST_EXPORT_ERROR', requestId });
    expect(t.ui.status).toHaveBeenLastCalledWith('failed', 'error');
    expect(t.controller.state.saving).toBe(false);
  });

  it('asks before closing with unsaved changes and stays when declined', () => {
    t.send({ type: 'DOCUMENT_LOADED' });
    t.send({ type: 'DOCUMENT_CHANGED' });
    expect(t.controller.close()).toBe(false);
    expect(t.win.confirm).toHaveBeenCalledWith('unsaved?');
    expect(t.win.location.assign).not.toHaveBeenCalled();
  });

  it('closes without asking when there is nothing to save', () => {
    expect(t.controller.close()).toBe(true);
    expect(t.win.confirm).not.toHaveBeenCalled();
    expect(t.win.location.assign).toHaveBeenCalledWith('/start.php');
  });

  it('blocks unload only while dirty', () => {
    const event = { preventDefault: vi.fn() };
    t.listeners.beforeunload(event);
    expect(event.preventDefault).not.toHaveBeenCalled();
    t.send({ type: 'EXELEARNING_EVENT', event: 'PROJECT_DIRTY' });
    t.listeners.beforeunload(event);
    expect(event.preventDefault).toHaveBeenCalled();
  });

  it('saves on the keyboard shortcut relayed by the editor', () => {
    t.send({ type: 'DOCUMENT_LOADED' });
    t.send({ type: 'SAVE_SHORTCUT' });
    expect(t.editorWindow.postMessage).toHaveBeenCalledWith(expect.objectContaining({ type: 'REQUEST_EXPORT' }), ORIGIN);
  });
});
