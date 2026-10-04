<?php
declare(strict_types=1);

require_once __DIR__ . '/cms.php';

/** Keep words and line boundaries, but never copy editor markup into archive search. */
function archive_plain_text(string $value, bool $html = false): string {
    if ($html) {
        $value = preg_replace('~<br\s*/?>|</(?:p|div|summary|li|h[1-6])\s*>~iu', "\n", $value) ?? $value;
        $value = preg_replace('~<(?:p|div|summary|li|h[1-6])\b[^>]*>~iu', "\n", $value) ?? $value;
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    $value = str_replace(["\xEF\xBB\xBF", "\r\n", "\r"], ['', "\n", "\n"], $value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? $value;
    $lines = array_map(static fn(string $line): string => trim(preg_replace('/[ \t]+/u', ' ', $line) ?? $line), explode("\n", $value));
    return trim(preg_replace("/\n{3,}/u", "\n\n", implode("\n", $lines)) ?? implode("\n", $lines));
}

/** A numbered agenda/protocol point is the smallest honest search result. */
function archive_sections(string $text): array {
    $intro = [];
    $items = [];
    $current = null;
    foreach (explode("\n", archive_plain_text($text)) as $line) {
        if (preg_match('/^(\d{1,2})\.\s+(.+)$/u', $line, $match)) {
            if ($current !== null) $items[] = $current;
            $current = ['number' => (int)$match[1], 'lines' => [$match[2]]];
        } elseif ($current !== null) {
            $current['lines'][] = $line;
        } else {
            $intro[] = $line;
        }
    }
    if ($current !== null) $items[] = $current;
    foreach ($items as $index => &$item) {
        $item['index'] = $index;
        $item['text'] = trim(implode("\n", $item['lines']));
        $item['heading'] = trim($item['lines'][0] ?? '');
        unset($item['lines']);
    }
    unset($item);
    return ['intro' => trim(implode("\n", $intro)), 'items' => $items];
}

/** The source documents append dated Telegram polls after the meeting minutes. */
function archive_protocol_sections(string $text): array {
    $lines = explode("\n", archive_plain_text($text));
    $starts = [];
    foreach ($lines as $index => $line) {
        if (!preg_match('/^\d{2}\.\d{2}\.\d{4}$/u', trim($line))) continue;
        $next = $index + 1;
        while (isset($lines[$next]) && ($next - $index) <= 3 && (trim($lines[$next]) === '' || trim($lines[$next]) === 'Внеочередное РС')) $next++;
        if (isset($lines[$next]) && preg_match('/^Источник\s*:/iu', trim($lines[$next]))) $starts[] = $index;
    }
    if (!$starts) return archive_sections($text) + ['votes' => []];
    $parts = archive_sections(implode("\n", array_slice($lines, 0, $starts[0])));
    $parts['votes'] = [];
    foreach ($starts as $number => $start) {
        $end = $starts[$number + 1] ?? count($lines);
        $block = trim(implode("\n", array_slice($lines, $start, $end - $start)));
        $heading = 'Голосование ' . ($number + 1);
        if (preg_match('/^Вопрос:\s*(.+?)(?=^Варианты:|^Итог:|\z)/msu', $block, $match)) {
            $withoutUrls = preg_replace('~https?://[^\s<>]+~u', '', $match[1]) ?? $match[1];
            $heading = trim(preg_replace('/\s+/u', ' ', $withoutUrls) ?? $withoutUrls);
        }
        $parts['votes'][] = ['index' => $number, 'heading' => $heading, 'text' => $block];
    }
    return $parts;
}

function archive_section_id(string $kind, string $id, int $index): string {
    $safeId = trim((string)preg_replace('/[^a-z0-9_-]+/i', '-', $id), '-');
    return $kind . '-' . ($safeId !== '' ? $safeId : 'item') . '-point-' . $index;
}

function archive_vote_id(string $id, int $index): string {
    return archive_section_id('vote', $id, $index);
}

function archive_protocol_date(string $text): string {
    if (!preg_match('/^\s*(?:Протокол\s+РС|Протокол\s+рабочего\s+собрания)\s+(\d{2})\.(\d{2})\.(\d{4})/iu', archive_plain_text($text), $match)) return '';
    return $match[3] . '-' . $match[2] . '-' . $match[1];
}

function archive_google_doc_id(array $item): string {
    foreach (($item['links'] ?? []) as $link) {
        $url = (string)($link['url'] ?? '');
        if (preg_match('~^https://docs\.google\.com/document/d/([A-Za-z0-9_-]+)(?:/|$)~', $url, $match)) return $match[1];
    }
    return '';
}

/** The 10 April card was linked to a document dated 5 April; keep it in CMS for repair, not as a public source. */
function archive_public_links(array $item): array {
    $links = (array)($item['links'] ?? []);
    if (($item['event_date'] ?? '') !== '2026-04-10') return $links;
    return array_values(array_filter($links, static fn($link): bool => !str_contains((string)($link['url'] ?? ''), '/12EgF3ar4Cw4bOykH3u2DZXJaiiB3ommgMjTIHM100l4/')));
}

function archive_apply_manual_protocol(array &$content, string $date, string $text): bool {
    $text = archive_plain_text($text);
    if (archive_protocol_date($text) !== $date || !isset($content['archive']['items'])) return false;
    foreach ($content['archive']['items'] as &$item) {
        if (($item['event_date'] ?? '') !== $date) continue;
        if (!empty($item['protocol_text'])) return false;
        $item['protocol_text'] = $text;
        unset($item);
        return true;
    }
    unset($item);
    return false;
}

function archive_append_verified_votes(array &$content, string $date, string $text, string $sourceUrl): int {
    $text = archive_plain_text($text);
    $new = archive_protocol_sections($text)['votes'];
    if (!$new || !preg_match('~^https://drive\.google\.com/file/d/[A-Za-z0-9_-]+/view~', $sourceUrl)) return 0;
    foreach ($new as $vote) {
        if (!preg_match('/^Итог\s*:/mu', $vote['text'])) return 0;
    }
    if (!isset($content['archive']['items'])) return 0;
    foreach ($content['archive']['items'] as &$item) {
        if (($item['event_date'] ?? '') !== $date || empty($item['protocol_text'])) continue;
        $existing = archive_protocol_sections((string)$item['protocol_text'])['votes'];
        $names = array_map(static fn($vote): string => cms_lower($vote['heading']), $existing);
        foreach ($new as $vote) if (in_array(cms_lower($vote['heading']), $names, true)) return 0;
        $item['protocol_text'] = rtrim((string)$item['protocol_text']) . "\n\n" . $text;
        $item['links'] = archive_public_links($item);
        $item['links'][] = ['label' => 'Первоисточник: архив решений (PDF)', 'url' => $sourceUrl];
        unset($item);
        return count($new);
    }
    unset($item);
    return 0;
}

function archive_add_standalone_vote(array &$content, string $date, string $title, string $text, string $sourceUrl): bool {
    $text = archive_plain_text($text);
    $parts = archive_protocol_sections($text);
    if (!preg_match('/^' . preg_quote(date('d.m.Y', strtotime($date)), '/') . '\b/u', $text)
        || count($parts['votes']) !== 1 || !preg_match('/^Итог\s*:/mu', $parts['votes'][0]['text'])
        || !preg_match('~^https://drive\.google\.com/file/d/[A-Za-z0-9_-]+/view~', $sourceUrl)
        || !isset($content['archive']['items'])) return false;
    foreach ($content['archive']['items'] as $item) if (($item['event_date'] ?? '') === $date) return false;
    $content['archive']['items'][] = [
        'id' => cms_id(), 'title' => $title, 'event_date' => $date, 'event_time' => '',
        'image' => '', 'body' => '', 'protocol_text' => $text,
        'links' => [['label' => 'Первоисточник: архив решений (PDF)', 'url' => $sourceUrl]],
    ];
    usort($content['archive']['items'], static fn($a, $b): int => strcmp((string)($b['event_date'] ?? ''), (string)($a['event_date'] ?? '')));
    foreach ($content['archive']['items'] as $index => &$item) $item['order'] = $index;
    unset($item);
    return true;
}

/** Mutate the real CMS array, never a foreach copy of an expression. */
function archive_apply_protocol_updates(array &$content, array $updates): int {
    if (!isset($content['archive']['items']) || !is_array($content['archive']['items'])) return 0;
    $updated = 0;
    foreach ($content['archive']['items'] as &$item) {
        $id = (string)($item['id'] ?? '');
        if (!isset($updates[$id]) || !empty($item['protocol_text'])) continue;
        $candidate = $updates[$id];
        if (($item['event_date'] ?? '') !== $candidate['date'] || archive_google_doc_id($item) !== $candidate['doc']) continue;
        $item['protocol_text'] = $candidate['text'];
        $updated++;
    }
    unset($item);
    return $updated;
}

function archive_linked_text(string $text): string {
    $parts = preg_split('~(https?://[^\s<>]+)~u', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
    $html = '';
    foreach ($parts as $part) {
        if (preg_match('~^https?://~u', $part)) {
            $url = rtrim($part, '.,;');
            $tail = substr($part, strlen($url));
            $html .= '<a href="' . h($url) . '" target="_blank" rel="noopener noreferrer">' . h($url) . '</a>' . h($tail);
        } else {
            $html .= h($part);
        }
    }
    return nl2br($html, false);
}

function archive_paragraphs_html(string $text): string {
    $html = '';
    foreach (preg_split("/\n{2,}/u", trim($text)) ?: [] as $paragraph) {
        if ($paragraph !== '') $html .= '<p>' . archive_linked_text($paragraph) . '</p>';
    }
    return $html;
}

function archive_document_html(string $text, string $kind, string $itemId): string {
    $parts = $kind === 'protocol' ? archive_protocol_sections($text) : archive_sections($text);
    if ($parts['intro'] === '' && !$parts['items'] && empty($parts['votes'])) return '';
    $label = $kind === 'protocol' ? ($parts['items'] ? 'Протокол и результаты голосований' : 'Итоги голосования') : 'Повестка РС';
    $html = '<details class="archive-document archive-document--' . h($kind) . '"><summary>' . h($label) . '</summary>';
    $intro = $parts['intro'];
    if ($parts['items'] && preg_match('/^\s*(?:Повестка|Протокол)\s+(?:к\s+)?РС\b[^\n]*(?:\n|$)/iu', $intro, $match)) {
        $intro = trim(substr($intro, strlen($match[0])));
    }
    if ($intro !== '') {
        $html .= '<div class="archive-document__intro">' . archive_paragraphs_html($intro) . '</div>';
    }
    foreach ($parts['items'] as $item) {
        $id = archive_section_id($kind, $itemId, $item['index']);
        $rest = trim(implode("\n", array_slice(explode("\n", $item['text']), 1)));
        $html .= '<section class="archive-document__item" id="' . h($id) . '">';
        $html .= '<h3><span class="archive-document__number">' . h((string)$item['number']) . '.</span> ' . h($item['heading']) . '</h3>';
        if ($rest !== '') $html .= '<div class="archive-document__body">' . archive_paragraphs_html($rest) . '</div>';
        $html .= '</section>';
    }
    if (!empty($parts['votes'])) {
        $html .= '<h3 class="archive-document__votes-heading">Итоги голосований</h3>';
        foreach ($parts['votes'] as $vote) {
            $html .= '<section class="archive-document__vote" id="' . h(archive_vote_id($itemId, $vote['index'])) . '">';
            $html .= '<h4>' . h($vote['heading']) . '</h4>';
            $body = $vote['text'];
            $body = preg_replace('/^Вопрос:\s*.+?(?=^Варианты:|^Итог:|\z)/msu', '', $body) ?? $body;
            $html .= '<div class="archive-document__body">' . archive_paragraphs_html(trim($body)) . '</div>';
            $html .= '</section>';
        }
    }
    $html .= '</details>';
    return $html;
}
