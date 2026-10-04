<?php
declare(strict_types=1);

require_once __DIR__ . '/cms.php';
require_once __DIR__ . '/archive-lib.php';

/** Search only text which a visitor can already see on a published page. */
function site_search_text($value): string {
    $html = preg_replace('~</?(?:p|div|li|h[1-6]|br|tr|td|th|blockquote)[^>]*>~iu', ' ', (string)$value) ?? (string)$value;
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return cms_text(preg_replace('/\s+/u', ' ', $text) ?? $text, 16000);
}

function site_search_anchor(string $type, $id, int $fallback): string {
    $part = trim((string)preg_replace('/[^a-z0-9_-]+/i', '-', (string)$id), '-');
    return $type . '-' . ($part !== '' ? $part : $fallback);
}

function site_search_documents(array $content, ?string $today = null): array {
    $today ??= date('Y-m-d');
    $documents = [];
    $add = static function (string $category, string $title, string $url, $body, string $keywords = '', string $date = '') use (&$documents): void {
        $title = site_search_text($title);
        if ($title === '' || $url === '') return;
        $documents[] = [
            'category' => $category,
            'title' => $title,
            'url' => $url,
            'text' => site_search_text($body),
            'keywords' => site_search_text($keywords),
            'date' => $date,
        ];
    };

    $add('Главная', 'Группа АА «Почти нормальные»', '/', ($content['pages']['home_intro'] ?? '') . ' ' . cms_site_text($content, 'home_heading'), 'анонимные алкоголики онлайн группа аа поддержка трезвость zoom зум войти ссылка подключиться чат написать телеграм telegram связь');

    foreach (($content['newcomers']['pages'] ?? []) as $page) {
        if (empty($page['published'])) continue;
        $slug = (string)($page['slug'] ?? '');
        if ($slug === 'aa-test') {
            $add('Тест', 'Тест «Подходит ли тебе АА?»', '/p/test-na-alkogolizm', $page['body'] ?? '', 'тест на алкоголизм подходит ли мне аа проверить употребление алкоголя вопросы аа');
            continue;
        }
        if (!preg_match('/^[a-z0-9-]+$/', $slug)) continue;
        $title = $slug === 'menu' ? 'Новичкам: с чего начать' : (string)($page['title'] ?? 'Новичкам');
        $actions = implode(' ', array_map(static fn($action): string => (string)($action['label'] ?? ''), (array)($page['actions'] ?? [])));
        $keywords = $slug === 'menu' ? 'бросить пить перестать пить помощь алкоголику найти группу аа онлайн собрание первый раз впервые' : '';
        if ($slug === 'aa') $keywords = 'что такое аа';
        $add('Новичкам', $title, '/newcomers.html' . ($slug === 'menu' ? '' : '#' . $slug), (string)($page['body'] ?? '') . ' ' . $actions, $keywords);
    }

    $schedule = $content['schedule'] ?? [];
    $add('Расписание', 'Расписание онлайн-собраний АА', '/schedule.html', $schedule['time'] ?? '', 'когда собрание zoom зум дни время темы сегодня во сколько вечером');
    foreach (($schedule['days'] ?? []) as $i => $day) {
        $add('Расписание', 'Собрание: ' . ($day['day'] ?? ''), '/schedule.html#' . site_search_anchor('day', $day['day'] ?? '', (int)$i), $day['topic'] ?? '', (string)($schedule['time'] ?? ''));
    }

    $add('Объявления', 'Объявления группы', '/announcements.html', '', 'новости группы анонсы события');
    foreach (($content['announcements'] ?? []) as $i => $item) {
        if (!empty($item['hide_after']) && (string)$item['hide_after'] < $today) continue;
        $add('Объявления', (string)($item['title'] ?? ''), '/announcements.html#' . site_search_anchor('announcement', $item['id'] ?? '', (int)$i), $item['body'] ?? '', 'новости анонс', (string)($item['event_date'] ?? ''));
    }

    $library = $content['library'] ?? [];
    $add('Библиотека', 'Библиотека АА', '/library.html', $library['note'] ?? '', 'книги брошюры литература читать скачать');
    foreach (($library['items'] ?? []) as $i => $item) {
        $add('Библиотека', (string)($item['title'] ?? ''), '/library.html#' . site_search_anchor('book', $item['id'] ?? '', (int)$i), $item['description'] ?? '', 'книга брошюра литература аа');
    }

    $speakers = $content['speakers'] ?? [];
    $materialTitles = implode(' ', array_map(static fn($item): string => (string)($item['title'] ?? ''), (array)($speakers['materials'] ?? [])));
    $add('Спикерские', 'Спикерские и материалы об анонимности', '/speakers.html', ($speakers['intro'] ?? '') . ' ' . ($speakers['privacy'] ?? '') . ' ' . $materialTitles, 'записи аудио слушать анонимность');
    foreach (cms_speakers_newest_first($speakers['items'] ?? []) as $i => $item) {
        $facts = implode(' ', array_map(static fn($key): string => (string)($item[$key] ?? ''), ['speaker','city','home_group','sobriety']));
        $add('Спикерские', (string)($item['title'] ?? ''), '/speakers.html#' . site_search_anchor('speaker', $item['id'] ?? '', (int)$i), $facts, 'спикерская выступление запись аудио', (string)($item['event_date'] ?? ''));
    }

    $services = $content['services'] ?? [];
    $add('Служения', 'Служения группы', '/service.html', $services['lead'] ?? '', 'служащие вакансии ротация');
    foreach (($services['items'] ?? []) as $i => $item) {
        $holderNames = implode(' ', array_map(static fn($holder): string => (string)($holder['name'] ?? ''), (array)($item['holders'] ?? [])));
        $add('Служения', (string)($item['title'] ?? ''), '/service.html#' . site_search_anchor('service', $item['id'] ?? '', (int)$i), $holderNames . ' ' . ($item['term'] ?? '') . ' ' . ($item['sobriety'] ?? ''), !empty($item['open']) ? 'свободное служение вакансия' : 'служение');
    }

    $tradition = $content['tradition'] ?? [];
    $paymentTitles = implode(' ', array_map(static fn($item): string => (string)($item['title'] ?? ''), (array)($tradition['payments'] ?? [])));
    $add('7-я традиция', '7-я традиция и пожертвования', '/tradition.html', ($tradition['lead'] ?? '') . ' ' . $paymentTitles, 'поддержать группу добровольные взносы');

    $archive = $content['archive'] ?? [];
    $add('Архив решений', 'Архив решений группы', '/archive.html', '', 'протоколы повестки рабочие собрания решения');
    foreach (($archive['items'] ?? []) as $i => $item) {
        $id = (string)($item['id'] ?? '');
        $date = (string)($item['event_date'] ?? '');
        $displayDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? date('d.m.Y', strtotime($date)) : $date;
        $cardUrl = '/archive.html#' . site_search_anchor('archive', $id, (int)$i);
        $agendaText = archive_plain_text((string)($item['body'] ?? ''), true);
        if ($agendaText !== '') {
            $agenda = archive_sections($agendaText);
            $add('Повестка РС', 'Повестка РС ' . $displayDate, $cardUrl, $agenda['intro'], 'повестка РС рабочее собрание группа', $date);
            foreach ($agenda['items'] as $point) {
                $body = preg_replace('~https?://[^\s<>]+~u', ' ', $point['text']) ?? $point['text'];
                $add('Повестка РС', $point['number'] . '. ' . cms_text($point['heading'], 140), '/archive.html#' . archive_section_id('agenda', $id, $point['index']), $body, 'повестка РС группа', $date);
            }
        }
        $protocolText = archive_plain_text((string)($item['protocol_text'] ?? ''));
        if ($protocolText !== '') {
            $protocol = archive_protocol_sections($protocolText);
            $voteCategory = $protocol['items'] ? 'Протокол РС' : 'Голосование группы';
            $add($voteCategory, ($protocol['items'] ? 'Протокол РС ' : 'Голосование группы ') . $displayDate, $cardUrl, $protocol['intro'], 'протокол РС рабочее собрание голосования решения', $date);
            foreach ($protocol['items'] as $point) {
                $body = preg_replace('~https?://[^\s<>]+~u', ' ', $point['text']) ?? $point['text'];
                $add('Протокол РС', $point['number'] . '. ' . cms_text($point['heading'], 140), '/archive.html#' . archive_section_id('protocol', $id, $point['index']), $body, 'протокол РС голосование группа', $date);
            }
            foreach ($protocol['votes'] as $vote) {
                $body = preg_replace('~https?://[^\s<>]+~u', ' ', $vote['text']) ?? $vote['text'];
                $add($voteCategory, 'Голосование: ' . cms_text($vote['heading'], 140), '/archive.html#' . archive_vote_id($id, $vote['index']), $body, 'протокол РС результаты голосования решение', $date);
            }
        }
    }

    foreach (($content['pages']['custom'] ?? []) as $page) {
        if (empty($page['published'])) continue;
        $slug = (string)($page['slug'] ?? '');
        if (!preg_match('/^[a-z0-9-]+$/', $slug) || $slug === 'kak-perestat-pit') continue;
        $blocks = is_array($page['blocks'] ?? null) ? $page['blocks'] : [];
        $body = $blocks ? implode(' ', array_map(static fn($block): string => (string)($block['body'] ?? ''), $blocks)) : (string)($page['body'] ?? '');
        $add('Страницы', (string)($page['title'] ?? ''), '/p/' . $slug, $body);
    }

    return $documents;
}
