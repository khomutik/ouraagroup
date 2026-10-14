const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const script = fs.readFileSync(require.resolve('../meeting-countdown.js'), 'utf8');

function renderAt(utc, weekdays = [1, 2, 4, 5, 7], hour = 21, minute = 30) {
  const output = { textContent: '' };
  const label = { textContent: '' };
  const classes = new Set();
  const panel = {
    dataset: { weekdays: JSON.stringify(weekdays), hour: String(hour), minute: String(minute), durationMinutes: '85' },
    classList: { toggle(name, enabled) { enabled ? classes.add(name) : classes.delete(name); } },
    querySelector: selector => selector.includes('label') ? label : output
  };
  class FakeDate extends Date {
    constructor(...args) { super(...(args.length ? args : [utc])); }
    static now() { return utc; }
  }
  vm.runInNewContext(script, {
    document: { querySelector: () => panel },
    Date: FakeDate,
    Intl,
    JSON,
    Math,
    Number,
    Object,
    Set,
    window: { setInterval() {} }
  });
  return { text: label.textContent, value: output.textContent, isLive: classes.has('is-live') };
}

assert.deepEqual(renderAt(Date.UTC(2026, 9, 12, 18, 0)), { text: 'До собрания', value: '0 часов 30 минут', isLive: false }); // Mon 21:00 MSK
assert.deepEqual(renderAt(Date.UTC(2026, 9, 12, 18, 30)), { text: 'Сейчас идет собрание', value: '', isLive: true }); // Mon 21:30 MSK
assert.deepEqual(renderAt(Date.UTC(2026, 9, 12, 19, 54)), { text: 'Сейчас идет собрание', value: '', isLive: true }); // Mon 22:54 MSK
assert.deepEqual(renderAt(Date.UTC(2026, 9, 12, 19, 55)), { text: 'До собрания', value: '22 часа 35 минут', isLive: false }); // Mon 22:55 MSK
assert.deepEqual(renderAt(Date.UTC(2026, 9, 11, 18, 30), [7]), { text: 'Сейчас идет собрание', value: '', isLive: true }); // Sunday 21:30 MSK
console.log('Meeting countdown Moscow-time and next-day checks passed.');
