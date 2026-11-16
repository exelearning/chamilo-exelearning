// Fullscreen toggle for the package viewer.
document.querySelectorAll('[data-exelearning-viewer]').forEach((viewer) => {
  const button = viewer.querySelector('[data-exelearning-fullscreen]');
  if (!button || !viewer.requestFullscreen) {
    if (button) button.hidden = true;
    return;
  }
  button.addEventListener('click', () => {
    if (document.fullscreenElement) {
      document.exitFullscreen();
    } else {
      viewer.requestFullscreen();
    }
  });
});
