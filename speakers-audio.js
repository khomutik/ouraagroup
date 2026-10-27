document.addEventListener('click', (event) => {
  const button = event.target.closest('[data-speaker-audio]');
  if (!button) return;
  const frame = button.parentElement.querySelector('.speaker-audio__frame');
  if (!frame) return;
  if (button.getAttribute('aria-expanded') === 'true') {
    const audio = frame.querySelector('audio');
    if (audio) {
      audio.pause();
      audio.removeAttribute('src');
      audio.load();
    }
    frame.replaceChildren();
    frame.hidden = true;
    button.setAttribute('aria-expanded', 'false');
    button.textContent = button.dataset.openLabel || 'Открыть плеер';
    return;
  }
  const source = button.dataset.speakerAudio;
  if (!/^\/speaker-audio\/[A-Za-z0-9_-]{1,40}$/.test(source || '')) return;

  const player = document.createElement('audio');
  player.controls = true;
  player.preload = 'none';
  player.src = source;
  player.setAttribute('aria-label', 'Прослушивание спикерской');
  frame.append(player);
  frame.hidden = false;
  button.setAttribute('aria-expanded', 'true');
  button.textContent = 'Закрыть плеер';
});
