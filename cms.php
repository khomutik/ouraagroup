<?php
declare(strict_types=1);

const CMS_ROOT = __DIR__;
const CMS_DATA = CMS_ROOT . '/cms-data';
const CMS_BACKUPS = CMS_ROOT . '/cms-backups';
const CMS_UPLOADS = CMS_ROOT . '/uploads';
const CMS_SECTIONS = ['announcements', 'schedule', 'library', 'speakers', 'services', 'newcomers', 'tradition', 'archive', 'pages', 'navigation', 'site_texts'];
function cms_section_labels(): array { return ['announcements'=>'Объявления','schedule'=>'Расписание собраний','library'=>'Библиотека','speakers'=>'Спикерские','services'=>'Служения','newcomers'=>'Новичкам','tradition'=>'7-я традиция','archive'=>'Архив решений','pages'=>'Главная и пользовательские страницы','navigation'=>'Кнопки и меню','site_texts'=>'Заголовки и подписи']; }

function cms_config(): array {
    static $config = null;
    if ($config !== null) return $config;
    $path = CMS_ROOT . '/cms-config.php';
    if (!is_file($path)) {
        $config = ['setup_token' => '', 'timezone' => 'Europe/Moscow', 'max_image_bytes' => 5242880, 'max_pdf_bytes' => 15728640];
        date_default_timezone_set($config['timezone']);
        return $config;
    }
    $loaded = require $path;
    $config = is_array($loaded) ? $loaded : [];
    $config += ['setup_token' => '', 'timezone' => 'Europe/Moscow', 'max_image_bytes' => 5242880, 'max_pdf_bytes' => 15728640];
    date_default_timezone_set((string) $config['timezone']);
    return $config;
}

function cms_ensure_dirs(): void {
    foreach ([CMS_DATA, CMS_BACKUPS, CMS_UPLOADS] as $dir) {
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('Не удалось создать служебную папку CMS.');
        }
    }
}

function cms_path(string $name): string { return CMS_DATA . '/' . $name . '.json'; }
function cms_read(string $name, array $fallback): array {
    $path = cms_path($name);
    if (!is_file($path)) return $fallback;
    $decoded = json_decode((string) file_get_contents($path), true);
    return is_array($decoded) ? $decoded : $fallback;
}
function cms_backup(string $name): void {
    $path = cms_path($name);
    if (!is_file($path)) return;
    $backup = CMS_BACKUPS . '/' . $name . '-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.json';
    copy($path, $backup);
    $files = glob(CMS_BACKUPS . '/' . $name . '-*.json') ?: [];
    usort($files, static fn($a, $b) => filemtime($b) <=> filemtime($a));
    foreach (array_slice($files, 30) as $old) @unlink($old);
}
function cms_write(string $name, array $data): void {
    cms_ensure_dirs();
    cms_backup($name);
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    $tmp = cms_path($name) . '.tmp';
    if (file_put_contents($tmp, $json, LOCK_EX) === false || !rename($tmp, cms_path($name))) {
        @unlink($tmp);
        throw new RuntimeException('Не удалось сохранить изменения. Проверьте права на папку cms-data.');
    }
}
function cms_backup_files(string $name): array {
    $files = glob(CMS_BACKUPS . '/' . $name . '-*.json') ?: [];
    usort($files, static fn($a, $b) => filemtime($b) <=> filemtime($a));
    return $files;
}
function cms_restore_backup(string $name, string $filename): void {
    $base = basename($filename);
    if (!preg_match('/^' . preg_quote($name, '/') . '-[A-Za-z0-9-]+\.json$/', $base)) throw new RuntimeException('Некорректная резервная копия.');
    $path = CMS_BACKUPS . '/' . $base;
    $decoded = is_file($path) ? json_decode((string)file_get_contents($path), true) : null;
    if (!is_array($decoded)) throw new RuntimeException('Не удалось прочитать резервную копию.');
    cms_write($name, $decoded);
}

function cms_id(): string { return bin2hex(random_bytes(8)); }
function h(?string $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function cms_text($value, int $max = 12000): string {
    $value = trim((string) $value);
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}
function cms_length(string $value): int { return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value); }
function cms_lower(string $value): string { return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value); }
function cms_date_ru(string $value): string {
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts)) return $value;
    $months = [1=>'января',2=>'февраля',3=>'марта',4=>'апреля',5=>'мая',6=>'июня',7=>'июля',8=>'августа',9=>'сентября',10=>'октября',11=>'ноября',12=>'декабря'];
    $month = (int)$parts[2];
    return (int)$parts[3] . ' ' . ($months[$month] ?? $parts[2]) . ' ' . $parts[1];
}
function cms_speakers_newest_first(array $items): array {
    usort($items, static function ($a, $b): int {
        $date = strcmp((string)($b['event_date'] ?? ''), (string)($a['event_date'] ?? ''));
        if ($date !== 0) return $date;
        return (int)($a['order'] ?? PHP_INT_MAX) <=> (int)($b['order'] ?? PHP_INT_MAX);
    });
    return $items;
}
function cms_safe_url($value, bool $empty = true): string {
    $url = trim((string) $value);
    if ($url === '' && $empty) return '';
    if (preg_match('~^(?:https?://|tel:|mailto:|/|\#|assets/|uploads/)~iu', $url)) return $url;
    if (preg_match('~^[a-z0-9][a-z0-9._/-]*(?:\#[a-z0-9-]+)?$~iu', $url)) return $url;
    return '';
}
function cms_drive_audio_preview_url(string $url): string {
    $parts = parse_url($url);
    if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') !== 'drive.google.com') return '';
    $path = (string)($parts['path'] ?? '');
    $id = '';
    if (preg_match('~^/file/d/([A-Za-z0-9_-]+)(?:/.*)?$~', $path, $match)) $id = $match[1];
    if ($path === '/open') {
        parse_str((string)($parts['query'] ?? ''), $query);
        if (is_string($query['id'] ?? null) && preg_match('/^[A-Za-z0-9_-]+$/', $query['id'])) $id = $query['id'];
    }
    if ($id === '') return '';
    parse_str((string)($parts['query'] ?? ''), $query);
    $resourceKey = $query['resourcekey'] ?? '';
    $suffix = is_string($resourceKey) && preg_match('/^[A-Za-z0-9_-]+$/', $resourceKey)
        ? '?resourcekey=' . rawurlencode($resourceKey) : '';
    return 'https://drive.google.com/file/d/' . $id . '/preview' . $suffix;
}
function cms_speaker_preview_url(array $speaker): string {
    $optimized = cms_drive_audio_preview_url(cms_safe_url($speaker['audio_stream_url'] ?? ''));
    if ($optimized !== '') return $optimized;
    return cms_drive_audio_preview_url(cms_safe_url($speaker['audio_url'] ?? ''));
}
function cms_html_attribute(string $attributes, string $name): string {
    if (!preg_match('/\b' . preg_quote($name, '/') . '\s*=\s*(["\'])(.*?)\1/iu', $attributes, $match)) return '';
    return html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
function cms_html_classes(string $attributes): array {
    $value = cms_html_attribute($attributes, 'class');
    return preg_split('/\s+/u', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
}
function cms_alignment_class(string $attributes): string {
    foreach (['text-left'=>'text-left','text-center'=>'text-center','text-right'=>'text-right','text-justify'=>'text-justify'] as $source=>$safe) {
        if (in_array($source, cms_html_classes($attributes), true)) return $safe;
    }
    if (preg_match('/text-align\s*:\s*(left|center|right|justify)/iu', $attributes, $match)) return 'text-' . strtolower($match[1]);
    if (preg_match('/\balign\s*=\s*(["\']?)(left|center|right|justify)\1/iu', $attributes, $match)) return 'text-' . strtolower($match[2]);
    return '';
}
function cms_icon_token_html(string $key): string {
    $icons = [
        'telegram'=>['assets/social-telegram-v2.png','Telegram'],
        'zoom'=>['assets/social-zoom-v2.png','Zoom'],
        'max'=>['assets/social-max-v2.png','MAX'],
        'gmail'=>['assets/service-gmail.svg','Gmail'],
        'whatsapp'=>['assets/service-whatsapp.svg','WhatsApp'],
        'vk'=>['assets/service-vk.svg','ВКонтакте'],
        'youtube'=>['assets/service-youtube.svg','YouTube'],
        'rutube'=>['assets/service-rutube.svg','RuTube'],
        'paypal'=>['assets/service-paypal.svg','PayPal'],
        'sber'=>['assets/service-sber.svg','Сбер'],
        'sbp'=>['assets/service-sbp.svg','Система быстрых платежей'],
    ];
    if (isset($icons[$key])) {
        [$src, $alt] = $icons[$key];
        return '<img class="inline-emoji inline-emoji--token" src="/' . h($src) . '" alt="' . h($alt) . '">';
    }
    if (str_starts_with($key, 'group:')) {
        $file = substr($key, 6);
        if (preg_match('#^[A-Za-z0-9_./-]+\\.png$#', $file)) return '<img class="inline-emoji inline-emoji--token" src="/assets/group-emoji/' . h($file) . '" alt="">';
    }
    return '';
}
function cms_icon_html(string $value): string {
    $escaped = h($value);
    return preg_replace_callback('/\[\[icon:([^\]]+)\]\]/u', static function ($match) {
        return cms_icon_token_html(rawurldecode((string) $match[1])) ?: h($match[0]);
    }, $escaped) ?? $escaped;
}
/** Safe plain text for the public site, with CMS icon tokens expanded. */
function cms_display_text(?string $value): string { return cms_icon_html((string) $value); }
function cms_expand_icon_tokens(string $html): string {
    return preg_replace_callback('/\[\[icon:([^\]]+)\]\]/u', static function ($match) {
        return cms_icon_token_html(rawurldecode((string) $match[1])) ?: $match[0];
    }, $html) ?? $html;
}
function cms_sanitize_html($html): string {
    $html = trim((string) $html);
    if ($html === '') return '';
    $html = cms_expand_icon_tokens($html);
    $html = preg_replace_callback('/<div\b([^>]*)>/iu', static function ($match) {
        $safe = ['newcomers-text','newcomers-info-block','newcomers-actions'];
        $classes = array_values(array_intersect(cms_html_classes($match[1]), $safe));
        $alignment = cms_alignment_class($match[1]);
        if ($alignment !== '') $classes[] = $alignment;
        return '<div' . ($classes ? ' class="' . implode(' ', array_unique($classes)) . '"' : '') . '>';
    }, $html) ?? '';
    $html = preg_replace('/<strike\b[^>]*>/iu', '<s>', $html) ?? '';
    $html = str_ireplace('</strike>', '</s>', $html);
    $html = strip_tags($html, '<div><p><br><strong><b><em><i><u><s><small><sub><sup><mark><code><pre><span><ul><ol><li><a><h2><h3><blockquote><hr><table><thead><tbody><tr><th><td><details><summary><figure><figcaption><img>');
    $html = preg_replace_callback('/<(div|p|h2|h3|blockquote|ul|ol)\b([^>]*)>/iu', static function ($match) {
        $tag = strtolower($match[1]);
        $classes = [];
        $alignment = cms_alignment_class($match[2]);
        if ($alignment !== '') $classes[] = $alignment;
        $sourceClasses = cms_html_classes($match[2]);
        if ($tag === 'blockquote' && in_array('pull-quote', $sourceClasses, true)) $classes[] = 'pull-quote';
        if ($tag === 'ul' && in_array('checklist', $sourceClasses, true)) $classes[] = 'checklist';
        foreach (['newcomers-text','newcomers-info-block','newcomers-actions','newcomers-list','newcomers-steps','newcomers-text__credit','newcomers-text__note','newcomers-text__strong','newcomers-text__intro','intro__lines','intro__invite'] as $safeClass) {
            if (in_array($safeClass, $sourceClasses, true)) $classes[] = $safeClass;
        }
        return '<' . $tag . ($classes ? ' class="' . implode(' ', array_unique($classes)) . '"' : '') . '>';
    }, $html) ?? '';
    $html = preg_replace('/<(br|strong|b|em|i|u|s|small|sub|sup|mark|code|pre|ol|li|hr|table|thead|tbody|tr|th|td|details|summary|figure|figcaption)\b[^>]*>/iu', '<$1>', $html) ?? '';
    $html = preg_replace_callback('/<span\b([^>]*)>/iu', static function ($match) {
        $classes = cms_html_classes($match[1]);
        if (in_array('spoiler', $classes, true)) return '<span class="spoiler" tabindex="0">';
        if (in_array('formula', $classes, true)) return '<span class="formula">';
        $colors = array_values(array_intersect($classes, ['text-color-blue','text-color-beige','text-color-orange']));
        if ($colors) return '<span class="' . h($colors[0]) . '">';
        return '<span>';
    }, $html) ?? '';
    $html = preg_replace_callback('/<img\b([^>]*)>/iu', static function ($match) {
        $url = cms_safe_url(cms_html_attribute($match[1], 'src'));
        if ($url === '') return '';
        $alt = cms_text(cms_html_attribute($match[1], 'alt'), 300);
        $class = in_array('inline-emoji', cms_html_classes($match[1]), true) ? ' class="inline-emoji"' : '';
        return '<img' . $class . ' src="' . h($url) . '" alt="' . h($alt) . '" loading="lazy">';
    }, $html) ?? '';
    return preg_replace_callback('/<a\b([^>]*)>/iu', static function ($m) {
        preg_match('/\bhref\s*=\s*(["\'])(.*?)\1/iu', $m[1], $href);
        $url = cms_safe_url(html_entity_decode($href[2] ?? '', ENT_QUOTES, 'UTF-8'));
        if ($url === '') return '<a>';
        $allowed = ['content-button','content-button--primary','content-button--soft','content-button--outline','content-button--link','content-button--blue','content-button--beige','content-button--orange','content-button--pill','content-button--square','content-button--align-left','content-button--align-center','content-button--align-right','content-button--align-full'];
        $classes = array_values(array_intersect(cms_html_classes($m[1]), $allowed));
        $class = $classes ? ' class="' . h(implode(' ', array_unique($classes))) . '"' : '';
        return '<a' . $class . ' href="' . h($url) . '" target="_blank" rel="noopener noreferrer">';
    }, $html) ?? '';
}
function cms_rich(string $html): string { return cms_sanitize_html($html); }

/** Read/write optional page-level action buttons without disturbing section data. */
function cms_page_actions(array $content, string $key): array {
    if (str_starts_with($key, 'newcomer:')) {
        $slug = substr($key, 9);
        foreach (($content['newcomers']['pages'] ?? []) as $page) if (($page['slug'] ?? '') === $slug) return is_array($page['actions'] ?? null) ? $page['actions'] : [];
        return [];
    }
    return is_array($content['page_actions'][$key] ?? null) ? $content['page_actions'][$key] : [];
}
function cms_set_page_actions(array &$content, string $key, array $actions): void {
    if (str_starts_with($key, 'newcomer:')) {
        $slug = substr($key, 9);
        foreach (($content['newcomers']['pages'] ?? []) as $i => $page) if (($page['slug'] ?? '') === $slug) $content['newcomers']['pages'][$i]['actions'] = $actions;
        return;
    }
    if (!isset($content['page_actions']) || !is_array($content['page_actions'])) $content['page_actions'] = [];
    $content['page_actions'][$key] = $actions;
}
function cms_action_classes(array $action): string {
    // Старые кнопки могли быть созданы до появления визуальных настроек.
    // Берём значения по умолчанию явно: иначе PHP пытался прочесть отсутствующий
    // ключ и выводил предупреждение прямо на публичной странице.
    $type = (string)($action['type'] ?? 'primary');
    if (!in_array($type, ['classic','primary','soft','outline','link','tab'], true)) $type = 'primary';
    $color = (string)($action['color'] ?? 'orange');
    if (!in_array($color, ['orange','blue','beige'], true)) $color = 'orange';
    $shape = (string)($action['shape'] ?? 'rounded');
    if (!in_array($shape, ['rounded','pill','square'], true)) $shape = 'rounded';
    $align = (string)($action['align'] ?? 'left');
    if (!in_array($align, ['left','center','right','full'], true)) $align = 'left';
    return 'flow-button flow-button--' . $type . ' flow-button--' . $color . ' flow-button--' . $shape . ' flow-button--align-' . $align;
}
function cms_actions_html(array $actions, string $class = 'page-actions', string $placementFilter = ''): string {
    $html = '';
    foreach ($actions as $action) {
        $url = cms_safe_url($action['url'] ?? ''); $label = cms_text($action['label'] ?? '', 160);
        if ($url === '' || $label === '') continue;
        $placement = in_array(($action['placement'] ?? 'bottom'), ['under-title','bottom'], true) ? ($action['placement'] ?? 'bottom') : 'bottom';
        if ($placementFilter !== '' && $placementFilter !== $placement) continue;
        $classes = cms_action_classes($action);
        if (str_starts_with($url, '#')) $html .= '<button class="' . h($classes) . '" type="button" data-target="' . h(substr($url, 1)) . '">' . cms_display_text($label) . '</button>';
        else $html .= '<a class="' . h($classes) . '" href="' . h($url) . '">' . cms_display_text($label) . '</a>';
    }
    return $html === '' ? '' : '<div class="' . h($class) . '">' . $html . '</div>';
}

function cms_newcomer_path(string $slug): string {
    return $slug === 'menu' ? '/newcomers.html' : ($slug === 'aa-test' ? '/p/test-na-alkogolizm' : '/newcomers/' . rawurlencode($slug) . '/');
}

function cms_newcomer_actions(array $actions, array $publishedSlugs): array {
    foreach ($actions as &$action) {
        $url = (string)($action['url'] ?? '');
        if (preg_match('~^(?:/?newcomers\.html)?#([a-z0-9-]+)$~', $url, $match) && isset($publishedSlugs[$match[1]])) {
            $action['url'] = cms_newcomer_path($match[1]);
        }
    }
    unset($action);
    return $actions;
}

function cms_default_aa_test(): array {
    return [
        'seo_title' => 'Тест на алкоголизм — 12 вопросов АА | Почти нормальные',
        'seo_description' => 'Ответьте на 12 вопросов Анонимных Алкоголиков о том, как алкоголь влияет на вашу жизнь. Тест не ставит диагноз; пройти его можно анонимно.',
        'body' => '<p>Ответьте на двенадцать вопросов честно — только для себя. Это не диагноз, а повод внимательно посмотреть на то, как алкоголь влияет на жизнь.</p>',
        'test_questions' => [
            'Бывало ли так, что вы решали не пить неделю или более, но вас хватало только на пару дней?',
            'Хотелось ли вам, чтобы окружающие перестали говорить о вашем пьянстве и о том, что вам следует делать?',
            'Пытались ли вы переключаться с одного вида выпивки на другой в надежде, что это поможет вам не напиться?',
            'Приходилось ли вам в течение последнего года выпивать утром, чтобы обрести способность начать новый день?',
            'Случалось ли вам завидовать людям, которые могут пить без неприятных последствий?',
            'Случались ли у вас в течение последнего года проблемы из-за выпивки?',
            'Возникали ли у вас из-за выпивки неприятности в семье?',
            'Случалось ли так, что выпивая в компании, вы старались перехватить «дополнительный» стаканчик, потому что вам не хватило?',
            'Утверждаете ли вы, что можете перестать пить в любой момент, как только захотите, хотя часто напиваетесь даже тогда, когда совсем не собираетесь этого делать?',
            'Приходилось ли вам прогуливать работу или занятия в связи с выпивками?',
            'Случались ли у вас провалы памяти?',
            'Появлялось ли у вас когда-либо ощущение, что если бы вы не пили, то ваша жизнь была бы лучше?',
        ],
        'test_attention_heading' => 'Стоит отнестись к этому внимательно',
        'test_attention_text' => 'Четыре или более ответов «Да» могут быть поводом обсудить, как алкоголь влияет на вашу жизнь. Только вы сами можете решить, относите ли себя к алкоголикам.',
        'test_other_heading' => 'Тест завершён',
        'test_other_text' => 'Этот результат не ставит диагноз. Если употребление алкоголя вызывает тревогу или вопросы, можно прийти на собрание АА, послушать опыт других и задать вопросы.',
    ];
}

function cms_default_newcomers_pages(): array {
    $path = CMS_ROOT . '/newcomers.html';
    $source = is_file($path) ? (string)file_get_contents($path) : '';
    $pages = [];
    if (preg_match_all('/<article\b[^>]*class="[^"]*newcomers-card[^"]*"[^>]*data-page="([a-z0-9-]+)"[^>]*>(.*?)<\/article>/isu', $source, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $order => $match) {
            $slug = $match[1];
            $body = $match[2];
            $title = $slug === 'menu' ? 'Главное меню раздела' : '';
            if (preg_match('/<h2\b[^>]*>(.*?)<\/h2>/isu', $body, $heading)) {
                $title = cms_text(strip_tags($heading[1]), 180);
                $body = preg_replace('/<h2\b[^>]*>.*?<\/h2>/isu', '', $body, 1) ?? $body;
            }
            $actions = [];
            if (preg_match_all('/<button\b[^>]*data-target="([a-z0-9-]+)"[^>]*>(.*?)<\/button>/isu', $body, $buttons, PREG_SET_ORDER)) {
                foreach ($buttons as $button) $actions[] = ['label'=>cms_text(strip_tags($button[2]), 160),'url'=>'#' . $button[1]];
            }
            if (preg_match_all('/<a\b[^>]*class="[^"]*flow-button[^"]*"[^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/isu', $body, $links, PREG_SET_ORDER)) {
                foreach ($links as $link) $actions[] = ['label'=>cms_text(strip_tags($link[2]), 160),'url'=>cms_safe_url(html_entity_decode($link[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'))];
            }
            $body = preg_replace('/<(?:button|a)\b[^>]*class="[^"]*flow-button[^"]*"[^>]*>.*?<\/(?:button|a)>/isu', '', $body) ?? $body;
            $body = preg_replace('/<div\b[^>]*class="[^"]*newcomers-actions[^"]*"[^>]*>\s*<\/div>/isu', '', $body) ?? $body;
            $pages[] = ['id'=>'newcomer-' . $slug,'slug'=>$slug,'title'=>$title ?: $slug,'body'=>cms_sanitize_html($body),'actions'=>$actions,'order'=>$order,'published'=>true];
        }
    }
    return $pages;
}

function cms_default_navigation(): array {
    return [
        'items'=>[
            ['id'=>'home','title'=>'Главная страница','url'=>'index.html','parent'=>'','icon'=>'','external'=>false],
            ['id'=>'newcomers','title'=>'Новичкам','url'=>'newcomers.html','parent'=>'','icon'=>'assets/emoji/aa_heart_hands.png','external'=>false],
            ['id'=>'schedule','title'=>'Расписание собраний','url'=>'schedule.html','parent'=>'','icon'=>'assets/emoji/aa_calendar.png','external'=>false],
            ['id'=>'announcements','title'=>'Объявления','url'=>'announcements.html','parent'=>'','icon'=>'assets/emoji/aa_double_exclamation.png','external'=>false],
            ['id'=>'library','title'=>'Библиотека','url'=>'library.html','parent'=>'','icon'=>'assets/emoji/aa_book_blue.png','external'=>false],
            ['id'=>'speakers','title'=>'Спикерские','url'=>'speakers.html','parent'=>'','icon'=>'assets/emoji/aa_microphone.png','external'=>false],
            ['id'=>'services','title'=>'Служения','url'=>'service.html','parent'=>'','icon'=>'assets/emoji/aa_people.png','external'=>false],
            ['id'=>'tradition','title'=>'7-я традиция','url'=>'tradition.html','parent'=>'','icon'=>'assets/emoji/aa_check.png','external'=>false],
            ['id'=>'archive','title'=>'Архив','url'=>'archive.html','parent'=>'','icon'=>'assets/emoji/aa_book_blue.png','external'=>false],
        ],
        'socials'=>[
            ['title'=>'Zoom','url'=>'https://us06web.zoom.us/j/5487249245?pwd=UE3buqca6pTDt8kGPJDW9pRoaC7gkt.1','icon'=>'assets/social-zoom-v2.png'],
            ['title'=>'Telegram','url'=>'https://telegram.me/+mta_CKQY2c05ODRi','icon'=>'assets/social-telegram-v2.png'],
            ['title'=>'MAX','url'=>'https://max.ru/join/GV-P-08zFtVs6pX-xR5Z8x80MMNPzhjJ1w6JVEYGn9M','icon'=>'assets/social-max-v2.png'],
        ],
    ];
}

function cms_navigation_payload(array $content): array {
    $nav = $content['navigation'] ?? cms_default_navigation();
    $hiddenTitles = ['Другая литература', 'График служений', 'Отчет казначея', 'Отчёт казначея'];
    $pages = [];
    foreach (($content['newcomers']['pages'] ?? []) as $page) {
        if (!empty($page['published']) && !empty($page['slug'])) $pages[(string)$page['slug']] = $page;
    }
    $items = [];
    foreach (($nav['items'] ?? []) as $item) {
        if (in_array((string)($item['title'] ?? ''), $hiddenTitles, true)) continue;
        $url = (string)($item['url'] ?? '');
        if (preg_match('~^/?newcomers\.html#([a-z0-9-]+)$~', $url, $match)) {
            if (!isset($pages[$match[1]])) continue;
            $item['url'] = cms_newcomer_path($match[1]);
        }
        $items[] = $item;
    }
    $testNav = null;
    $items = array_values(array_filter($items, static function (array $item) use (&$testNav): bool {
        if (!in_array((string)($item['url'] ?? ''), ['newcomers.html#aa-test', '#aa-test', 'p/test-na-alkogolizm', '/p/test-na-alkogolizm'], true)) return true;
        $testNav ??= $item;
        return false;
    }));
    if (isset($pages['aa-test'])) {
        $testNav = array_merge(['id'=>'newcomer-aa-test','title'=>'Тест на алкоголизм: подходит ли тебе АА?','icon'=>'','external'=>false], $testNav ?? []);
        $testNav['url'] = '/p/test-na-alkogolizm';
        $testNav['parent'] = 'Новичкам';
        $rootIndex = null;
        foreach ($items as $index => $item) {
            if (($item['id'] ?? '') === 'newcomers' || ($item['title'] ?? '') === 'Новичкам') { $rootIndex = $index; break; }
        }
        array_splice($items, $rootIndex === null ? count($items) : $rootIndex + 1, 0, [$testNav]);
    }
    $knownUrls = array_column($items, 'url');
    foreach ($pages as $slug => $page) {
        if (in_array($slug, ['menu', 'aa-test'], true)) continue;
        $url = cms_newcomer_path($slug);
        if (in_array($url, $knownUrls, true)) continue;
        $items[] = ['id'=>'newcomer-' . $slug,'title'=>$page['title'] ?? 'Страница','url'=>$url,'parent'=>'Новичкам','icon'=>'','external'=>false];
        $knownUrls[] = $url;
    }
    $socialUrls = array_column($nav['socials'] ?? [], 'url');
    foreach ($pages as $slug => $page) {
        foreach (($page['actions'] ?? []) as $index => $action) {
            $url = cms_safe_url($action['url'] ?? '');
            if (preg_match('~^(?:/?newcomers\.html)?#([a-z0-9-]+)$~', $url, $match) && isset($pages[$match[1]])) $url = cms_newcomer_path($match[1]);
            $label = cms_text(strip_tags((string)($action['label'] ?? '')), 160);
            if ($url === '' || $label === '' || in_array($url, $knownUrls, true) || in_array($url, $socialUrls, true)) continue;
            $items[] = ['id'=>'newcomer-action-' . $slug . '-' . $index,'title'=>$label,'url'=>$url,'parent'=>'Новичкам','icon'=>'','external'=>str_starts_with($url, 'http')];
            $knownUrls[] = $url;
        }
    }
    foreach (($content['pages']['custom'] ?? []) as $page) {
        if (empty($page['published']) || empty($page['slug'])) continue;
        $url = '/p/' . $page['slug'];
        if (in_array($url, $knownUrls, true)) continue;
        $items[] = ['id'=>'page-' . $page['slug'],'title'=>$page['title'] ?? 'Страница','url'=>$url,'parent'=>'','icon'=>'','external'=>false];
        $knownUrls[] = $url;
    }
    return ['items'=>$items,'socials'=>$nav['socials'] ?? []];
}

function cms_default_content(): array {
    $content = [
        'announcements' => [
            ['id'=>'ice-2026-09-11','title'=>'«Моё дело — не спасать других, а своим примером показать, как я спаслась сама»','event_date'=>'2026-09-11','event_time'=>'21:30','hide_after'=>'','image'=>'assets/announcement-speaker-ice-2026-09-11.jpg','image_alt'=>'Спикерское выступление Айс 11 сентября 2026 года','body'=>'<p><strong>Спикерское выступление.</strong> Спикер: Айс, Москва. Домашняя группа — АА «На одном дыхании». Трезвая 5 лет.</p>','links'=>[['label'=>'Войти в Zoom','url'=>'https://us06web.zoom.us/j/5487249245?pwd=UE3buqca6pTDt8kGPJDW9pRoaC7gkt.1'],['label'=>'Telegram группы','url'=>'https://telegram.me/+mta_CKQY2c05ODRi']]],
            ['id'=>'rotation-2026-08-29','title'=>'Ротация служащих','event_date'=>'2026-08-29','event_time'=>'18:00','hide_after'=>'','image'=>'assets/announcement-rotation-2026-08-29.jpg','image_alt'=>'Ротация служащих 29 августа 2026 года','body'=>'<p>Плановая смена ведущих собраний, технических ведущих и рассыльных анонсов. Ротация помогает передавать служение другим членам группы и вместе поддерживать жизнь группы.</p><p>Служение можно взять на рабочем собрании или написать секретарю группы.</p>','links'=>[['label'=>'Войти в Zoom','url'=>'https://us06web.zoom.us/j/5487249245?pwd=UE3buqca6pTDt8kGPJDW9pRoaC7gkt.1']]],
            ['id'=>'anastasia-2026-08-28','title'=>'«Принципы, а не личности»','event_date'=>'2026-08-28','event_time'=>'21:30','hide_after'=>'','image'=>'assets/announcement-speaker-anastasia-2026-08-28.jpg','image_alt'=>'Спикерское выступление Анастасии Че 28 августа 2026 года','body'=>'<p><strong>Спикерское выступление.</strong> Спикер: Анастасия Че, Челябинск. Трезвая с 25 октября 2023 года.</p>','links'=>[['label'=>'Войти в Zoom','url'=>'https://us06web.zoom.us/j/5487249245?pwd=UE3buqca6pTDt8kGPJDW9pRoaC7gkt.1'],['label'=>'Telegram группы','url'=>'https://t.me/+mta_CKQY2c05ODRi']]],
            ['id'=>'sasha-2026-08-14','title'=>'«Духовное пробуждение»','event_date'=>'2026-08-14','event_time'=>'21:30','hide_after'=>'','image'=>'assets/announcement-speaker-sasha-2026-08-14.png','image_alt'=>'Спикерское выступление Саши Г. 14 августа 2026 года','body'=>'<p><strong>Спикерское выступление.</strong> Спикер: Саша Г., Петропавловск, Казахстан. Домашняя группа — АА «Последняя капля». Трезвый с 8 января 2024 года.</p>','links'=>[['label'=>'Войти в Zoom','url'=>'https://us06web.zoom.us/j/5487249245?pwd=UE3buqca6pTDt8kGPJDW9pRoaC7gkt.1']]],
            ['id'=>'working-2026-07-25','title'=>'Рабочее собрание','event_date'=>'2026-07-25','event_time'=>'18:00','hide_after'=>'','image'=>'assets/announcement-working-meeting-2026-07-25.jpg','image_alt'=>'Рабочее собрание 25 июля 2026 года','body'=>'<p>Рабочее собрание домашней группы «Почти нормальные». Правом голоса при принятии решений обладают служащие и члены группы, считающие её своей домашней.</p>','links'=>[['label'=>'Войти в Zoom','url'=>'https://us06web.zoom.us/j/5487249245?pwd=UE3buqca6pTDt8kGPJDW9pRoaC7gkt.1']]],
            ['id'=>'darya-2026-07-24','title'=>'«Пьяница или алкоголик? Честно о срывах. Возвращение в программу»','event_date'=>'2026-07-24','event_time'=>'21:30','hide_after'=>'','image'=>'assets/announcement-speaker-darya-2026-07-24.jpg','image_alt'=>'Спикерское выступление Дарьи 24 июля 2026 года','body'=>'<p><strong>Спикерское выступление.</strong> Спикер: Чёрная Мамба (Дарья), Санкт-Петербург. Домашние группы — АА «Юнона и Авось» и «Юго-Запад». Трезвая с 19 апреля 2026 года.</p>','links'=>[['label'=>'Войти в Zoom','url'=>'https://us06web.zoom.us/j/5487249245?pwd=UE3buqca6pTDt8kGPJDW9pRoaC7gkt.1']]],
            ['id'=>'stas-2026-07-10','title'=>'«Эта программа духовна и в то же время моральна»','event_date'=>'2026-07-10','event_time'=>'21:30','hide_after'=>'','image'=>'assets/announcement-speaker-stas-2026-07-10.jpg','image_alt'=>'Спикерское выступление Стаса К. 10 июля 2026 года','body'=>'<p><strong>Спикерское выступление.</strong> Спикер: Стас К., Санкт-Петербург. Домашняя группа — АА «Главная цель». Трезвый более 12 лет.</p>','links'=>[['label'=>'Войти в Zoom','url'=>'https://us06web.zoom.us/j/5487249245?pwd=UE3buqca6pTDt8kGPJDW9pRoaC7gkt.1']]],
            ['id'=>'zoom-training','title'=>'Обучение работе в Zoom','event_date'=>'2026-06-28','event_time'=>'18:00','hide_after'=>'','image'=>'assets/announcement-zoom-training.png','image_alt'=>'Обучение работе в Zoom 28 июня 2026 года','body'=>'<p>Служащие группы aa24.online поделятся опытом работы в Zoom. Встреча будет записываться.</p>','links'=>[['label'=>'Войти в Zoom','url'=>'https://us06web.zoom.us/j/5487249245?pwd=UE3buqca6pTDt8kGPJDW9pRoaC7gkt.1']]],
        ],
        'schedule' => ['time'=>'21:30 по Москве','zoom_url'=>'https://us06web.zoom.us/j/5487249245?pwd=UE3buqca6pTDt8kGPJDW9pRoaC7gkt.1','days'=>[
            ['day'=>'Понедельник','topic'=>'Чтение и обсуждение БК «Анонимные Алкоголики», дополнительные темы'],['day'=>'Вторник','topic'=>'Ёжик, Билл, шаг программы, игра «500 почти нормальных вопросов», дополнительные темы'],['day'=>'Четверг','topic'=>'Собрание для новичков: «Как воздержаться от первой рюмки?», дополнительные темы'],['day'=>'Пятница','topic'=>'Чтение и обсуждение книги «12 шагов и 12 традиций».<br>2-я и 4-я пятница месяца: большое спикерское собрание.'],['day'=>'Воскресенье','topic'=>'День свободных тем, игра «500 почти нормальных вопросов»']]],
        'library' => ['note'=>'<p>Купить печатную литературу АА на русском языке в России можно на <a href="https://aarussia.ru/litra#!/tab/744553584-1">официальном сайте АА России</a>. За помощью с покупкой литературы за рубежом можно обратиться к членам нашей группы.</p>','other_url'=>'https://drive.google.com/drive/folders/1vKX6abhQRFOKIhHaqXmYcUWgjVcCpmHu?usp=sharing','items'=>[
            ['id'=>'big-book','title'=>'Большая Книга «Анонимные Алкоголики»','description'=>'Большая книга с историями. В ней описан опыт первых членов Содружества и принципы, которые помогают алкоголикам оставаться трезвыми.','cover'=>'assets/book-big-book.webp','resource'=>'assets/big-book-with-stories.pdf'],['id'=>'12x12','title'=>'Двенадцать шагов и Двенадцать традиций','description'=>'Книга подробно раскрывает Двенадцать Шагов как путь личного выздоровления и Двенадцать Традиций как основу единства и работы групп АА.','cover'=>'assets/book-12x12.webp','resource'=>'assets/twelve-steps-twelve-traditions.pdf'],['id'=>'living-sober','title'=>'Жить трезвыми','description'=>'Практическая книга с методами сохранения трезвости, которые используют члены АА.','cover'=>'assets/book-living-sober.jpg','resource'=>'assets/living-sober.pdf'],['id'=>'44','title'=>'Брошюра «44 вопроса и ответа»','description'=>'Короткая брошюра с простыми ответами на частые вопросы об Анонимных Алкоголиках.','cover'=>'assets/brochure-44-questions.jpg','resource'=>'assets/forty-four-questions-answers.pdf'],['id'=>'sponsorship','title'=>'Брошюра «Вопросы и ответы о наставничестве»','description'=>'Краткое руководство о наставничестве в АА.','cover'=>'assets/brochure-sponsorship-qa.jpg','resource'=>'assets/sponsorship-questions-answers.pdf']]],
        'speakers' => ['drive_url'=>'https://drive.google.com/drive/folders/1x-bKBZzLpj1uTAnJpWqVFq3JBjXw-su-?usp=sharing','intro'=>'<h2>📌 Немного информации об анонимности и соблюдении Традиций на онлайн-собраниях группы АА «Почти нормальные»</h2><p>Друзья, хотим напомнить, что для нашей группы соблюдение Традиций АА и принципа анонимности является важной частью нашей общей групповой совести.</p><p>Включение камеры остаётся исключительно <strong>личным выбором</strong> каждого участника и не должно быть причиной давления или дискомфорта.</p>','privacy'=>'<h2>Важно об анонимности</h2><p>Просим уважать анонимность и личное пространство друг друга: не делать скриншоты, фотографии или записи участников и не распространять их без согласия. 🙏🏻</p><p><em>Во время спикерских выступлений мы не ведём видеозапись встреч.</em></p>','items'=>[],'materials'=>[['id'=>'mg18','title'=>'A.A. Guidelines - Internet (MG-18)','resource'=>'assets/MG-18_1025.pdf'],['id'=>'mg25','title'=>'Safety and A.A. Groups Online (MG-25)','resource'=>'assets/MG-25_Safety_and_AA_Groups_ONLINE.pdf'],['id'=>'p47','title'=>'Anonymity: Our Spiritual Foundation (P-47)','resource'=>'assets/P-47_Anonymity_Our_Spiritual_Foundation_ONLINE.pdf']]],
        'services' => ['chart_url'=>'https://docs.google.com/spreadsheets/d/1VAWzdnevTgTmfyx83wfSig9BW6PfdKK1wZorpW0bIvU/edit?usp=sharing','lead'=>'Если вы хотите взять служение в нашей группе, вы можете написать любому из служащих или сообщить об этом после собрания в чайной.','items'=>[
            ['id'=>'coordinator','title'=>'Координатор','sobriety'=>'1 год','term'=>'1 год','open'=>false,'holders'=>[['name'=>'Маня Х.','rotation'=>'2027-06-30']]],['id'=>'deputy-coordinator','title'=>'Дублёр координатора','sobriety'=>'6 месяцев','term'=>'6 месяцев','open'=>true,'holders'=>[]],['id'=>'secretary','title'=>'Секретарь','sobriety'=>'6 месяцев','term'=>'6 месяцев','open'=>false,'holders'=>[['name'=>'Валя С.','rotation'=>'2026-12-31']]],['id'=>'deputy-secretary','title'=>'Дублёр секретаря','sobriety'=>'3 месяца','term'=>'6 месяцев','open'=>true,'holders'=>[]],['id'=>'treasurer','title'=>'Казначей','sobriety'=>'1 год','term'=>'1 год','open'=>false,'holders'=>[['name'=>'Владимир Э.','rotation'=>'2027-05-26']]],['id'=>'deputy-treasurer','title'=>'Дублёр казначея','sobriety'=>'6 месяцев','term'=>'6 месяцев','open'=>true,'holders'=>[]],['id'=>'speaker-hunter','title'=>'Спикерхантер','sobriety'=>'1 год','term'=>'1 год','open'=>true,'holders'=>[['name'=>'врио спикерхантера — Катя Ши','rotation'=>'']]],['id'=>'training','title'=>'Куратор по обучению','sobriety'=>'6 месяцев','term'=>'6 месяцев','open'=>false,'holders'=>[['name'=>'Анна Лион','rotation'=>'2026-11-26']]],['id'=>'sysadmin','title'=>'Сисадмин','sobriety'=>'6 месяцев','term'=>'6 месяцев','open'=>false,'holders'=>[['name'=>'Максим Г.','rotation'=>'2026-11-26']]],['id'=>'chairs','title'=>'Ведущие собраний','sobriety'=>'3 месяца','term'=>'3 месяца','open'=>true,'holders'=>[['name'=>'Катя Ши','rotation'=>'2026-12-01'],['name'=>'Анна Лион','rotation'=>'2026-12-01'],['name'=>'Валя С.','rotation'=>'2026-12-01'],['name'=>'Юлия Г.','rotation'=>'2026-12-01']]],['id'=>'tech','title'=>'Технические ведущие','sobriety'=>'2 недели','term'=>'3 месяца','open'=>true,'holders'=>[['name'=>'Катя Ши','rotation'=>'2026-12-01'],['name'=>'Анна Лион','rotation'=>'2026-12-01'],['name'=>'Маня Х.','rotation'=>'2026-12-01'],['name'=>'Валя С.','rotation'=>'2026-12-01'],['name'=>'Денис','rotation'=>'2026-12-01'],['name'=>'Юлия Н.','rotation'=>'2026-12-01']]],['id'=>'announcements','title'=>'Рассыльные анонсов собраний группы','sobriety'=>'2 недели','term'=>'3 месяца','open'=>true,'holders'=>[['name'=>'В Telegram — Анна Лион','rotation'=>'2026-12-01'],['name'=>'В MAX — Юлия Г.','rotation'=>'2026-12-01']]]]],
        'newcomers' => ['pages'=>cms_default_newcomers_pages()],
        'tradition' => ['report_url'=>'https://docs.google.com/spreadsheets/d/1uFKVQ6Orlz2GMTyIWsB4ux7eDRKejFTQ120p6JowZSE/edit?usp=sharing','lead'=>'<p>Пожертвования добровольны и не являются условием членства в нашей группе. Это не касается гостей нашей группы, не являющихся алкоголиками.</p>','thanks'=>'<p>Благодарим за участие и поддержку!</p>','treasurer'=>'Владимир Э.','payments'=>[['id'=>'sber','title'=>'СБП Сбер, Владимир Э.','value'=>'+7 981 188-15-62','url'=>'tel:+79811881562','comment'=>'В комментариях к переводу пишите, пожалуйста, «7 традиция».','icon'=>'С'],['id'=>'paypal','title'=>'PayPal (для переводов из ЕС, США и других стран)','value'=>'paypal.me/Vlad1954','url'=>'https://paypal.me/Vlad1954','comment'=>'','icon'=>'P']]],
        'archive' => ['drive_url'=>'https://docs.google.com/document/d/14c8l7aYBO2R3Gz-PgVV3y0CCMXS4gBflpFp4pQsn9v8/edit?usp=sharing','items'=>[]],
        'pages' => ['home_intro'=>'<p><strong>Группа Анонимных Алкоголиков «Почти нормальные»</strong> - это место для алкоголиков, которым нужна трезвость, опыт других алкоголиков, живое общение, человеческое тепло и поддержка.</p><p>Группа создана для того, чтобы оставаться трезвыми самим и помогать другим алкоголикам обрести трезвость.</p><p>К нам можно прийти новичком, вернуться после срыва или перерыва, прийти с вопросами, сомнениями, тревогой, выключенным микрофоном и ощущением: «Я вообще не знаю, что делать». Всё нормально, мы сами начинали с этого.</p><p>Мы не делим людей на «настоящих» или «не настоящих» алкоголиков, у нас нет «правильно» или «не правильно» выздоравливающих. Не надо заранее знать Шаги, Традиции и быть святым. Не надо уметь красиво говорить и понимать всё с полуслова.</p><p class="intro__lines">Нормальным быть не обязательно.<br>Анонимность уважаем.<br>Атмосферу бережём.<br>Трезвость поддерживаем.</p><p class="intro__invite">Приходите к нам на собрания.<br>🫶 Места хватит всем 🫶</p>','custom'=>[]],
        'site_texts' => [],
        'page_actions' => [],
        'navigation' => cms_default_navigation(),
    ];
    // The initial values reproduce the existing site. Once an editor saves a
    // section, its data is written to cms-data/content.json instead.
    $content['speakers']['intro'] = '<h2>📌 Немного информации об анонимности и соблюдении Традиций на онлайн-собраниях группы АА «Почти нормальные»</h2><p>Друзья, хотим напомнить, что для нашей группы соблюдение Традиций АА и принципа анонимности является важной частью нашей общей групповой совести.</p><p>Согласно рекомендациям GSO АА, на онлайн-собрании каждый участник самостоятельно выбирает тот уровень анонимности, который считает для себя необходимым. Кто-то чувствует себя комфортно с включённой камерой, а для кого-то, в соответствии с его пониманием Одиннадцатой Традиции, предпочтительнее выключенная камера.</p><p>Поэтому включение камеры остаётся исключительно <strong>личным выбором</strong> каждого участника и не должно быть причиной давления или дискомфорта.</p><p>Если вам комфортно и это не затрагивает вашу анонимность, мы будем рады видеть вас с включёнными камерами — это помогает сохранить ощущение живой группы и непосредственного общения.</p>';
    array_splice($content['services']['items'], 7, 0, [[
        'id'=>'deputy-speaker-hunter','title'=>'Дублёр спикерхантера','sobriety'=>'6 месяцев','term'=>'6 месяцев','open'=>true,'holders'=>[['name'=>'врио дублера спикерхантера — Анна Лион','rotation'=>'']]
    ]]);
    array_splice($content['services']['items'], 10, 0, [
        ['id'=>'group-representative','title'=>'Представитель группы','sobriety'=>'1 год','term'=>'1 год','open'=>true,'holders'=>[]],
        ['id'=>'deputy-group-representative','title'=>'Дублёр ПГ','sobriety'=>'9 месяцев','term'=>'1 год','open'=>true,'holders'=>[]],
    ]);
    return $content;
}
function cms_content(): array {
    $defaults = cms_default_content();
    $content = cms_read('content', $defaults);
    foreach ($defaults as $key => $value) {
        if (!array_key_exists($key, $content)) $content[$key] = $value;
    }
    $announcementDefaults = [];
    foreach ($defaults['announcements'] as $item) $announcementDefaults[$item['id']] = $item;
    foreach (($content['announcements'] ?? []) as $index => $item) {
        $fallback = $announcementDefaults[$item['id'] ?? ''] ?? ['event_time'=>'21:30'];
        $content['announcements'][$index] = $item + $fallback;
        if (!array_key_exists('order', $content['announcements'][$index])) $content['announcements'][$index]['order'] = $index;
    }
    if (!isset($content['archive']) || !is_array($content['archive'])) $content['archive'] = $defaults['archive'];
    return $content;
}
function cms_site_text_fields(): array {
    return [
        'home_heading'=>['Главная','Заголовок страницы',"Онлайн группа\nАнонимных алкоголиков\n«Почти нормальные»"],
        'home_description'=>['Главная','Описание для поисковиков','Хочешь перестать пить или ищешь поддержку, чтобы оставаться трезвым? Приходи на русскоязычное онлайн-собрание АА в Zoom. Можно просто послушать.'],
        'zoom_prefix'=>['Главная','Надпись перед названием Zoom','Войти в '],
        'zoom_subtitle'=>['Главная','Подпись кнопки Zoom','Собрание группы'],
        'schedule_button_title'=>['Главная','Название кнопки расписания','Расписание собраний'],
        'schedule_button_subtitle'=>['Главная','Подпись кнопки расписания','Дни и время'],
        'admin_link'=>['Главная','Ссылка на админку','↗ Вход в админку'],
        'newcomers_heading'=>['Новичкам','Заголовок страницы','Новичкам'],
        'newcomers_description'=>['Новичкам','Описание для поисковиков','Если алкоголь стал проблемой, узнай, как впервые попасть на онлайн-собрание АА «Почти нормальные». Можно подключиться и просто послушать.'],
        'schedule_heading'=>['Расписание','Заголовок страницы','Расписание собраний'],
        'schedule_description'=>['Расписание','Описание для поисковиков','Онлайн-собрания АА «Почти нормальные» проходят по понедельникам, вторникам, четвергам, пятницам и воскресеньям в 21:30 по Москве. Темы и вход в Zoom.'],
        'announcements_heading'=>['Объявления','Заголовок страницы','Объявления'],
        'announcements_description'=>['Объявления','Описание для поисковиков','Объявления онлайн-группы АА «Почти нормальные»: новости и события группы.'],
        'no_announcements'=>['Объявления','Текст, если объявлений нет','Сейчас новых объявлений нет.'],
        'library_heading'=>['Библиотека','Заголовок страницы','Библиотека'],
        'library_description'=>['Библиотека','Описание для поисковиков','Книги, брошюры и литература АА для группы «Почти нормальные».'],
        'library_other_button'=>['Библиотека','Кнопка другой литературы','Другая литература'],
        'library_read_button'=>['Библиотека','Текст кнопки по умолчанию (для карточек без своих настроек)','Читать'],
        'speakers_heading'=>['Спикерские','Заголовок страницы','Спикерские'],
        'speakers_description'=>['Спикерские','Описание для поисковиков','Спикерские и материалы об анонимности группы АА «Почти нормальные».'],
        'speakers_drive_button'=>['Спикерские','Кнопка Google Диска','Открыть спикерские на Google Диске'],
        'speakers_materials_heading'=>['Спикерские','Заголовок материалов','Материалы об анонимности'],
        'services_heading'=>['Служения','Заголовок страницы','Служения'],
        'services_description'=>['Служения','Описание для поисковиков','Служения группы АА «Почти нормальные».'],
        'services_chart_button'=>['Служения','Кнопка графика','График служений'],
        'services_open_label'=>['Служения','Подпись свободного служения','свободное служение'],
        'archive_heading'=>['Архив','Заголовок страницы','Решения группы'],
        'archive_description'=>['Архив','Описание для поисковиков','Протоколы рабочих собраний и решения группы «Почти нормальные».'],
        'archive_drive_button'=>['Архив','Кнопка Google Диска','Открыть архив на Google Диске'],
        'tradition_heading'=>['7-я традиция','Заголовок страницы','7-я традиция'],
        'tradition_description'=>['7-я традиция','Описание для поисковиков','Реквизиты седьмой традиции группы «Почти нормальные».'],
        'tradition_report_button'=>['7-я традиция','Кнопка отчёта казначея','Отчёт казначея'],
        'back_to_top'=>['Общее','Кнопка возврата вверх','↑ Наверх'],
    ];
}
function cms_site_text(array $content, string $key): string {
    $fields = cms_site_text_fields();
    $default = (string) ($fields[$key][2] ?? '');
    $value = $content['site_texts'][$key] ?? null;
    return is_string($value) && trim($value) !== '' ? $value : $default;
}
function cms_users(): array { return cms_read('users', []); }
function cms_audit(): array { return cms_read('audit', []); }
function cms_log(string $action, string $details = ''): void {
    $audit = cms_audit();
    $user = cms_current_user();
    array_unshift($audit, ['at'=>date(DATE_ATOM),'user'=>$user['username'] ?? 'system','action'=>$action,'details'=>$details]);
    cms_write('audit', array_slice($audit, 0, 300));
}

function cms_start_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        $forwardedProto = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $forwardedProto === 'https';
        session_name('pn_cms');
        session_set_cookie_params(['httponly'=>true,'secure'=>$isSecure,'samesite'=>'Lax','path'=>'/']);
        session_start();
    }
}
function cms_current_user(): ?array {
    cms_start_session();
    $id = $_SESSION['cms_user'] ?? '';
    foreach (cms_users() as $user) if (($user['id'] ?? '') === $id && !empty($user['active'])) return $user;
    return null;
}
function cms_is_admin(): bool { $user = cms_current_user(); return $user !== null && !empty($user['admin']); }
function cms_can(string $section): bool { $user = cms_current_user(); return $user !== null && (!empty($user['admin']) || in_array($section, $user['permissions'] ?? [], true)); }
function cms_require_login(): void { if (!cms_current_user()) { header('Location: /admin/'); exit; } }
function cms_require(string $section): void { cms_require_login(); if (!cms_can($section)) { http_response_code(403); exit('Нет доступа к этому разделу.'); } }
function cms_csrf(): string { cms_start_session(); return $_SESSION['csrf'] ??= bin2hex(random_bytes(24)); }
function cms_verify_csrf(): void { if (!hash_equals(cms_csrf(), (string) ($_POST['csrf'] ?? ''))) { http_response_code(419); exit('Форма устарела. Обновите страницу и повторите действие.'); } }
function cms_login(string $username, string $password): bool {
    cms_start_session();
    $attempts = $_SESSION['login_attempts'] ?? ['count'=>0,'at'=>0];
    if ($attempts['count'] >= 5 && time() - $attempts['at'] < 600) return false;
    foreach (cms_users() as $user) {
        if (!empty($user['active']) && hash_equals((string) $user['username'], $username) && password_verify($password, (string) $user['password_hash'])) {
            session_regenerate_id(true); $_SESSION['cms_user'] = $user['id']; unset($_SESSION['login_attempts']); cms_log('Вход'); return true;
        }
    }
    $_SESSION['login_attempts'] = ['count'=>$attempts['count'] + 1, 'at'=>time()];
    return false;
}
function cms_store_upload(array $file, string $kind): string {
    if (empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return '';
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) throw new RuntimeException('Не удалось загрузить файл.');
    $config = cms_config(); $max = $kind === 'pdf' ? (int)$config['max_pdf_bytes'] : (int)$config['max_image_bytes'];
    if ((int)$file['size'] > $max) throw new RuntimeException('Файл слишком большой.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $allowed = $kind === 'pdf' ? ['application/pdf'=>'pdf'] : ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if (!isset($allowed[$mime])) throw new RuntimeException('Этот тип файла не разрешён.');
    cms_ensure_dirs(); $name = date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], CMS_UPLOADS . '/' . $name)) throw new RuntimeException('Не удалось сохранить загруженный файл.');
    return 'uploads/' . $name;
}
function cms_upload(string $field, string $kind): string { return cms_store_upload($_FILES[$field] ?? [], $kind); }
function cms_upload_at(string $field, int $index, string $kind): string {
    if (!isset($_FILES[$field]['tmp_name'][$index])) return '';
    $file = [];
    foreach (['name','type','tmp_name','error','size'] as $key) $file[$key] = $_FILES[$field][$key][$index] ?? null;
    return cms_store_upload($file, $kind);
}
function cms_replace_inline_uploads($html, string $field): string {
    $html = (string)$html;
    $stored = [];
    return preg_replace_callback('/<img\b([^>]*)>/iu', static function ($match) use ($field, &$stored) {
        $indexValue = cms_html_attribute($match[1], 'data-upload-index');
        if ($indexValue === '') return $match[0];
        if (!ctype_digit($indexValue)) throw new RuntimeException('Не удалось определить картинку внутри текста.');
        $index = (int)$indexValue;
        if (!array_key_exists($index, $stored)) {
            $stored[$index] = cms_upload_at($field, $index, 'image');
            if ($stored[$index] === '') throw new RuntimeException('Не удалось загрузить картинку внутри текста.');
        }
        $alt = cms_text(cms_html_attribute($match[1], 'alt'), 300);
        return '<img src="' . h($stored[$index]) . '" alt="' . h($alt) . '">';
    }, $html) ?? '';
}
