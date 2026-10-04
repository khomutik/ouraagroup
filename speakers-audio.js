document.addEventListener('click', (event) => {
  const button = event.target.closest('[data-speaker-preview]');
  if (!button) return;
  const frame = button.parentElement.querySelector('.speaker-audio__frame');
  if (!frame) return;
  if (button.getAttribute('aria-expanded') === 'true') {
    frame.replaceChildren();
    frame.hidden = true;
    button.setAttribute('aria-expanded', 'false');
    button.textContent = 'Плеер на странице';
    return;
  }
  const preview = button.dataset.speakerPreview;
  if (!/^https:\/\/drive\.google\.com\/file\/d\/[A-Za-z0-9_-]+\/preview(?:\?resourcekey=[A-Za-z0-9_-]+)?$/.test(preview || '')) return;

  const player = document.createElement('iframe');
  player.src = preview;
  player.title = 'Прослушивание спикерской на Google Диске';
  player.loading = 'lazy';
  player.allow = 'autoplay';
  player.referrerPolicy = 'strict-origin-when-cross-origin';
  frame.append(player);
  frame.hidden = false;
  button.setAttribute('aria-expanded', 'true');
  button.textContent = 'Закрыть плеер';
});
