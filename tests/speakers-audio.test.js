const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

let clickHandler;
const frame = {
  hidden: true,
  children: [],
  querySelector(selector) { assert.equal(selector, 'audio'); return this.children[0] || null; },
  append(node) { this.children.push(node); },
  replaceChildren() { this.children = []; }
};
const button = {
  dataset: {speakerAudio: '/public.php?speaker_audio=ABC_123', openLabel: 'Моя кнопка плеера'},
  parentElement: {querySelector: () => frame},
  attributes: {'aria-expanded': 'false'},
  getAttribute(name) { return this.attributes[name]; },
  setAttribute(name, value) { this.attributes[name] = value; },
  textContent: 'Моя кнопка плеера'
};
const document = {
  addEventListener(name, handler) { if (name === 'click') clickHandler = handler; },
  createElement(tag) {
    assert.equal(tag, 'audio');
    return {
      attributes: {},
      pause() { this.paused = true; },
      removeAttribute(name) { delete this[name]; },
      load() { this.loaded = true; },
      setAttribute(name, value) { this.attributes[name] = value; }
    };
  }
};

vm.runInNewContext(fs.readFileSync(path.join(__dirname, '..', 'speakers-audio.js'), 'utf8'), {document});
assert.equal(frame.children.length, 0, 'Плеер не должен загружаться до нажатия.');
clickHandler({target: {closest: () => button}});
assert.equal(frame.children.length, 1);
assert.equal(frame.children[0].src, button.dataset.speakerAudio);
assert.equal(frame.children[0].controls, true);
assert.equal(frame.children[0].preload, 'none');
assert.equal(frame.children[0].attributes['aria-label'], 'Прослушивание спикерской');
assert.equal(button.getAttribute('aria-expanded'), 'true');
const player = frame.children[0];
clickHandler({target: {closest: () => button}});
assert.equal(frame.children.length, 0);
assert.equal(frame.hidden, true);
assert.equal(player.paused, true);
assert.equal(player.src, undefined);
assert.equal(player.loaded, true);
assert.equal(button.getAttribute('aria-expanded'), 'false');
assert.equal(button.textContent, 'Моя кнопка плеера');

button.dataset.speakerAudio = 'https://example.com/audio.mp3';
clickHandler({target: {closest: () => button}});
assert.equal(frame.children.length, 0, 'Неизвестный источник не должен вставляться в аудиоплеер.');

console.log('Speaker audio click-to-load checks passed.');
