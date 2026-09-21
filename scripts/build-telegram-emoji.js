/*
 * Builds the CMS emoji catalog from Telegram for Android's own static emoji
 * images. Source: https://github.com/DrKLO/Telegram (GPL-2.0-or-later).
 */
const fs = require('node:fs/promises');
const path = require('node:path');

const ROOT = 'https://raw.githubusercontent.com/DrKLO/Telegram/master';
const DATA_URL = `${ROOT}/TMessagesProj/src/main/java/org/telegram/messenger/EmojiData.java`;
const IMAGE_ROOT = `${ROOT}/TMessagesProj/src/main/assets/emoji`;
const OUTPUT_DIR = path.resolve(__dirname, '..', 'assets', 'telegram-emoji');
const CATALOG_PATH = path.join(OUTPUT_DIR, 'index.json');

const skinTone = /[\u{1F3FB}-\u{1F3FF}]/u;
const professionCharacters = new Set(['👮', '👷', '💂', '🕵']);
const professionMarkers = new Set(['⚕', '🌾', '🍳', '🎓', '🎤', '🏫', '🏭', '💻', '💼', '🔧', '🔬', '🎨', '🚒', '✈', '🚀', '⚖']);
const blockedPeopleMarkers = [
  '💀', '☠', '👻', '🧟', '🧛', '🦸', '🦹', '🧞', '💇',
  '🦽', '🦼', '🦯', '🦻', '🦾', '🦿', '🧏',
  '🏋', '🤸', '⛹', '🤾', '🧘', '🏄', '🏊', '🤽', '🚣', '🧗', '🚵', '🚴', '🏃', '🏇'
];
const blockedTime = new Set(['⌚', '⏱', '⏲', '⏰', '🕰', '⌛', '⏳']);
const blockedTools = new Set(['🪜', '🧰', '🪛', '🔧', '🔨', '⚒', '🛠', '⛏', '🪏', '🪚', '🔩', '⚙', '🪤', '🧱', '⛓', '⛓‍💥', '🧲']);
const blockedFuneral = new Set(['⚰', '🪦', '⚱']);
const blockedMusicInstruments = new Set(['🎹', '🪇', '🥁', '🪘', '🎷', '🎺', '🪊', '🪗', '🎸', '🪕', '🪉', '🎻', '🪈']);
const blockedReligion = new Set(['☮', '✝', '☪', '🕉', '☸', '🪯', '✡', '🔯', '🕎', '☯', '☦', '🛐', '📿', '🧿', '🪬']);
const blockedZodiac = new Set(['⛎', '♈', '♉', '♊', '♋', '♌', '♍', '♎', '♏', '♐', '♑', '♒', '♓']);
const blockedIdeographs = new Set(['🉑', '🈶', '🈚', '🈸', '🈺', '🈷', '🈁', '🈂', '🈳', '🈯', '🉐', '㊙', '㊗', '🈴', '🈵', '🈹', '🈲', '🀄']);

function extractArray(javaSource) {
  const marker = 'public static final String[][] data = {';
  const start = javaSource.indexOf(marker);
  if (start < 0) throw new Error('EmojiData.data was not found');
  const tail = javaSource.slice(start + marker.length);
  const end = tail.indexOf('\n    };');
  if (end < 0) throw new Error('EmojiData.data closing marker was not found');

  const pages = [];
  const pagePattern = /new String\[\]\s*\{([\s\S]*?)\n\s*\}/g;
  let match;
  while ((match = pagePattern.exec(tail.slice(0, end)))) {
    const strings = match[1].match(/"(?:\\.|[^"\\])*"/g) || [];
    pages.push(strings.map((value) => JSON.parse(value)));
  }
  if (!pages.length) throw new Error('No emoji pages were parsed');
  return pages;
}

function isProfession(emoji) {
  const plain = emoji.replaceAll('\uFE0F', '');
  if ([...professionCharacters].some((character) => plain.includes(character))) return true;
  if (!emoji.includes('\u200D')) return false;
  return [...professionMarkers].some((marker) => emoji.includes(marker));
}

function isBlocked(emoji, pageIndex, imageIndex, page) {
  const plain = emoji.replaceAll('\uFE0F', '');
  if (blockedReligion.has(plain)) return true;
  if (pageIndex === 2 || pageIndex === 4 || pageIndex === 7) return true;
  if (pageIndex === 0) {
    const clothingStart = page.indexOf('🪢');
    if (clothingStart >= 0 && imageIndex >= clothingStart) return true;
    if (blockedPeopleMarkers.some((marker) => plain.includes(marker))) return true;
  }
  if (pageIndex === 1) {
    const weatherStart = page.indexOf('🌞');
    if (weatherStart < 0 || imageIndex < weatherStart) return true;
  }
  if (pageIndex === 3) {
    const nonSportStart = page.indexOf('🎫');
    if (nonSportStart < 0 || imageIndex < nonSportStart) return true;
    if (blockedMusicInstruments.has(plain) || ['🎯', '🎳'].includes(plain)) return true;
  }
  if (pageIndex === 5 && (blockedTime.has(plain) || blockedTools.has(plain) || blockedFuneral.has(plain))) return true;
  if (pageIndex === 6) {
    if (blockedZodiac.has(plain) || blockedIdeographs.has(plain) || plain === '♿' || plain === '〽') return true;
    if (/^🕐|^🕑|^🕒|^🕓|^🕔|^🕕|^🕖|^🕗|^🕘|^🕙|^🕚|^🕛|^🕜|^🕝|^🕞|^🕟|^🕠|^🕡|^🕢|^🕣|^🕤|^🕥|^🕦|^🕧/u.test(plain)) return true;
  }
  return false;
}

async function download(url, destination) {
  try {
    await fs.access(destination);
    return;
  } catch (_) {}
  const response = await fetch(url);
  if (!response.ok) throw new Error(`${response.status} ${url}`);
  await fs.writeFile(destination, Buffer.from(await response.arrayBuffer()));
}

async function main() {
  const response = await fetch(DATA_URL);
  if (!response.ok) throw new Error(`${response.status} ${DATA_URL}`);
  const pages = extractArray(await response.text());
  const seen = new Set();
  const catalog = [];

  pages.forEach((page, pageIndex) => page.forEach((emoji, imageIndex) => {
    if (skinTone.test(emoji) || isProfession(emoji) || isBlocked(emoji, pageIndex, imageIndex, page) || seen.has(emoji)) return;
    seen.add(emoji);
    catalog.push({emoji, file: `${pageIndex}_${imageIndex}.png`});
  }));

  await fs.mkdir(OUTPUT_DIR, {recursive: true});
  let cursor = 0;
  const workers = Array.from({length: 20}, async () => {
    while (cursor < catalog.length) {
      const item = catalog[cursor++];
      await download(`${IMAGE_ROOT}/${item.file}`, path.join(OUTPUT_DIR, item.file));
    }
  });
  await Promise.all(workers);
  await fs.writeFile(CATALOG_PATH, JSON.stringify(catalog));
  await fs.writeFile(path.join(OUTPUT_DIR, 'SOURCE.txt'), [
    'Telegram for Android static emoji assets',
    'https://github.com/DrKLO/Telegram',
    'License: GNU GPL v2 or later (see LICENSE)',
    ''
  ].join('\n'));
  const licenseResponse = await fetch(`${ROOT}/LICENSE`);
  if (licenseResponse.ok) await fs.writeFile(path.join(OUTPUT_DIR, 'LICENSE'), await licenseResponse.text());
  console.log(`Built ${catalog.length} Telegram emoji without skin-tone variants or professions.`);
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
