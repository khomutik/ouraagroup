const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

let clickHandler;
const frame = {
  hidden: true,
  children: [],
  append(node) { this.children.push(node); },
  replaceChildren() { this.children = []; }
};
const button = {
  dataset: {speakerPreview: 'https://drive.google.com/file/d/ABC_123/preview', openLabel: 'Моя кнопка плеера'},
  parentElement: {querySelector: () => frame},
  attributes: {'aria-expanded': 'false'},
  getAttribute(name) { return this.attributes[name]; },
  setAttribute(name, value) { this.attributes[name] = value; },
  textContent: 'Моя кнопка плеера'
};
const document = {
  addEventListener(name, handler) { if (name === 'click') clickHandler = handler; },
  createElement(tag) { assert.equal(tag, 'iframe'); return {}; }
};

vm.runInNewContext(fs.readFileSync(path.join(__dirname, '..', 'speakers-audio.js'), 'utf8'), {document});
assert.equal(frame.children.length, 0, 'Плеер не должен загружаться до нажатия.');
clickHandler({target: {closest: () => button}});
assert.equal(frame.children.length, 1);
assert.equal(frame.children[0].src, button.dataset.speakerPreview);
assert.equal(button.getAttribute('aria-expanded'), 'true');
clickHandler({target: {closest: () => button}});
assert.equal(frame.children.length, 0);
assert.equal(frame.hidden, true);
assert.equal(button.getAttribute('aria-expanded'), 'false');
assert.equal(button.textContent, 'Моя кнопка плеера');

console.log('Speaker audio click-to-load checks passed.');
