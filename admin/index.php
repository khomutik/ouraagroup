<?php
declare(strict_types=1);
require dirname(__DIR__) . '/cms.php';
require dirname(__DIR__) . '/archive-lib.php';
cms_start_session();

function admin_flash(string $message, string $type = 'ok'): void { $_SESSION['admin_flash'] = [$type, $message]; }
function admin_take_flash(): ?array { $flash = $_SESSION['admin_flash'] ?? null; unset($_SESSION['admin_flash']); return $flash; }
function admin_redirect(string $url = '/admin/'): never { header('Location: ' . $url); exit; }
function admin_input(string $name, string $value = '', string $type = 'text', string $label = '', bool $required = false): void { ?>
  <label class="field"><?= $label ? '<span>' . h($label) . '</span>' : '' ?><input type="<?= h($type) ?>" name="<?= h($name) ?>" value="<?= h($value) ?>"<?= $required ? ' required' : '' ?>></label>
<?php }
function admin_site_text_group_for_section(string $section): string {
    return match ($section) {
        'home' => 'Главная',
        'announcements' => 'Объявления',
        'schedule' => 'Расписание',
        'library' => 'Библиотека',
        'speakers' => 'Спикерские',
        'services' => 'Служения',
        'newcomers' => 'Новичкам',
        'tradition' => '7-я традиция',
        'archive' => 'Архив',
        default => '',
    };
}
function admin_site_texts_form(array $content, string $group, string $returnTo): void {
    $fields = array_filter(cms_site_text_fields(), static fn(array $definition): bool => $definition[0] === $group || ($group === 'Главная' && $definition[0] === 'Общее'));
    if (!$fields) return;
    ?>
    <form method="post" class="admin-form admin-page-texts">
      <input type="hidden" name="action" value="save_section">
      <input type="hidden" name="section" value="site_texts">
      <input type="hidden" name="return_to" value="<?=h($returnTo)?>">
      <input type="hidden" name="csrf" value="<?=h(cms_csrf())?>">
      <h2>Заголовок и подписи страницы</h2>
      <p class="admin-tip">Заголовок и подписи сохраняются отдельно от основного текста и кнопок страницы.</p>
      <?php foreach ($fields as $key => $definition): $value = cms_site_text($content, $key); ?>
        <label class="field"><span><?=h($definition[1])?></span>
          <?php if ($key === 'home_heading' || str_contains($key, 'description') || str_ends_with($key, '_lead')): ?>
            <textarea name="site_text[<?=h($key)?>]" rows="3"><?=h($value)?></textarea>
          <?php else: ?>
            <input name="site_text[<?=h($key)?>]" value="<?=h($value)?>">
          <?php endif; ?>
          <?php if ($key === 'home_heading'): ?><small>Нажмите Enter там, где нужна новая строка, затем сохраните заголовок кнопкой сразу под этим полем.</small><?php endif; ?>
        </label>
        <?php if ($key === 'home_heading'): ?><button class="button" type="submit">Сохранить заголовок и подписи</button><?php endif; ?>
      <?php endforeach; ?>
      <button class="button" type="submit">Сохранить заголовок и подписи</button>
    </form>
    <?php
}
function admin_page_links(array $content): void { ?>
  <nav class="admin-page-links" aria-label="Редактировать страницу сайта">
    <h2>Какую страницу изменить?</h2>
    <div class="admin-page-links__grid">
      <?php foreach ([''=>'Главная страница','schedule'=>'Расписание','newcomers'=>'Новичкам','announcements'=>'Объявления','library'=>'Библиотека','speakers'=>'Спикерские','services'=>'Служения','tradition'=>'7-я традиция','archive'=>'Архив'] as $key=>$label):
        if (!cms_can($key === '' ? 'pages' : $key)) continue; ?>
        <a href="<?= $key === '' ? '/admin/' : '/admin/?section=' . rawurlencode($key) ?>"><?=h($label)?></a>
      <?php endforeach; ?>
      <?php foreach (cms_can('pages') ? ($content['pages']['custom'] ?? []) : [] as $page): ?>
        <a href="/admin/?edit=<?=rawurlencode((string)($page['id'] ?? ''))?>"><?=h((string)($page['title'] ?? 'Страница'))?></a>
      <?php endforeach; ?>
    </div>
    <p>На странице редактора собраны её заголовки, тексты и кнопки. Каждая форма сохраняется отдельно.</p>
  </nav>
<?php }
function admin_document_position(string $name, string $selected, string $label = 'Расположение кнопки'): void { ?>
  <label class="field"><span><?=h($label)?></span><select name="<?=h($name)?>"><option value="top" <?=$selected==='top'?'selected':''?>>Вверху страницы</option><option value="bottom" <?=$selected!=='top'?'selected':''?>>Внизу страницы</option></select></label>
<?php }
function admin_rich(string $name, string $value, string $label, bool $required = false, bool $inlineImages = false): void { ?>
  <div class="field field--wide"><span id="rich-<?= h($name) ?>-label"><?= h($label) ?></span><textarea name="<?= h($name) ?>" class="js-rich" rows="7" data-required="<?= $required ? 'true' : 'false' ?>" data-inline-images="<?= $inlineImages ? 'true' : 'false' ?>" aria-labelledby="rich-<?= h($name) ?>-label"><?= h($value) ?></textarea></div>
<?php }
function admin_rich_legacy_text(string $value): string {
    if (trim($value) === '') return '';
    if (preg_match('~</?(?:p|div|h2|h3|blockquote|ul|ol|li|table|figure|pre|a|strong|em|span|br)\b~iu', $value)) return cms_sanitize_html($value);
    return '<p>' . nl2br(h($value), false) . '</p>';
}
function admin_file(string $name, string $current, string $label, string $accept): void { ?>
  <label class="field field--wide"><span><?= h($label) ?></span><input type="text" name="<?= h($name) ?>_url" value="<?= h($current) ?>" placeholder="Ссылка или путь к уже загруженному файлу"><input type="file" name="<?= h($name) ?>_file" accept="<?= h($accept) ?>"></label>
<?php }
function admin_array(array $value, string $key, int $i, string $default = ''): string { return (string)($value[$key] ?? $default); }
function admin_upload_value(string $field, int $index, string $current, string $kind): string {
    $upload = cms_upload_at($field . '_file', $index, $kind);
    if ($upload !== '') return $upload;
    return cms_safe_url($_POST[$field . '_url'][$index] ?? $current);
}
function admin_collect_pairs(string $labels, string $urls): array {
    $result = []; foreach (($_POST[$labels] ?? []) as $i => $label) { $url = cms_safe_url($_POST[$urls][$i] ?? ''); if ($url !== '') $result[] = ['label'=>cms_text($label, 160), 'url'=>$url]; } return $result;
}
function admin_collect_actions(string $prefix): array {
    $result = []; foreach (($_POST[$prefix . '_label'] ?? []) as $i => $label) { $url = cms_safe_url($_POST[$prefix . '_url'][$i] ?? ''); if (cms_text($label,160) === '' || $url === '') continue; $result[] = ['label'=>cms_text($label,160),'url'=>$url,'type'=>cms_text($_POST[$prefix . '_type'][$i] ?? 'primary',20),'color'=>cms_text($_POST[$prefix . '_color'][$i] ?? 'orange',20),'shape'=>cms_text($_POST[$prefix . '_shape'][$i] ?? 'rounded',20),'placement'=>cms_text($_POST[$prefix . '_placement'][$i] ?? 'bottom',20),'align'=>cms_text($_POST[$prefix . '_align'][$i] ?? 'center',20)]; } return $result;
}
function admin_collect_navigation(): array {
    $items=[]; foreach(($_POST['nav_title']??[]) as $i=>$title){$title=cms_text($title,120);$url=cms_safe_url($_POST['nav_url'][$i]??'');if($title===''||$url==='')continue;$items[]=['id'=>cms_text($_POST['nav_id'][$i]??'',40)?:cms_id(),'title'=>$title,'url'=>$url,'parent'=>cms_text($_POST['nav_parent'][$i]??'',80),'icon'=>admin_upload_value('nav_icon',$i,(string)($_POST['nav_icon_url'][$i]??''),'image'),'external'=>isset($_POST['nav_external'][$i])];}
    $socials=[]; foreach(($_POST['social_title']??[]) as $i=>$title){$title=cms_text($title,80);$url=cms_safe_url($_POST['social_url'][$i]??'');if($title===''||$url==='')continue;$socials[]=['title'=>$title,'url'=>$url,'icon'=>admin_upload_value('social_icon',$i,(string)($_POST['social_icon_url'][$i]??''),'image')];}
    return ['items'=>$items,'socials'=>$socials];
}
function admin_home_navigation_fields(array $navigation): void { ?>
<fieldset class="repeatable" data-repeat="navigation"><legend>Кнопки главной страницы и меню</legend><p class="admin-tip">Здесь меняются кнопки главной страницы и меню. Укажите название, ссылку и, при необходимости, картинку. Чтобы разместить пункт внутри раздела меню, заполните поле «Вложить в раздел».</p><div data-repeat-items><?php foreach(($navigation['items']??[]) as $i=>$item): ?><div class="card-editor" data-nav-item><input type="hidden" name="nav_id[]" value="<?=h($item['id']??'')?>"><div class="form-grid"><label class="field"><span>Название кнопки</span><input name="nav_title[]" value="<?=h($item['title']??'')?>" placeholder="Надпись"></label><label class="field"><span>Куда ведёт</span><input name="nav_url[]" value="<?=h($item['url']??'')?>" placeholder="Ссылка"></label><label class="field"><span>Вложить в раздел</span><input name="nav_parent[]" value="<?=h($item['parent']??'')?>" placeholder="Оставьте пустым для главной"></label><label class="field"><span>Картинка кнопки</span><input name="nav_icon_url[]" value="<?=h($item['icon']??'')?>" placeholder="Ссылка на картинку"></label></div><input type="file" name="nav_icon_file[]" accept="image/jpeg,image/png,image/webp"><label class="check"><input type="checkbox" name="nav_external[<?=$i?>]" <?=!empty($item['external'])?'checked':''?>> Открывать в новой вкладке</label><button type="button" class="remove-row">Удалить кнопку</button></div><?php endforeach; ?></div><button type="button" class="button button--quiet" data-add-row>Добавить кнопку</button><template><div class="card-editor" data-nav-item><input type="hidden" name="nav_id[]"><div class="form-grid"><label class="field"><span>Название кнопки</span><input name="nav_title[]" placeholder="Надпись"></label><label class="field"><span>Куда ведёт</span><input name="nav_url[]" placeholder="Ссылка"></label><label class="field"><span>Вложить в раздел</span><input name="nav_parent[]" placeholder="Оставьте пустым для главной"></label><label class="field"><span>Картинка кнопки</span><input name="nav_icon_url[]" placeholder="Ссылка на картинку"></label></div><input type="file" name="nav_icon_file[]" accept="image/jpeg,image/png,image/webp"><label class="check"><input type="checkbox" data-nav-external> Открывать в новой вкладке</label><button type="button" class="remove-row">Удалить кнопку</button></div></template></fieldset>
<fieldset class="repeatable" data-repeat="socials"><legend>Быстрые кнопки: Zoom, Telegram, MAX и другие</legend><p class="admin-tip">Первая ссылка управляет большой кнопкой входа на главной. Остальные быстрые ссылки показываются внизу главной страницы. Для каждой можно изменить название, адрес и картинку.</p><div data-repeat-items><?php foreach(($navigation['socials']??[]) as $item): ?><div class="card-editor"><div class="form-grid"><label class="field"><span>Название</span><input name="social_title[]" value="<?=h($item['title']??'')?>" placeholder="Название"></label><label class="field"><span>Ссылка</span><input name="social_url[]" value="<?=h($item['url']??'')?>" placeholder="Ссылка"></label><label class="field"><span>Картинка</span><input name="social_icon_url[]" value="<?=h($item['icon']??'')?>" placeholder="Ссылка на картинку"></label></div><input type="file" name="social_icon_file[]" accept="image/jpeg,image/png,image/webp"><button type="button" class="remove-row">Удалить</button></div><?php endforeach; ?></div><button type="button" class="button button--quiet" data-add-row>Добавить быструю ссылку</button><template><div class="card-editor"><div class="form-grid"><label class="field"><span>Название</span><input name="social_title[]" placeholder="Название"></label><label class="field"><span>Ссылка</span><input name="social_url[]" placeholder="Ссылка"></label><label class="field"><span>Картинка</span><input name="social_icon_url[]" placeholder="Ссылка на картинку"></label></div><input type="file" name="social_icon_file[]" accept="image/jpeg,image/png,image/webp"><button type="button" class="remove-row">Удалить</button></div></template></fieldset>
<?php }
function admin_page_catalog(array $content): array {
    $pages = [['key'=>'home','title'=>'Главная страница','section'=>'pages','group'=>'Основные страницы','published'=>true]];
    foreach (['schedule'=>'Расписание собраний','announcements'=>'Объявления','library'=>'Библиотека','speakers'=>'Спикерские','services'=>'Служения','tradition'=>'7-я традиция','archive'=>'Архив решений'] as $key=>$title) {
        $pages[]=['key'=>$key,'title'=>$title,'section'=>$key,'group'=>'Основные страницы','published'=>true];
    }
    foreach (($content['newcomers']['pages'] ?? []) as $page) {
        $slug = (string)($page['slug'] ?? '');
        if ($slug === '') continue;
        $pages[]=['key'=>'newcomer:' . $slug,'title'=>(string)($page['title'] ?? 'Страница'),'section'=>'newcomers','group'=>'Новичкам','published'=>!array_key_exists('published',$page) || !empty($page['published'])];
    }
    foreach (($content['pages']['custom'] ?? []) as $page) {
        $slug = (string)($page['slug'] ?? '');
        if ($slug === '') continue;
        $pages[]=['key'=>'custom:' . $slug,'title'=>(string)($page['title'] ?? 'Страница'),'section'=>'pages','group'=>'Пользовательские страницы','published'=>!array_key_exists('published',$page) || !empty($page['published'])];
    }
    return $pages;
}
function admin_page_editor_url(array $content, string $key): string {
    if ($key === 'home') return '/admin/';
    if (str_starts_with($key, 'newcomer:')) foreach (($content['newcomers']['pages'] ?? []) as $page) if (($page['slug'] ?? '') === substr($key, 9)) return '?section=newcomers&edit=' . rawurlencode((string)($page['id'] ?? ''));
    if (str_starts_with($key, 'custom:')) foreach (($content['pages']['custom'] ?? []) as $page) if (($page['slug'] ?? '') === substr($key, 7)) return '?edit=' . rawurlencode((string)($page['id'] ?? ''));
    return '?section=' . rawurlencode($key);
}
/** Normalize links written by the button editor to the URLs used by this site. */
function admin_structure_url_key(string $url): string {
    $url = trim($url);
    if (preg_match('#^https?://(?:www\.)?pochtinormalnye\.ru(?P<path>/.*)?$#i', $url, $match)) $url = (string)($match['path'] ?? '');
    return ltrim($url, '/');
}
/** Buttons which are actually rendered on the public home page. */
function admin_home_public_actions(array $content): array {
    $actions = cms_page_actions($content, 'home');
    $known = [];
    foreach ($actions as $action) $known[(string)($action['label'] ?? '') . "\x1F" . (string)($action['url'] ?? '')] = true;
    $add = static function (array $action) use (&$actions, &$known): void {
        $label = cms_text($action['label'] ?? '', 160); $url = cms_safe_url($action['url'] ?? '');
        if ($label === '' || $url === '') return;
        $id = $label . "\x1F" . $url; if (isset($known[$id])) return;
        $known[$id] = true; $actions[] = ['label'=>$label, 'url'=>$url];
    };
    $navigation = $content['navigation'] ?? cms_default_navigation();
    foreach (($navigation['items'] ?? []) as $item) {
        $url = (string)($item['url'] ?? '');
        if (($item['parent'] ?? '') !== '' || $url === 'index.html' || $url === 'schedule.html') continue;
        $add(['label'=>$item['title'] ?? '', 'url'=>$url]);
    }
    $add(['label'=>'Расписание собраний', 'url'=>'schedule.html']);
    foreach (($navigation['socials'] ?? []) as $i=>$item) {
        $title = cms_text($item['title'] ?? '', 80);
        if ($title === '') continue;
        $add(['label'=>$i === 0 ? 'Войти в ' . $title : 'Быстрая ссылка: ' . $title, 'url'=>$item['url'] ?? '']);
    }
    return $actions;
}
/**
 * One source of truth for the page explorer. It follows only actual buttons
 * rendered on pages; the menu is included solely as real buttons of Home.
 */
function admin_page_graph(array $content): array {
    $nodes = []; $urlToKey = [
        'index.html'=>'home', 'newcomers.html'=>'newcomer:menu', 'schedule.html'=>'schedule',
        'announcements.html'=>'announcements', 'library.html'=>'library', 'speakers.html'=>'speakers',
        'service.html'=>'services', 'tradition.html'=>'tradition', 'archive.html'=>'archive',
    ];
    foreach (admin_page_catalog($content) as $page) {
        $nodes[$page['key']] = $page;
        if (str_starts_with($page['key'], 'newcomer:')) {
            $slug = substr($page['key'], 9);
            $urlToKey['newcomers.html#' . $slug] = $page['key'];
            $urlToKey['#' . $slug] = $page['key'];
            $urlToKey[ltrim(cms_newcomer_path($slug), '/')] = $page['key'];
        }
        if (str_starts_with($page['key'], 'custom:')) {
            $slug = substr($page['key'], 7);
            $urlToKey['p/' . $slug] = $page['key'];
            $urlToKey['p/' . $slug . '/'] = $page['key'];
        }
    }
    $out = []; $incoming = []; $external = [];
    foreach ($nodes as $sourceKey=>$source) {
        $pageActions = $sourceKey === 'home' ? admin_home_public_actions($content) : cms_page_actions($content, $sourceKey);
        foreach ($pageActions as $action) {
            $label = cms_text($action['label'] ?? '', 160);
            $rawUrl = cms_safe_url($action['url'] ?? '');
            if ($label === '' || $rawUrl === '') continue;
            $url = admin_structure_url_key($rawUrl);
            if (isset($urlToKey[$url])) {
                $targetKey = $urlToKey[$url];
                $edgeKey = $sourceKey . "\x1F" . $targetKey;
                if (!isset($out[$sourceKey][$edgeKey])) $out[$sourceKey][$edgeKey] = ['target'=>$targetKey,'labels'=>[]];
                if (!in_array($label, $out[$sourceKey][$edgeKey]['labels'], true)) $out[$sourceKey][$edgeKey]['labels'][] = $label;
                continue;
            }
            $isMissingPage = str_starts_with($url, '#') || str_starts_with($url, 'newcomers.html#');
            $external[$sourceKey][] = ['label'=>$label,'url'=>$rawUrl,'missing'=>$isMissingPage];
        }
    }
    foreach ($out as $sourceKey=>$edges) {
        $out[$sourceKey] = array_values($edges);
        foreach ($out[$sourceKey] as $edge) $incoming[$edge['target']][] = ['source'=>$sourceKey,'labels'=>$edge['labels']];
    }
    return ['nodes'=>$nodes,'out'=>$out,'in'=>$incoming,'external'=>$external];
}
function admin_structure_url(string $key): string { return '?tab=structure&page=' . rawurlencode($key); }
function admin_structure_tree_node(array $graph, string $key, string $selectedKey, array $trail = [], array $labels = []): void {
    if (!isset($graph['nodes'][$key])) return;
    $node = $graph['nodes'][$key];
    $isCycle = in_array($key, $trail, true);
    $children = $isCycle ? [] : ($graph['out'][$key] ?? []);
    $hasChildren = !empty($children);
    ?>
    <li class="site-explorer__item<?= $key === $selectedKey ? ' is-selected' : '' ?><?= $isCycle ? ' is-cycle' : '' ?>" data-tree-item>
      <div class="site-explorer__row">
        <?php if ($hasChildren): ?><button type="button" class="site-explorer__toggle" data-tree-toggle aria-expanded="true" aria-label="Свернуть ветку <?=h($node['title'])?>">▾</button><?php else: ?><span class="site-explorer__toggle site-explorer__toggle--empty" aria-hidden="true"></span><?php endif; ?>
        <span class="site-explorer__page-icon" aria-hidden="true"></span>
        <span class="site-explorer__text"><a class="site-explorer__name" href="<?=h(admin_structure_url($key))?>"><?=h($node['title'])?></a><?php if ($labels): ?><span class="site-explorer__via">Кнопка: <?=h(implode(' / ', $labels))?></span><?php endif; ?><?php if (empty($node['published'])): ?><span class="site-explorer__draft">Не опубликована</span><?php endif; ?><?php if ($isCycle): ?><span class="site-explorer__cycle">Циклическая ссылка</span><?php endif; ?></span>
      </div>
      <?php if ($hasChildren): ?><ul class="site-explorer__children" data-tree-children><?php foreach ($children as $edge) admin_structure_tree_node($graph, $edge['target'], $selectedKey, array_merge($trail, [$key]), $edge['labels']); ?></ul><?php endif; ?>
    </li>
    <?php
}
function admin_structure_page_detail(array $content, array $graph, string $key): void {
    if (!isset($graph['nodes'][$key])) return;
    $node = $graph['nodes'][$key];
    ?>
    <section class="site-explorer__detail" aria-live="polite">
      <a class="mobile-explorer-back" href="?tab=structure">← К проводнику</a>
      <p class="site-explorer__crumb">Структура сайта / <?=h($node['group'] ?? 'Страницы')?></p>
      <h2><?=h($node['title'])?></h2>
      <p class="site-explorer__status"><?=empty($node['published']) ? 'Черновик: страница не опубликована.' : 'Опубликованная страница.'?></p>
      <a class="button" href="<?=h(admin_page_editor_url($content, $key))?>">Изменить содержимое</a>
      <section class="site-explorer__detail-section">
        <h3>Кнопки и настройки</h3>
        <p class="admin-tip">Здесь можно изменить кнопки выбранной страницы: название, адрес и внешний вид. Образец меняется сразу; после правки нажмите «Сохранить кнопки».</p>
        <form method="post" enctype="multipart/form-data" class="admin-form site-explorer__actions-form">
          <input type="hidden" name="action" value="save_page_actions"><input type="hidden" name="page_key" value="<?=h($key)?>"><input type="hidden" name="csrf" value="<?=h(cms_csrf())?>">
          <?php if ($key === 'home'): ?>
            <?php admin_home_navigation_fields($content['navigation'] ?? cms_default_navigation()); ?>
            <h4>Дополнительные кнопки на главной</h4>
            <?php admin_page_actions_fieldset(cms_page_actions($content, 'home'), 'page_action'); ?>
          <?php else: ?>
            <?php admin_page_actions_fieldset(cms_page_actions($content, $key), 'page_action'); ?>
          <?php endif; ?>
          <div class="form-actions"><button class="button">Опубликовать кнопки</button></div>
        </form>
      </section>
    </section>
    <?php
}
function admin_action_link_options(): array {
    static $options = null;
    if ($options !== null) return $options;
    $content = cms_content();
    $options = [
        'index.html' => 'Главная страница',
        'newcomers.html' => 'Новичкам — главное меню',
        'schedule.html' => 'Расписание собраний',
        'announcements.html' => 'Объявления',
        'library.html' => 'Библиотека',
        'speakers.html' => 'Спикерские',
        'service.html' => 'Служения',
        'tradition.html' => '7-я традиция',
        'archive.html' => 'Архив решений',
    ];
    foreach (($content['newcomers']['pages'] ?? []) as $page) {
        if (empty($page['published']) || ($page['slug'] ?? '') === 'menu') continue;
        $options[cms_newcomer_path((string)$page['slug'])] = 'Новичкам → ' . ($page['title'] ?? 'Страница');
    }
    foreach (($content['pages']['custom'] ?? []) as $page) {
        if (empty($page['published']) || empty($page['slug'])) continue;
        $options['/p/' . $page['slug']] = $page['title'] ?? 'Пользовательская страница';
    }
    return $options;
}
function admin_action_destination(array $options, string $selected): void {
    if (preg_match('~^/?newcomers\.html#([a-z0-9-]+)$~', $selected, $match)) $selected = cms_newcomer_path($match[1]);
    ?>
<label class="field"><span>Куда ведёт кнопка</span><select data-action-destination><option value="">Внешняя ссылка или адрес вручную</option><?php foreach ($options as $url=>$title): ?><option value="<?=h($url)?>" <?=$selected===$url?'selected':''?>><?=h($title)?></option><?php endforeach; ?></select></label>
<?php }
function admin_action_select(string $name, string $label, array $options, string $selected): void { $kind = substr($name, strrpos($name, '_') + 1); ?><fieldset class="button-option" data-button-kind="<?=h($kind)?>"><legend><?=h($label)?></legend><input type="hidden" name="<?=h($name)?>[]" value="<?=h($selected)?>" data-button-option="<?=h($kind)?>"><div class="button-option__choices"><?php foreach($options as $value=>$caption): ?><button type="button" class="button-choice button-choice--<?=h($kind)?>-<?=h($value)?> <?=$selected===$value?'is-selected':''?>" data-button-value="<?=h($value)?>" aria-pressed="<?=$selected===$value?'true':'false'?>"><span><?=h($caption)?></span></button><?php endforeach; ?></div></fieldset><?php }
function admin_action_row(array $action, string $prefix, bool $showPlacement = true): void { $types=['primary'=>'Основная','soft'=>'Светлая','outline'=>'Контурная','tab'=>'Вкладка','link'=>'Ссылка-текст']; $colors=['orange'=>'Оранжевый','blue'=>'Тёмно-синий','beige'=>'Бежевый']; $shapes=['rounded'=>'Скруглённая','pill'=>'Пилюля','square'=>'Прямоугольная']; $placements=['under-title'=>'Под заголовком','bottom'=>'Внизу страницы']; $aligns=['left'=>'Слева','center'=>'По центру','right'=>'Справа','full'=>'На всю ширину']; $url=$action['url']??''; ?>
<article class="button-editor"><button type="button" class="button-editor__mobile-toggle" data-button-editor-toggle aria-expanded="false"><span class="button-editor__summary-label"><?=h($action['label']??'Новая кнопка')?></span><span class="button-preview button-preview--summary">Пример кнопки</span><span aria-hidden="true">⌄</span></button><div class="button-editor__body"><div class="form-grid"><label class="field"><span>Название кнопки</span><input name="<?=h($prefix)?>_label[]" value="<?=h($action['label']??'')?>" placeholder="Например: Открыть протокол"></label><?php admin_action_destination(admin_action_link_options(), $url); ?></div><label class="field button-editor__manual-url"><span>Внешняя ссылка или адрес вручную</span><input name="<?=h($prefix)?>_url[]" value="<?=h($url)?>" data-action-url placeholder="https://... (заполнять, только если страницы нет в списке)"></label><div class="button-editor__settings"><?php admin_action_select($prefix . '_type','Вид',$types,$action['type']??'primary'); admin_action_select($prefix . '_color','Цвет',$colors,$action['color']??'orange'); admin_action_select($prefix . '_shape','Форма',$shapes,$action['shape']??'rounded'); if ($showPlacement) admin_action_select($prefix . '_placement','Место',$placements,$action['placement']??'bottom'); admin_action_select($prefix . '_align','Выравнивание',$aligns,$action['align']??'left'); ?><div class="button-preview-wrap"><span>Так будет выглядеть</span><span class="button-preview">Пример кнопки</span></div></div><span class="row-actions"><button type="button" class="move-row" data-move="up">↑ Выше</button><button type="button" class="move-row" data-move="down">↓ Ниже</button><button type="button" class="remove-row">Убрать</button></span></div></article>
<?php }
function admin_page_actions_fieldset(array $actions, string $prefix = 'page_action'): void { ?>
<fieldset class="repeatable page-actions-editor" data-repeat="page-actions"><legend>Кнопки этой страницы</legend><p class="admin-tip">Добавьте название и ссылку кнопки, затем выберите её вид, цвет, форму и место на странице. Образец справа показывает результат до сохранения.</p><div data-repeat-items><?php foreach ($actions as $action) admin_action_row($action, $prefix); ?></div><button type="button" class="button button--quiet" data-add-row>Добавить кнопку</button><template><?php admin_action_row([], $prefix); ?></template></fieldset>
<?php }
function admin_card_actions_fieldset(array $actions, string $prefix = 'card_action'): void { ?>
<fieldset class="repeatable page-actions-editor" data-repeat="card-actions"><legend>Кнопки внизу карточки</legend><p class="admin-tip">Кнопки показываются под содержимым карточки. Для каждой укажите название и ссылку; внешний вид можно проверить по образцу.</p><div data-repeat-items><?php foreach ($actions as $action) admin_action_row($action, $prefix, false); ?></div><button type="button" class="button button--quiet" data-add-row>Добавить кнопку</button><template><?php admin_action_row(['type'=>'tab','color'=>'orange','shape'=>'pill','align'=>'left'], $prefix, false); ?></template></fieldset>
<?php }
function admin_library_button_editor(array $item, array $content): void {
    $button = is_array($item['read_button'] ?? null) ? $item['read_button'] : [];
    $label = (string)($button['label'] ?? cms_site_text($content, 'library_read_button'));
    $types = ['classic'=>'Как сейчас','primary'=>'Основная','soft'=>'Светлая','outline'=>'Контурная','tab'=>'Вкладка','link'=>'Ссылка-текст'];
    $colors = ['orange'=>'Оранжевый','blue'=>'Тёмно-синий','beige'=>'Бежевый'];
    $shapes = ['rounded'=>'Скруглённая','pill'=>'Пилюля','square'=>'Прямоугольная'];
    $aligns = ['left'=>'Слева','center'=>'По центру','right'=>'Справа','full'=>'На всю ширину'];
?>
<article class="button-editor"><button type="button" class="button-editor__mobile-toggle" data-button-editor-toggle aria-expanded="false"><span class="button-editor__summary-label"><?=h($label)?></span><span class="button-preview button-preview--summary">Пример кнопки</span><span aria-hidden="true">⌄</span></button><div class="button-editor__body">
  <label class="field"><span>Название кнопки «Читать»</span><input name="lib_button_label[]" value="<?=h($label)?>" data-action-label></label>
  <p class="admin-tip">Кнопка открывает PDF или ссылку из поля выше. Если адрес не задан, кнопка не показывается.</p>
  <div class="button-editor__settings"><?php admin_action_select('lib_button_type','Вид',$types,$button['type']??'classic'); admin_action_select('lib_button_color','Цвет',$colors,$button['color']??'orange'); admin_action_select('lib_button_shape','Форма',$shapes,$button['shape']??'rounded'); admin_action_select('lib_button_align','Выравнивание',$aligns,$button['align']??'full'); ?><div class="button-preview-wrap"><span>Так будет выглядеть</span><span class="button-preview">Пример кнопки</span></div></div>
</div></article>
<?php }
function admin_service_action_select(string $field, string $label, array $options, string $selected, int $serviceIndex): void { ?>
<fieldset class="button-option" data-button-kind="<?=h($field)?>"><legend><?=h($label)?></legend><input type="hidden" name="service_action_<?=h($field)?>[<?=h((string)$serviceIndex)?>][]" value="<?=h($selected)?>" data-button-option="<?=h($field)?>"><div class="button-option__choices"><?php foreach($options as $value=>$caption): ?><button type="button" class="button-choice button-choice--<?=h($field)?>-<?=h($value)?> <?=$selected===$value?'is-selected':''?>" data-button-value="<?=h($value)?>" aria-pressed="<?=$selected===$value?'true':'false'?>"><span><?=h($caption)?></span></button><?php endforeach; ?></div></fieldset>
<?php }
function admin_service_action_row(array $action, int $serviceIndex): void { $types=['primary'=>'Основная','soft'=>'Светлая','outline'=>'Контурная','tab'=>'Вкладка','link'=>'Ссылка-текст']; $colors=['orange'=>'Оранжевый','blue'=>'Тёмно-синий','beige'=>'Бежевый']; $shapes=['rounded'=>'Скруглённая','pill'=>'Пилюля','square'=>'Прямоугольная']; $aligns=['left'=>'Слева','center'=>'По центру','right'=>'Справа','full'=>'На всю ширину']; $url=$action['url']??''; ?>
<article class="button-editor"><button type="button" class="button-editor__mobile-toggle" data-button-editor-toggle aria-expanded="false"><span class="button-editor__summary-label"><?=h($action['label']??'Новая кнопка')?></span><span class="button-preview button-preview--summary">Пример кнопки</span><span aria-hidden="true">⌄</span></button><div class="button-editor__body"><div class="form-grid"><label class="field"><span>Название кнопки</span><input name="service_action_label[<?=h((string)$serviceIndex)?>][]" value="<?=h($action['label']??'')?>" placeholder="Например: Написать служащему" data-action-label></label><?php admin_action_destination(admin_action_link_options(), $url); ?></div><label class="field button-editor__manual-url"><span>Внешняя ссылка или адрес вручную</span><input name="service_action_url[<?=h((string)$serviceIndex)?>][]" value="<?=h($url)?>" data-action-url placeholder="https://... (заполнять, только если страницы нет в списке)"></label><div class="button-editor__settings"><?php admin_service_action_select('type','Вид',$types,$action['type']??'tab',$serviceIndex); admin_service_action_select('color','Цвет',$colors,$action['color']??'orange',$serviceIndex); admin_service_action_select('shape','Форма',$shapes,$action['shape']??'pill',$serviceIndex); admin_service_action_select('align','Выравнивание',$aligns,$action['align']??'left',$serviceIndex); ?><div class="button-preview-wrap"><span>Так будет выглядеть</span><span class="button-preview">Пример кнопки</span></div></div><span class="row-actions"><button type="button" class="move-row" data-move="up">↑ Выше</button><button type="button" class="move-row" data-move="down">↓ Ниже</button><button type="button" class="remove-row">Убрать</button></span></div></article>
<?php }
function admin_service_actions_fieldset(array $actions, int $serviceIndex): void { ?>
<fieldset class="repeatable page-actions-editor" data-repeat="service-actions"><legend>Кнопки внизу карточки служения</legend><p class="admin-tip">Кнопки служения показываются под описанием карточки. Укажите название, ссылку и внешний вид; образец показывает результат.</p><div data-repeat-items><?php foreach ($actions as $action) admin_service_action_row($action, $serviceIndex); ?></div><button type="button" class="button button--quiet" data-add-row>Добавить кнопку</button><template><?php admin_service_action_row(['type'=>'tab','color'=>'orange','shape'=>'pill','align'=>'left'], $serviceIndex); ?></template></fieldset>
<?php }
function admin_collect_service_actions(int $serviceIndex): array {
    $result=[]; foreach (($_POST['service_action_label'][$serviceIndex] ?? []) as $i=>$label) { $url=cms_safe_url($_POST['service_action_url'][$serviceIndex][$i] ?? ''); if (cms_text($label,160)==='' || $url==='') continue; $result[]=['label'=>cms_text($label,160),'url'=>$url,'type'=>cms_text($_POST['service_action_type'][$serviceIndex][$i]??'tab',20),'color'=>cms_text($_POST['service_action_color'][$serviceIndex][$i]??'orange',20),'shape'=>cms_text($_POST['service_action_shape'][$serviceIndex][$i]??'pill',20),'placement'=>'bottom','align'=>cms_text($_POST['service_action_align'][$serviceIndex][$i]??'left',20)]; }
    return $result;
}
function admin_service_editor(array $item, int $serviceIndex): void { ?>
<div class="service-editor" data-service>
  <input type="hidden" name="service_id[]" value="<?=h($item['id']??'')?>">
  <div class="form-grid"><label class="field"><span>Название</span><input name="service_title[]" value="<?=h($item['title']??'')?>"></label><label class="field"><span>Ценз трезвости</span><input name="sobriety[]" value="<?=h($item['sobriety']??'')?>"></label><label class="field"><span>Срок служения</span><input name="term[]" value="<?=h($item['term']??'')?>"></label></div>
  <label class="check"><input type="checkbox" name="service_open[<?=h((string)$serviceIndex)?>]" <?=!empty($item['open'])?'checked':''?>> Свободное служение</label>
  <div class="holders"><strong>Служащие и ротация</strong><div data-holders><?php foreach(($item['holders']??[]) as $holder): ?><div class="holder-row"><input name="holder_name[<?=h((string)$serviceIndex)?>][]" value="<?=h($holder['name']??'')?>" placeholder="Имя"><input type="date" name="holder_rotation[<?=h((string)$serviceIndex)?>][]" value="<?=h($holder['rotation']??'')?>"><button type="button" class="remove-row">Убрать</button></div><?php endforeach; ?></div><button type="button" class="button button--quiet" data-add-holder>Добавить служащего</button></div>
  <?php admin_service_actions_fieldset($item['actions']??[], $serviceIndex); ?>
  <button type="button" class="remove-row">Удалить служение</button>
</div>
<?php }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'login') {
            cms_verify_csrf();
            if (cms_login(cms_text($_POST['username'] ?? '', 80), (string)($_POST['password'] ?? ''))) admin_redirect('/admin/');
            admin_flash('Не удалось войти: проверьте логин и пароль.', 'error'); admin_redirect('/admin/');
        }
        if ($action === 'logout') { cms_verify_csrf(); session_destroy(); admin_redirect('/admin/'); }
        cms_require_login(); cms_verify_csrf();

        if ($action === 'save_user') {
            if (!cms_is_admin()) throw new RuntimeException('Только администратор может управлять пользователями.');
            $users = cms_users(); $id = cms_text($_POST['id'] ?? '', 40); $editing = null; $index = null;
            foreach ($users as $i => $user) if (($user['id'] ?? '') === $id) { $editing = $user; $index = $i; break; }
            $username = cms_text($_POST['username'] ?? '', 80);
            if (!preg_match('/^[\p{L}\p{N}._-]{3,80}$/u', $username)) throw new RuntimeException('Логин: от 3 символов, буквы, цифры, точка, дефис или подчёркивание.');
            foreach ($users as $user) if (($user['id'] ?? '') !== $id && cms_lower($user['username']) === cms_lower($username)) throw new RuntimeException('Такой логин уже есть.');
            $password = (string)($_POST['password'] ?? '');
            if (!$editing && cms_length($password) < 12) throw new RuntimeException('Для нового пользователя задайте пароль не короче 12 символов.');
            if ($password !== '' && cms_length($password) < 12) throw new RuntimeException('Новый пароль должен быть не короче 12 символов.');
            $permissions = array_values(array_intersect(CMS_SECTIONS, $_POST['permissions'] ?? []));
            $record = ['id'=>$editing['id'] ?? cms_id(),'username'=>$username,'password_hash'=>$editing['password_hash'] ?? password_hash($password, PASSWORD_DEFAULT),'active'=>isset($_POST['active']),'admin'=>isset($_POST['admin']),'permissions'=>$permissions];
            if ($password !== '') $record['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            if ($record['admin']) $record['permissions'] = CMS_SECTIONS;
            if ($index === null) $users[] = $record; else $users[$index] = $record;
            if (!array_filter($users, static fn($u) => !empty($u['admin']) && !empty($u['active']))) throw new RuntimeException('Нужно оставить хотя бы одного активного администратора.');
            cms_write('users', $users); cms_log('Пользователь сохранён', $username); admin_flash('Пользователь сохранён.'); admin_redirect('/admin/?tab=users');
        }
        if ($action === 'restore_content') {
            if (!cms_is_admin()) throw new RuntimeException('Восстанавливать резервные копии могут только администраторы.');
            cms_restore_backup('content', (string)($_POST['backup'] ?? ''));
            cms_log('Восстановлена резервная копия содержимого', basename((string)($_POST['backup'] ?? '')));
            admin_flash('Содержимое сайта восстановлено из резервной копии.'); admin_redirect('/admin/');
        }
        if ($action === 'delete_announcement') {
            cms_require('announcements'); $id = cms_text($_POST['id'] ?? '', 40); $content = cms_content();
            $content['announcements'] = array_values(array_filter($content['announcements'] ?? [], static fn($item) => ($item['id'] ?? '') !== $id));
            cms_write('content', $content); cms_log('Удалено объявление', $id); admin_flash('Объявление удалено. Его можно вернуть из резервной копии.'); admin_redirect('/admin/?section=announcements');
        }
        if ($action === 'delete_archive_item') {
            cms_require('archive'); $id=cms_text($_POST['id']??'',40); $content=cms_content(); $content['archive']['items']=array_values(array_filter($content['archive']['items']??[],static fn($item)=>($item['id']??'')!==$id)); cms_write('content',$content); admin_flash('Документ удалён. Его можно вернуть из резервной копии.'); admin_redirect('/admin/?section=archive');
        }
        if ($action === 'delete_speaker') {
            cms_require('speakers'); $id=cms_text($_POST['id']??'',40); $content=cms_content();
            $content['speakers']['items']=array_values(array_filter($content['speakers']['items']??[],static fn($item)=>($item['id']??'')!==$id));
            cms_write('content',$content); cms_log('Удалена спикерская', $id); admin_flash('Спикерская удалена. Её можно вернуть из резервной копии.'); admin_redirect('/admin/?section=speakers');
        }
        if ($action === 'reorder_announcement') {
            cms_require('announcements'); $id=cms_text($_POST['id']??'',40); $direction=($_POST['direction']??'up')==='down'?'down':'up'; $content=cms_content(); $items=$content['announcements']??[]; foreach($items as $i=>$item) if(($item['id']??'')===$id){$j=$direction==='up'?$i-1:$i+1;if(isset($items[$j])){[$items[$i],$items[$j]]=[$items[$j],$items[$i]];} break;} foreach($items as $i=>$item)$items[$i]['order']=$i; $content['announcements']=$items; cms_write('content',$content); admin_redirect('/admin/?section=announcements');
        }
        if ($action === 'reorder_archive_item') {
            cms_require('archive'); $id=cms_text($_POST['id']??'',40); $direction=($_POST['direction']??'up')==='down'?'down':'up'; $content=cms_content(); $items=$content['archive']['items']??[]; foreach($items as $i=>$item) if(($item['id']??'')===$id){$j=$direction==='up'?$i-1:$i+1;if(isset($items[$j])){[$items[$i],$items[$j]]=[$items[$j],$items[$i]];} break;} foreach($items as $i=>$item)$items[$i]['order']=$i; $content['archive']['items']=$items; cms_write('content',$content); admin_redirect('/admin/?section=archive');
        }
        if ($action === 'delete_newcomer') {
            cms_require('newcomers'); $id = cms_text($_POST['id'] ?? '', 40); $content = cms_content();
            $pages = $content['newcomers']['pages'] ?? [];
            $target = null; foreach ($pages as $page) if (($page['id'] ?? '') === $id) $target = $page;
            if (($target['slug'] ?? '') === 'menu') throw new RuntimeException('Главное меню раздела удалить нельзя, а то новичок войдёт и встретит коридор без дверей.');
            $content['newcomers']['pages'] = array_values(array_filter($pages, static fn($page) => ($page['id'] ?? '') !== $id));
            cms_write('content', $content); cms_log('Удалена страница для новичков', $id); admin_flash('Страница удалена.'); admin_redirect('/admin/?section=newcomers');
        }
        if ($action === 'delete_page') {
            cms_require('pages'); $id = cms_text($_POST['id'] ?? '', 40); $content = cms_content();
            $content['pages']['custom'] = array_values(array_filter($content['pages']['custom'] ?? [], static fn($page) => ($page['id'] ?? '') !== $id));
            cms_write('content', $content); cms_log('Удалена произвольная страница', $id); admin_flash('Страница удалена.'); admin_redirect('/admin/');
        }
        if ($action === 'save_page_actions') {
            $key = cms_text($_POST['page_key'] ?? '', 180);
            if ($key === '' || !in_array($key, array_column(admin_page_catalog(cms_content()), 'key'), true)) throw new RuntimeException('Выберите существующую страницу.');
            $requiredSection = str_starts_with($key, 'newcomer:') ? 'newcomers' : (($key === 'home' || str_starts_with($key, 'custom:')) ? 'pages' : $key);
            cms_require($requiredSection);
            $content = cms_content();
            if ($key === 'home') $content['navigation'] = admin_collect_navigation();
            cms_set_page_actions($content, $key, admin_collect_actions('page_action'));
            cms_write('content', $content); cms_log('Кнопки страницы сохранены', $key); admin_flash('Кнопки страницы сохранены.'); admin_redirect('/admin/?tab=structure&page=' . rawurlencode($key));
        }

        if ($action === 'save_section') {
            $section = (string)($_POST['section'] ?? '');
            $textReturnTo = (string)($_POST['return_to'] ?? '');
            $textGroup = $section === 'site_texts' ? admin_site_text_group_for_section($textReturnTo) : '';
            cms_require($textGroup !== '' ? ($textReturnTo === 'home' ? 'pages' : $textReturnTo) : $section);
            $content = cms_content();
            if ($section === 'announcements') {
                $items = $content['announcements'] ?? []; $id = cms_text($_POST['id'] ?? '', 40); $index = null; foreach ($items as $i=>$item) if (($item['id']??'')===$id) $index=$i;
                $current = $index === null ? [] : $items[$index]; $image = cms_upload('image_file','image') ?: cms_safe_url($_POST['image_url'] ?? ($current['image'] ?? ''));
                $title = cms_text($_POST['title'] ?? '', 180); $eventDate = cms_text($_POST['event_date'] ?? '', 10); $eventTime = cms_text($_POST['event_time'] ?? '', 5); $body = cms_sanitize_html(cms_replace_inline_uploads($_POST['body'] ?? '', 'body_inline_images'));
                if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $eventDate, $dateParts) || !checkdate((int)$dateParts[2], (int)$dateParts[3], (int)$dateParts[1])) throw new RuntimeException('Укажите правильную дату события.');
                if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $eventTime)) throw new RuntimeException('Укажите правильное время события.');
                if ($title === '') throw new RuntimeException('Введите тему события.');
                $record = ['id'=>$id ?: cms_id(),'title'=>$title,'event_date'=>$eventDate,'event_time'=>$eventTime,'order'=>$index===null?0:(int)($current['order']??$index),'hide_after'=>cms_text($_POST['hide_after'] ?? '', 10),'image'=>$image,'image_alt'=>$title . '. ' . $eventDate . ' в ' . $eventTime . ' по МСК','body'=>$body,'links'=>admin_collect_actions('card_action')];
                if ($index===null) array_unshift($items,$record); else $items[$index]=$record; foreach ($items as $i=>$item) $items[$i]['order']=$i; $content['announcements']=$items;
                $target='/admin/?section=announcements';
            } elseif ($section === 'archive') {
                $driveUrl = cms_safe_url($_POST['archive_drive_url'] ?? ($content['archive']['drive_url'] ?? ''));
                $drivePosition = (($_POST['drive_position'] ?? 'bottom') === 'top') ? 'top' : 'bottom';
                if (($_POST['archive_mode'] ?? '') === 'settings') {
                    $content['archive'] = array_merge($content['archive'] ?? [], ['drive_url'=>$driveUrl,'drive_position'=>$drivePosition]);
                    $target='/admin/?section=archive';
                } else {
                    $items=$content['archive']['items']??[]; $id=cms_text($_POST['id']??'',40); $index=null; foreach($items as $i=>$item)if(($item['id']??'')===$id)$index=$i;
                    $title=cms_text($_POST['title']??'',180); if($title==='')throw new RuntimeException('Введите тему решения.');
                    $date=cms_text($_POST['event_date']??'',10); if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))throw new RuntimeException('Укажите дату решения.');
                    $current=$index===null?[]:$items[$index]; $image=cms_upload('image_file','image')?:cms_safe_url($_POST['image_url']??($current['image']??''));
                    $record=['id'=>$id?:cms_id(),'title'=>$title,'event_date'=>$date,'event_time'=>cms_text($_POST['event_time']??'',5),'order'=>$index===null?0:(int)($current['order']??$index),'image'=>$image,'body'=>cms_sanitize_html(cms_replace_inline_uploads($_POST['body']??'','body_inline_images')),'protocol_text'=>archive_plain_text(cms_text($_POST['protocol_text']??($current['protocol_text']??''),200000)),'treasurer_report'=>cms_sanitize_html($_POST['treasurer_report']??($current['treasurer_report']??'')),'links'=>admin_collect_actions('card_action')];
                    if($index===null)array_unshift($items,$record);else $items[$index]=$record; foreach($items as $i=>$item)$items[$i]['order']=$i;
                    $content['archive']=['drive_url'=>$driveUrl,'drive_position'=>$drivePosition,'items'=>$items]; $target='/admin/?section=archive';
                }
            } elseif ($section === 'schedule') {
                $days=[]; foreach (($_POST['day'] ?? []) as $i=>$day) { if (cms_text($day,60)!=='') $days[]=['day'=>cms_text($day,60),'topic'=>cms_sanitize_html($_POST['topic'][$i] ?? '')]; }
                $content['schedule']=['time'=>cms_text($_POST['time'] ?? '',80),'zoom_url'=>cms_safe_url($_POST['zoom_url'] ?? ''),'days'=>$days]; $target='/admin/?section=schedule';
            } elseif ($section === 'library') {
                $items=[]; foreach (($_POST['lib_title'] ?? []) as $i=>$title) {
                    if (cms_text($title,180)==='') continue;
                    $readButton = [
                        'label'=>cms_text($_POST['lib_button_label'][$i]??cms_site_text($content,'library_read_button'),160),
                        'type'=>cms_text($_POST['lib_button_type'][$i]??'classic',20),
                        'color'=>cms_text($_POST['lib_button_color'][$i]??'orange',20),
                        'shape'=>cms_text($_POST['lib_button_shape'][$i]??'rounded',20),
                        'align'=>cms_text($_POST['lib_button_align'][$i]??'full',20),
                    ];
                    $items[]=['id'=>cms_text($_POST['lib_id'][$i]??'',40) ?: cms_id(),'title'=>cms_text($title,180),'description'=>cms_sanitize_html($_POST['lib_description'][$i]??''),'cover'=>admin_upload_value('cover',$i,(string)($_POST['cover_url'][$i]??''),'image'),'resource'=>admin_upload_value('resource',$i,(string)($_POST['resource_url'][$i]??''),'pdf'),'read_button'=>$readButton];
                }
                $content['library']=['note'=>cms_sanitize_html($_POST['note'] ?? ''),'other_url'=>cms_safe_url($_POST['other_url'] ?? ''),'other_position'=>(($_POST['other_position']??'bottom')==='top'?'top':'bottom'),'items'=>$items]; $target='/admin/?section=library';
            } elseif ($section === 'speakers') {
                $data=$content['speakers']??[];
                if (($_POST['speaker_mode'] ?? '') === 'settings') {
                    $materials=[]; foreach (($_POST['material_title'] ?? []) as $i=>$title) { if (cms_text($title,180)==='') continue; $materials[]=['id'=>cms_text($_POST['material_id'][$i]??'',40) ?: cms_id(),'title'=>cms_text($title,180),'resource'=>admin_upload_value('material',$i,(string)($_POST['material_url'][$i]??''),'pdf')]; }
                    $data['drive_url']=cms_safe_url($_POST['drive_url'] ?? '');
                    $data['intro']=cms_sanitize_html($_POST['intro'] ?? '');
                    $data['privacy']=cms_sanitize_html($_POST['privacy'] ?? '');
                    $data['materials']=$materials;
                    unset($data['drive_position'], $data['closing']);
                } else {
                    $items=$data['items']??[]; $id=cms_text($_POST['id']??'',40); $index=null;
                    foreach($items as $i=>$item) if(($item['id']??'')===$id)$index=$i;
                    $date=cms_text($_POST['event_date']??'',10); $title=cms_text($_POST['title']??'',180); $speaker=cms_text($_POST['speaker']??'',180);
                    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/',$date,$dateParts) || !checkdate((int)$dateParts[2],(int)$dateParts[3],(int)$dateParts[1])) throw new RuntimeException('Укажите правильную дату спикерской.');
                    if ($title==='' || $speaker==='') throw new RuntimeException('Заполните тему и имя спикера.');
                    $audioUrl=cms_safe_url($_POST['audio_url']??'');
                    if ($audioUrl!=='' && cms_drive_audio_preview_url($audioUrl)==='') throw new RuntimeException('Для аудио нужна ссылка на отдельный файл в Google Диске, а не на папку.');
                    $streamUrl=cms_safe_url($_POST['audio_stream_url']??'');
                    if ($streamUrl!=='' && cms_drive_audio_preview_url($streamUrl)==='') throw new RuntimeException('Для облегчённой записи нужна ссылка на отдельный файл в Google Диске.');
                    $record=['id'=>$id?:cms_id(),'event_date'=>$date,'title'=>$title,'speaker'=>$speaker,'city'=>cms_text($_POST['city']??'',180),'home_group'=>cms_text($_POST['home_group']??'',180),'sobriety'=>cms_text($_POST['sobriety']??'',120),'audio_url'=>$audioUrl,'audio_stream_url'=>$streamUrl,'links'=>admin_collect_actions('card_action'),'order'=>$index===null?0:(int)($items[$index]['order']??$index)];
                    if($index===null)array_unshift($items,$record);else$items[$index]=$record;
                    foreach($items as $i=>$item)$items[$i]['order']=$i;
                    $data['items']=$items;
                }
                $content['speakers']=$data; $target='/admin/?section=speakers';
            } elseif ($section === 'services') {
                $items=[]; foreach (($_POST['service_title'] ?? []) as $i=>$title) { if (cms_text($title,180)==='') continue; $holders=[]; foreach (($_POST['holder_name'][$i] ?? []) as $j=>$name) if (cms_text($name,180)!=='') $holders[]=['name'=>cms_text($name,180),'rotation'=>cms_text($_POST['holder_rotation'][$i][$j]??'',10)]; $items[]=['id'=>cms_text($_POST['service_id'][$i]??'',40) ?: cms_id(),'title'=>cms_text($title,180),'sobriety'=>cms_text($_POST['sobriety'][$i]??'',100),'term'=>cms_text($_POST['term'][$i]??'',100),'open'=>isset($_POST['service_open'][$i]),'holders'=>$holders,'actions'=>admin_collect_service_actions((int)$i)]; }
                $content['services']=['chart_url'=>cms_safe_url($_POST['chart_url'] ?? ''),'chart_position'=>(($_POST['chart_position']??'top')==='bottom'?'bottom':'top'),'lead'=>cms_sanitize_html($_POST['lead'] ?? ''),'items'=>$items]; $target='/admin/?section=services';
            } elseif ($section === 'newcomers') {
                $items = $content['newcomers']['pages'] ?? []; $id = cms_text($_POST['id'] ?? '', 40); $index = null;
                foreach ($items as $i=>$item) if (($item['id']??'')===$id) $index=$i;
                $slug = $id === 'newcomer-aa-test' ? 'aa-test' : strtolower(cms_text($_POST['slug'] ?? '', 80));
                if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) throw new RuntimeException('Адрес страницы: только латинские буквы, цифры и дефисы.');
                foreach ($items as $i=>$item) if ($i !== $index && ($item['slug']??'') === $slug) throw new RuntimeException('Такой адрес страницы уже используется.');
                $title = cms_text($_POST['title'] ?? '', 180); if ($title === '') throw new RuntimeException('Введите заголовок страницы.');
                $body = cms_sanitize_html(cms_replace_inline_uploads($_POST['body'] ?? '', 'body_inline_images'));
                $record = ['id'=>$id ?: cms_id(),'slug'=>$slug,'title'=>$title,'body'=>$body,'actions'=>admin_collect_actions('action'),'order'=>(int)($_POST['order']??0),'published'=>isset($_POST['published'])];
                if ($slug === 'aa-test') {
                    $defaults = cms_default_aa_test();
                    $questions = array_map(static fn($question): string => cms_text($question, 500), (array)($_POST['test_question'] ?? []));
                    if (count($questions) !== 12 || in_array('', $questions, true)) throw new RuntimeException('Заполните все 12 вопросов теста.');
                    $record['test_questions'] = $questions;
                    foreach (['seo_title','seo_description','test_attention_heading','test_attention_text','test_other_heading','test_other_text'] as $key) {
                        $record[$key] = cms_text($_POST[$key] ?? '', 600) ?: $defaults[$key];
                    }
                }
                if ($index===null) $items[]=$record; else $items[$index]=$record; usort($items,static fn($a,$b)=>(int)($a['order']??0)<=>(int)($b['order']??0));
                $content['newcomers']=['pages'=>$items]; $target='/admin/?section=newcomers';
            } elseif ($section === 'tradition') {
                $payments=[]; foreach (($_POST['payment_title']??[]) as $i=>$title) { if (cms_text($title,180)==='') continue; $payments[]=['id'=>cms_text($_POST['payment_id'][$i]??'',40) ?: cms_id(),'title'=>cms_text($title,180),'value'=>cms_text($_POST['payment_value'][$i]??'',300),'url'=>cms_safe_url($_POST['payment_url'][$i]??''),'comment'=>cms_sanitize_html($_POST['payment_comment'][$i]??''),'icon'=>cms_text($_POST['payment_icon'][$i]??'',300)]; }
                $content['tradition']=['report_url'=>cms_safe_url($_POST['report_url']??''),'report_position'=>(($_POST['report_position']??'top')==='bottom'?'bottom':'top'),'lead'=>cms_sanitize_html($_POST['lead']??''),'treasurer'=>cms_text($_POST['treasurer']??'',180),'payments'=>$payments]; $target='/admin/?section=tradition';
            } elseif ($section === 'pages') {
                if (($_POST['page_kind']??'') === 'basics') {
                    $content['pages']['home_intro']=cms_sanitize_html(cms_replace_inline_uploads($_POST['home_intro']??'','home_intro_inline_images'));
                    cms_set_page_actions($content, 'home', admin_collect_actions('home_action'));
                    $content['navigation'] = admin_collect_navigation();
                } else {
                    $items=$content['pages']['custom']??[]; $id=cms_text($_POST['id']??'',40); $index=null; foreach($items as $i=>$item)if(($item['id']??'')===$id)$index=$i;
                    $slug=strtolower(cms_text($_POST['slug']??'',80)); if(!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/',$slug))throw new RuntimeException('Адрес страницы: только латинские буквы, цифры и дефисы.');
                    foreach($items as $i=>$item)if($i!==$index&&($item['slug']??'')===$slug)throw new RuntimeException('Такой адрес страницы уже используется.');
                    $title=cms_text($_POST['title']??'',180); if($title==='')throw new RuntimeException('Введите заголовок страницы.');
                    $blocks=[]; foreach (($_POST['block_body'] ?? []) as $blockBody) { $blockBody=cms_sanitize_html(cms_replace_inline_uploads($blockBody,'block_inline_images')); if (trim(strip_tags($blockBody)) !== '' || str_contains($blockBody,'<img')) $blocks[]=['id'=>cms_id(),'body'=>$blockBody]; }
                    if (!$blocks && trim((string)($_POST['body']??'')) !== '') $blocks[]=['id'=>cms_id(),'body'=>cms_sanitize_html(cms_replace_inline_uploads($_POST['body'],'body_inline_images'))];
                    $record=['id'=>$id?:cms_id(),'slug'=>$slug,'title'=>$title,'description'=>cms_text($_POST['description']??'',300),'body'=>$blocks[0]['body']??'','blocks'=>$blocks,'published'=>isset($_POST['published'])];
                    if($index===null)$items[]=$record;else$items[$index]=$record; $content['pages']['custom']=$items; cms_set_page_actions($content,'custom:'. $slug, admin_collect_actions('page_action'));
                }
                $target='/admin/';
            } elseif ($section === 'site_texts') {
                $texts = $content['site_texts'] ?? [];
                $postedTexts = $_POST['site_text'] ?? [];
                if (!is_array($postedTexts)) throw new RuntimeException('Не удалось прочитать подписи страницы.');
                foreach (cms_site_text_fields() as $key => $definition) {
                    if (!array_key_exists($key, $postedTexts)) continue;
                    if ($textGroup !== '' && $definition[0] !== $textGroup && !($textGroup === 'Главная' && $definition[0] === 'Общее')) continue;
                    $value = cms_text($postedTexts[$key], 1200);
                    $texts[$key] = $value !== '' ? $value : $definition[2];
                }
                $content['site_texts'] = $texts;
                $target = $textGroup === 'Главная' ? '/admin/' : ($textGroup !== '' ? '/admin/?section=' . rawurlencode($textReturnTo) : '/admin/?section=site_texts');
            } elseif ($section === 'navigation') {
                $content['navigation'] = admin_collect_navigation(); $target='/admin/';
            } else throw new RuntimeException('Неизвестный раздел.');
            cms_write('content',$content); cms_log('Раздел обновлён', $section); admin_flash('Изменения опубликованы.'); admin_redirect($target);
        }
    } catch (Throwable $error) { admin_flash($error->getMessage(), 'error'); admin_redirect($_SERVER['HTTP_REFERER'] ?? '/admin/'); }
}

$users = cms_users(); $user = cms_current_user(); $flash = admin_take_flash();
if (!$user): ?>
<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Админка — Почти нормальные</title><link rel="stylesheet" href="admin.css?v=25"></head><body class="admin-login"><main class="login-card"><h1>Админка сайта</h1><?php if ($flash): ?><p class="notice notice--<?=h($flash[0])?>"><?=h($flash[1])?></p><?php endif; ?><?php if (!$users): ?><p>Админка ещё не настроена. Администратор должен открыть <code>/setup.php</code> по инструкции.</p><?php else: ?><form method="post"><input type="hidden" name="action" value="login"><input type="hidden" name="csrf" value="<?=h(cms_csrf())?>"><?php admin_input('username','','text','Логин'); ?><label class="field"><span>Пароль</span><input type="password" name="password" autocomplete="current-password" required></label><button class="button" type="submit">Войти</button></form><?php endif; ?></main></body></html>
<?php exit; endif;

$tab = (string)($_GET['tab'] ?? 'home'); $section = (string)($_GET['section'] ?? ''); $content = cms_content();
if ($section === 'pages') {
    $edit = (string)($_GET['edit'] ?? '');
    admin_redirect('/admin/' . ($edit !== '' ? '?edit=' . rawurlencode($edit) : ''));
}
if ($section === 'navigation') admin_redirect('/admin/');
if ($section !== '' && !cms_can($section)) { http_response_code(403); exit('Нет доступа к этому разделу.'); }
$structureGraph = null; $structureGroups = []; $structureSelectedPage = '';
if ($tab === 'structure' && !$section) {
    $structureGraph = admin_page_graph($content);
    $roots = []; foreach ($structureGraph['nodes'] as $key=>$node) if (empty($structureGraph['in'][$key])) $roots[] = $key;
    $structureSelectedPage = cms_text($_GET['page'] ?? '', 180);
    if (!isset($structureGraph['nodes'][$structureSelectedPage])) $structureSelectedPage = isset($structureGraph['nodes']['newcomer:menu']) ? 'newcomer:menu' : ($roots[0] ?? array_key_first($structureGraph['nodes']));
    $seen = []; $markSeen = null;
    $markSeen = static function (string $key) use (&$markSeen, &$seen, $structureGraph): void { if (isset($seen[$key])) return; $seen[$key] = true; foreach ($structureGraph['out'][$key] ?? [] as $edge) $markSeen($edge['target']); };
    foreach ($roots as $key) $markSeen($key);
    $cycleRoots = array_keys(array_filter($structureGraph['nodes'], static fn($node, $key) => !isset($seen[$key]), ARRAY_FILTER_USE_BOTH));
    $structureGroups = ['Основные страницы'=>[], 'Новичкам'=>[], 'Пользовательские страницы'=>[], 'Замкнутые переходы'=>[]];
    foreach ($roots as $key) $structureGroups[$structureGraph['nodes'][$key]['group'] ?? 'Основные страницы'][] = $key;
    foreach ($cycleRoots as $key) $structureGroups['Замкнутые переходы'][] = $key;
}
$structureMobileLanding = $structureGraph !== null && !isset($_GET['page']);
?>
<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Админка — Почти нормальные</title><link rel="stylesheet" href="admin.css?v=25"></head><body class="<?=$structureMobileLanding?'admin-structure--landing':''?>"><header class="admin-header"><button type="button" class="admin-menu-toggle" data-admin-menu-toggle aria-controls="admin-nav" aria-expanded="false">☰ <span>Разделы</span></button><a href="/" class="admin-brand">Почти нормальные <small>админка</small></a><form method="post"><input type="hidden" name="action" value="logout"><input type="hidden" name="csrf" value="<?=h(cms_csrf())?>"><button class="link-button">Выйти (<?=h($user['username'])?>)</button></form></header><div class="admin-layout"><aside id="admin-nav" class="admin-nav" aria-label="Разделы админки"><div class="admin-nav__mobile-heading"><strong>Разделы</strong><button type="button" class="admin-nav__close" data-admin-menu-close aria-label="Закрыть разделы">×</button></div><a href="/admin/" class="<?=(!$section && $tab==='home')?'is-active':''?>">Главная страница</a><a href="?tab=structure" class="<?=($tab==='structure')?'is-active':''?>">Структура сайта</a><?php if ($structureGraph): ?><section class="admin-nav__explorer" aria-label="Проводник реальных переходов"><p>Переходы страниц</p><?php foreach ($structureGroups as $group=>$keys): if (!$keys) continue; ?><section class="site-explorer__group"><h3><?=h($group)?></h3><ul class="site-explorer__list"><?php foreach ($keys as $key) admin_structure_tree_node($structureGraph, $key, $structureSelectedPage); ?></ul></section><?php endforeach; ?></section><?php endif; ?><?php foreach (cms_section_labels() as $key=>$label): if (cms_can($key) && !in_array($key, ['navigation','pages','site_texts'], true)): ?><a href="?section=<?=$key?>" class="<?=$section===$key?'is-active':''?>"><?=h($label)?></a><?php endif; endforeach; ?><?php if (cms_is_admin()): ?><a href="?tab=users" class="<?=($tab==='users')?'is-active':''?>">Пользователи</a><?php endif; ?></aside><main class="admin-main"><?php if ($flash): ?><p class="notice notice--<?=h($flash[0])?>"><?=h($flash[1])?></p><?php endif; ?>
<?php
$pageTextGroup = admin_site_text_group_for_section(
    (!$section && $tab === 'home' && empty($_GET['edit'])) ? 'home' : $section
);
if ($pageTextGroup !== '') admin_site_texts_form($content, $pageTextGroup, $section ?: 'home');
?>
<?php if (!$section && $tab === 'home'): ?>
<p class="admin-tip">Выберите страницу ниже. В её редакторе меняются заголовки, тексты и кнопки. Каждую форму нужно сохранить отдельно; затем проверьте результат на сайте.</p>
<?php endif; ?>
<?php if ($structureGraph): ?>
  <h1>Структура сайта</h1>
  <p class="admin-tip">Здесь показано, как страницы связаны кнопками. Выберите страницу в списке, чтобы изменить её содержимое или кнопки перехода.</p>
  <button type="button" class="mobile-open-explorer button button--quiet" data-admin-menu-toggle>Открыть проводник</button>
  <section class="site-explorer site-explorer--detail-only" aria-label="Настройки выбранной страницы">
    <?php admin_structure_page_detail($content, $structureGraph, $structureSelectedPage); ?>
  </section>
<?php endif; ?>
<?php if ($tab === 'users' && cms_is_admin()): $editId=(string)($_GET['edit']??''); $edit=[]; foreach ($users as $candidate) if (($candidate['id']??'')===$editId) $edit=$candidate; ?>
  <div class="section-heading"><h1>Пользователи</h1><a class="button button--quiet" href="?tab=users&edit=new">Добавить пользователя</a></div>
  <?php if ($editId): ?><form method="post" class="admin-form"><input type="hidden" name="action" value="save_user"><input type="hidden" name="csrf" value="<?=h(cms_csrf())?>"><input type="hidden" name="id" value="<?=h($edit['id']??'')?>"><h2><?= $edit ? 'Редактирование: '.h($edit['username']) : 'Новый пользователь' ?></h2><div class="form-grid"><?php admin_input('username',$edit['username']??'','text','Логин'); ?><label class="field"><span><?= $edit?'Новый пароль (оставьте пустым, чтобы не менять)':'Пароль (минимум 12 символов)' ?></span><input type="password" name="password" autocomplete="new-password"></label></div><label class="check"><input type="checkbox" name="active" <?=(!$edit || !empty($edit['active']))?'checked':''?>> Аккаунт активен</label><label class="check"><input type="checkbox" name="admin" <?=!empty($edit['admin'])?'checked':''?>> Администратор: может управлять пользователями и всеми разделами</label><fieldset><legend>Разрешённые разделы</legend><?php foreach (cms_section_labels() as $key=>$label): ?><label class="check"><input type="checkbox" name="permissions[]" value="<?=$key?>" <?=in_array($key,$edit['permissions']??[],true)?'checked':''?>> <?=h($label)?></label><?php endforeach; ?></fieldset><div class="form-actions"><button class="button">Сохранить пользователя</button><a class="button button--quiet" href="?tab=users">Отмена</a></div></form><?php endif; ?>
  <div class="table-wrap"><table><thead><tr><th>Логин</th><th>Доступ</th><th>Состояние</th><th></th></tr></thead><tbody><?php foreach ($users as $candidate): ?><tr><td><?=h($candidate['username'])?></td><td><?=!empty($candidate['admin'])?'Администратор':h(implode(', ',array_map(static fn($p)=>cms_section_labels()[$p]??$p,$candidate['permissions']??[])))?></td><td><?=!empty($candidate['active'])?'Активен':'Отключён'?></td><td><a href="?tab=users&edit=<?=h($candidate['id'])?>">Изменить</a></td></tr><?php endforeach; ?></tbody></table></div>
<?php endif; ?>
<?php if ($section === 'announcements'): $items=$content['announcements']??[]; $editId=(string)($_GET['edit']??''); $edit=[]; foreach($items as $item)if(($item['id']??'')===$editId)$edit=$item; ?>
  <div class="section-heading"><h1>Объявления</h1><a class="button button--quiet" href="?section=announcements&edit=new">Новое объявление</a></div>
  <?php if ($editId): ?>
    <form method="post" enctype="multipart/form-data" class="admin-form">
      <input type="hidden" name="action" value="save_section"><input type="hidden" name="section" value="announcements"><input type="hidden" name="csrf" value="<?=h(cms_csrf())?>"><input type="hidden" name="id" value="<?=h($edit['id']??'')?>">
      <h2><?= $edit ? 'Редактировать объявление' : 'Добавить объявление' ?></h2>
      <?php admin_file('image',$edit['image']??'','1. Картинка (необязательно)','image/jpeg,image/png,image/webp'); ?>
      <div class="form-grid"><?php admin_input('event_date',$edit['event_date']??'','date','2. Дата события *',true); admin_input('event_time',$edit['event_time']??'','time','3. Время события по МСК *',true); ?></div>
      <?php admin_input('title',$edit['title']??'','text','4. Тема события *',true); ?>
      <?php admin_rich('body',$edit['body']??'','5. Текст события (необязательно)',false,true); ?>
      <?php admin_input('hide_after',$edit['hide_after']??'','date','Скрыть после даты (необязательно)'); ?>
      <?php admin_card_actions_fieldset($edit['links']??[], 'card_action'); ?>
      <div class="form-actions"><button class="button">Опубликовать</button><a class="button button--quiet" href="?section=announcements">Отмена</a></div>
    </form>
  <?php endif; ?>
  <?php usort($items, static fn($a,$b)=>(int)($a['order']??PHP_INT_MAX)<=>(int)($b['order']??PHP_INT_MAX)); ?><div class="item-list item-list--announcements"><?php foreach($items as $item): ?><article><strong><?=h($item['title']??'')?></strong><span class="announcement-list__when"><time datetime="<?=h($item['event_date']??'')?>"><?=h($item['event_date']??'')?></time><span aria-hidden="true">·</span><time datetime="<?=h(($item['event_date']??'') . 'T' . ($item['event_time']??''))?>"><?=h($item['event_time']??'')?></time><span>МСК</span></span><div class="item-list__actions"><form method="post"><input type="hidden" name="action" value="reorder_announcement"><input type="hidden" name="csrf" value="<?=h(cms_csrf())?>"><input type="hidden" name="id" value="<?=h($item['id']??'')?>"><input type="hidden" name="direction" value="up"><button class="move-row" title="Поднять выше" aria-label="Поднять объявление выше">↑</button></form><form method="post"><input type="hidden" name="action" value="reorder_announcement"><input type="hidden" name="csrf" value="<?=h(cms_csrf())?>"><input type="hidden" name="id" value="<?=h($item['id']??'')?>"><input type="hidden" name="direction" value="down"><button class="move-row" title="Опустить ниже" aria-label="Опустить объявление ниже">↓</button></form><a href="?section=announcements&edit=<?=h($item['id']??'')?>">Изменить</a><form method="post" onsubmit="return confirm('Удалить объявление? Его можно будет вернуть из резервной копии.')"><input type="hidden" name="action" value="delete_announcement"><input type="hidden" name="csrf" value="<?=h(cms_csrf())?>"><input type="hidden" name="id" value="<?=h($item['id']??'')?>"><button class="link-danger">Удалить</button></form></div></article><?php endforeach; ?></div>
<?php endif; ?>
<?php if ($section === 'archive'):
    $data=$content['archive']??[]; $items=$data['items']??[]; $editId=(string)($_GET['edit']??''); $edit=[];
    foreach($items as $item) if(($item['id']??'')===$editId) $edit=$item;
?>
  <div class="section-heading"><h1>Архив решений</h1><a class="button button--quiet" href="?section=archive&edit=new">Новое решение</a></div>
  <p class="admin-tip">К каждому решению можно добавить кнопки со ссылками. Для каждой кнопки можно выбрать текст, адрес и внешний вид.</p>
  <form method="post" class="admin-form">
    <input type="hidden" name="action" value="save_section"><input type="hidden" name="section" value="archive"><input type="hidden" name="archive_mode" value="settings"><input type="hidden" name="csrf" value="<?=h(cms_csrf())?>">
    <?php admin_input('archive_drive_url',$data['drive_url']??'','url','Ссылка на Google Диск'); admin_document_position('drive_position',$data['drive_position']??'bottom','Где показывать кнопку Google Диска'); ?>
    <button class="button">Сохранить настройки архива</button>
  </form>
  <?php if($editId): ?>
    <form method="post" enctype="multipart/form-data" class="admin-form">
      <input type="hidden" name="action" value="save_section"><input type="hidden" name="section" value="archive"><input type="hidden" name="csrf" value="<?=h(cms_csrf())?>">
      <input type="hidden" name="id" value="<?=h($edit['id']??'')?>"><input type="hidden" name="archive_drive_url" value="<?=h($data['drive_url']??'')?>"><input type="hidden" name="drive_position" value="<?=h($data['drive_position']??'bottom')?>">
      <h2><?= $edit?'Редактировать решение':'Добавить решение' ?></h2>
      <?php admin_file('image',$edit['image']??'','1. Картинка (необязательно)','image/jpeg,image/png,image/webp'); ?>
      <div class="form-grid"><?php admin_input('event_date',$edit['event_date']??'','date','2. Дата решения *',true); admin_input('event_time',$edit['event_time']??'','time','3. Время (необязательно)'); ?></div>
      <?php admin_input('title',$edit['title']??'','text','4. Тема решения *',true); admin_rich('body',$edit['body']??'','5. Повестка РС',false,true); ?>
      <label class="field"><span>6. Протокол РС и результаты голосований</span><textarea name="protocol_text" rows="18"><?=h($edit['protocol_text']??'')?></textarea></label>
      <?php admin_rich('treasurer_report',$edit['treasurer_report']??'','7. Отчёт казначея (отдельный раскрывающийся блок)'); ?>
      <?php admin_card_actions_fieldset($edit['links']??[], 'card_action'); ?>
      <div class="form-actions"><button class="button">Опубликовать решение</button><a class="button button--quiet" href="?section=archive">Отмена</a></div>
    </form>
  <?php endif; ?>
  <div class="item-list"><?php foreach($items as $item): ?><article><strong><?=h($item['title']??'')?></strong><span><?=h($item['event_date']??'')?></span><div class="item-list__actions"><form method="post"><input type="hidden" name="action" value="reorder_archive_item"><input type="hidden" name="csrf" value="<?=h(cms_csrf())?>"><input type="hidden" name="id" value="<?=h($item['id']??'')?>"><input type="hidden" name="direction" value="up"><button class="move-row" title="Поднять выше" aria-label="Поднять решение выше">↑</button></form><form method="post"><input type="hidden" name="action" value="reorder_archive_item"><input type="hidden" name="csrf" value="<?=h(cms_csrf())?>"><input type="hidden" name="id" value="<?=h($item['id']??'')?>"><input type="hidden" name="direction" value="down"><button class="move-row" title="Опустить ниже" aria-label="Опустить решение ниже">↓</button></form><a href="?section=archive&edit=<?=h($item['id']??'')?>">Изменить</a><form method="post" onsubmit="return confirm('Удалить решение?')"><input type="hidden" name="action" value="delete_archive_item"><input type="hidden" name="csrf" value="<?=h(cms_csrf())?>"><input type="hidden" name="id" value="<?=h($item['id']??'')?>"><button class="link-danger">Удалить</button></form></div></article><?php endforeach; ?></div>
<?php endif; ?>
<?php if ($section === 'schedule'): $data=$content['schedule']??[]; ?>
<h1>Расписание</h1><form method="post" class="admin-form"><input type="hidden" name="action" value="save_section"><input type="hidden" name="section" value="schedule"><input type="hidden" name="csrf" value="<?=h(cms_csrf())?>"><div class="form-grid"><?php admin_input('time',$data['time']??'','text','Время собрания'); admin_input('zoom_url',$data['zoom_url']??'','url','Ссылка на Zoom'); ?></div>
<fieldset class="repeatable" data-repeat="days"><legend>Дни и темы</legend><p class="admin-tip">Слева — день недели, справа — текст темы. В теме можно выбрать «Обычный текст» или заголовок через меню «Стиль текста».</p><div data-repeat-items><?php foreach(($data['days']??[]) as $day): ?><div class="schedule-row"><label class="field schedule-row__day"><span>День недели</span><input name="day[]" value="<?=h($day['day']??'')?>" placeholder="Например: Понедельник"></label><div class="field schedule-row__topic"><span>Тема собрания</span><textarea name="topic[]" class="js-rich" data-label="Тема собрания" rows="4"><?=h($day['topic']??'')?></textarea></div><button type="button" class="remove-row">Убрать</button></div><?php endforeach; ?></div><button type="button" class="button button--quiet" data-add-row>Добавить день</button><template><div class="schedule-row"><label class="field schedule-row__day"><span>День недели</span><input name="day[]" placeholder="Например: Понедельник"></label><div class="field schedule-row__topic"><span>Тема собрания</span><textarea name="topic[]" class="js-rich" data-label="Тема собрания" rows="4"></textarea></div><button type="button" class="remove-row">Убрать</button></div></template></fieldset><button class="button">Сохранить расписание</button></form><?php endif; ?>
<?php if ($section === 'library'): $data=$content['library']??[]; ?>
<h1>Библиотека</h1>
<form method="post" enctype="multipart/form-data" class="admin-form">
  <input type="hidden" name="action" value="save_section"><input type="hidden" name="section" value="library"><input type="hidden" name="csrf" value="<?=h(cms_csrf())?>">
  <?php admin_rich('note',$data['note']??'','Текст над карточками',false,true); admin_input('other_url',$data['other_url']??'','url','Ссылка «Другая литература»'); admin_document_position('other_position',$data['other_position']??'bottom','Где показывать кнопку Google Диска'); ?>
  <fieldset class="repeatable" data-repeat="library"><legend>Карточки книг и брошюр</legend>
    <div data-repeat-items><?php foreach(($data['items']??[]) as $item): ?>
      <div class="card-editor"><input type="hidden" name="lib_id[]" value="<?=h($item['id']??'')?>"><input name="lib_title[]" value="<?=h($item['title']??'')?>" placeholder="Название">
        <div class="field"><span>Описание книги</span><textarea name="lib_description[]" class="js-rich" data-label="Описание книги" rows="3"><?=h(admin_rich_legacy_text((string)($item['description']??'')))?></textarea></div>
        <label>Обложка: ссылка<input name="cover_url[]" value="<?=h($item['cover']??'')?>"></label><input type="file" name="cover_file[]" accept="image/jpeg,image/png,image/webp">
        <label>PDF или внешняя ссылка<input name="resource_url[]" value="<?=h($item['resource']??'')?>"></label><input type="file" name="resource_file[]" accept="application/pdf">
        <?php admin_library_button_editor($item, $content); ?>
        <button type="button" class="remove-row">Убрать карточку</button>
      </div>
    <?php endforeach; ?></div>
    <button type="button" class="button button--quiet" data-add-row>Добавить материал</button>
    <template><div class="card-editor"><input type="hidden" name="lib_id[]"><input name="lib_title[]" placeholder="Название">
      <div class="field"><span>Описание книги</span><textarea name="lib_description[]" class="js-rich" data-label="Описание книги" rows="3"></textarea></div>
      <label>Обложка: ссылка<input name="cover_url[]"></label><input type="file" name="cover_file[]" accept="image/jpeg,image/png,image/webp">
      <label>PDF или внешняя ссылка<input name="resource_url[]"></label><input type="file" name="resource_file[]" accept="application/pdf">
      <?php admin_library_button_editor([], $content); ?>
      <button type="button" class="remove-row">Убрать карточку</button>
    </div></template>
  </fieldset>
  <button class="button">Сохранить библиотеку</button>
</form>
<?php endif; ?>
<?php if ($section === 'speakers'):
    $data=$content['speakers']??[]; $items=$data['items']??[]; $editId=(string)($_GET['edit']??''); $edit=[];
    foreach($items as $item) if(($item['id']??'')===$editId)$edit=$item;
    $items=cms_speakers_newest_first($items);
?>
  <h1>Спикерские</h1>
  <form method="post" enctype="multipart/form-data" class="admin-form">
    <input type="hidden" name="action" value="save_section"><input type="hidden" name="section" value="speakers"><input type="hidden" name="speaker_mode" value="settings"><input type="hidden" name="csrf" value="<?=h(cms_csrf())?>">
    <h2>Об анонимности и материалы</h2>
    <?php admin_rich('intro',$data['intro']??'','Вступительный текст',false,true); admin_rich('privacy',$data['privacy']??'','Блок об анонимности',false,true); ?>
    <fieldset class="repeatable" data-repeat="materials"><legend>Материалы</legend><div data-repeat-items><?php foreach(($data['materials']??[]) as $item): ?><div class="repeat-row repeat-row--stack"><input type="hidden" name="material_id[]" value="<?=h($item['id']??'')?>"><input name="material_title[]" value="<?=h($item['title']??'')?>" placeholder="Название"><input name="material_url[]" value="<?=h($item['resource']??'')?>" placeholder="PDF или внешняя ссылка"><input type="file" name="material_file[]" accept="application/pdf"><button type="button" class="remove-row">Убрать</button></div><?php endforeach; ?></div><button type="button" class="button button--quiet" data-add-row>Добавить материал</button><template><div class="repeat-row repeat-row--stack"><input type="hidden" name="material_id[]"><input name="material_title[]" placeholder="Название"><input name="material_url[]" placeholder="PDF или внешняя ссылка"><input type="file" name="material_file[]" accept="application/pdf"><button type="button" class="remove-row">Убрать</button></div></template></fieldset>
    <?php admin_input('drive_url',$data['drive_url']??'','url','Кнопка Google Диска между материалами и карточками'); ?>
    <button class="button">Сохранить тексты и материалы</button>
  </form>
  <div class="section-heading"><h2>Записи спикерских — новые сверху</h2><a class="button button--quiet" href="?section=speakers&edit=new">Добавить спикерскую</a></div>
  <p class="admin-tip">Записи остаются на Google Диске и не занимают место на сервере. Встроенный плеер загружается только по нажатию и использует облегчённую копию, если она указана.</p>
  <?php if($editId): ?>
    <form method="post" class="admin-form">
      <input type="hidden" name="action" value="save_section"><input type="hidden" name="section" value="speakers"><input type="hidden" name="speaker_mode" value="card"><input type="hidden" name="csrf" value="<?=h(cms_csrf())?>"><input type="hidden" name="id" value="<?=h($edit['id']??'')?>">
      <h2><?= $edit?'Редактировать спикерскую':'Новая спикерская' ?></h2>
      <div class="form-grid"><?php admin_input('event_date',$edit['event_date']??'','date','Дата *',true); admin_input('title',$edit['title']??'','text','Тема спикерской *',true); admin_input('speaker',$edit['speaker']??'','text','Спикер *',true); admin_input('city',$edit['city']??'','text','Город'); admin_input('home_group',$edit['home_group']??'','text','Домашняя группа'); admin_input('sobriety',$edit['sobriety']??'','text','Трезвость'); ?></div>
      <?php admin_input('audio_url',$edit['audio_url']??'','url','Ссылка на MP3-файл в Google Диске (необязательно)'); ?>
      <?php admin_input('audio_stream_url',$edit['audio_stream_url']??'','url','Облегчённая запись для плеера (необязательно)'); ?>
      <p class="admin-tip">Вставляйте ссылки на отдельные открытые файлы Google Диска. Основная кнопка ведёт к оригиналу; встроенный плеер использует облегчённую копию. Если копия не указана, плеер использует оригинал.</p>
      <?php admin_card_actions_fieldset($edit['links']??[], 'card_action'); ?>
      <div class="form-actions"><button class="button">Опубликовать спикерскую</button><a class="button button--quiet" href="?section=speakers">Отмена</a></div>
    </form>
  <?php endif; ?>
  <div class="item-list"><?php foreach($items as $item): ?><article><strong><?=h($item['title']??'')?></strong><span><?=h(cms_date_ru((string)($item['event_date']??'')))?> · <?=h($item['speaker']??'')?></span><div class="item-list__actions"><a href="?section=speakers&edit=<?=h($item['id']??'')?>">Изменить</a><form method="post" onsubmit="return confirm('Удалить спикерскую? Её можно будет вернуть из резервной копии.')"><input type="hidden" name="action" value="delete_speaker"><input type="hidden" name="csrf" value="<?=h(cms_csrf())?>"><input type="hidden" name="id" value="<?=h($item['id']??'')?>"><button class="link-danger">Удалить</button></form></div></article><?php endforeach; ?></div>
<?php endif; ?>
<?php if ($section === 'services'): $data=$content['services']??[]; ?>
  <h1>Служения</h1>
  <form method="post" class="admin-form" id="services-form">
    <input type="hidden" name="action" value="save_section"><input type="hidden" name="section" value="services"><input type="hidden" name="csrf" value="<?=h(cms_csrf())?>">
    <?php admin_input('chart_url',$data['chart_url']??'','url','Ссылка на график служений'); admin_document_position('chart_position',$data['chart_position']??'top','Где показывать кнопку графика'); ?>
    <?php admin_rich('lead',admin_rich_legacy_text((string)($data['lead']??'')),'Вступительный текст'); ?>
    <fieldset class="repeatable" data-repeat="services"><legend>Список служений</legend><div data-repeat-items><?php foreach(($data['items']??[]) as $i=>$item) admin_service_editor($item, $i); ?></div><button type="button" class="button button--quiet" data-add-row>Добавить служение</button><template><?php admin_service_editor([], 0); ?></template></fieldset>
    <button class="button">Сохранить служения</button>
  </form>
<?php endif; ?>

<?php if ($section === 'newcomers'): $items=$content['newcomers']['pages']??[]; $editId=(string)($_GET['edit']??''); $edit=[]; foreach($items as $item)if(($item['id']??'')===$editId)$edit=$item; ?>
  <div class="section-heading"><h1>Новичкам</h1><a class="button button--quiet" href="?section=newcomers&edit=new">Добавить страницу</a></div>
  <p class="admin-tip">Большинство карточек открываются внутри раздела «Новичкам». Тест — отдельная страница с адресом <code>/p/test-na-alkogolizm</code>, чтобы её могли находить поисковики. Ссылки вида <code>#aa</code> ведут на остальные карточки раздела.</p>
  <?php if($editId): $isTest = ($edit['slug'] ?? '') === 'aa-test'; $testDefaults = cms_default_aa_test(); ?>
  <form method="post" enctype="multipart/form-data" class="admin-form">
    <input type="hidden" name="action" value="save_section"><input type="hidden" name="section" value="newcomers">
    <input type="hidden" name="csrf" value="<?=h(cms_csrf())?>"><input type="hidden" name="id" value="<?=h($edit['id']??'')?>">
    <h2><?= $edit?'Редактирование страницы':'Новая страница' ?></h2>
    <?php if($isTest): ?><p class="admin-tip">Это отдельная страница теста: <a href="/p/test-na-alkogolizm" target="_blank" rel="noopener">посмотреть на сайте</a>. Здесь меняются её заголовок, описание для поиска, вступление, вопросы и оба варианта результата. Ответы посетителей на сервер не отправляются.</p><?php endif; ?>
    <div class="form-grid">
      <?php admin_input('title',$edit['title']??'','text','Заголовок',true); ?>
      <?php if($isTest): ?><label class="field"><span>Адрес теста</span><input value="/p/test-na-alkogolizm" readonly></label><input type="hidden" name="slug" value="aa-test"><?php else: ?><?php admin_input('slug',$edit['slug']??'','text','Адрес латиницей, например sponsor',true); ?><?php endif; ?>
      <?php admin_input('order',(string)($edit['order']??count($items)),'number','Порядок'); ?>
    </div>
    <label class="check"><input type="checkbox" name="published" <?=(!$edit||!empty($edit['published']))?'checked':''?>> Показывать на сайте</label>
    <?php if($isTest): ?>
      <?php admin_input('seo_title',$edit['seo_title']??$testDefaults['seo_title'],'text','Заголовок для поиска'); ?>
      <label class="field"><span>Описание для поисковиков</span><textarea name="seo_description" rows="3"><?=h($edit['seo_description']??$testDefaults['seo_description'])?></textarea></label>
    <?php endif; ?>
    <?php admin_rich('body',$edit['body']??($isTest?$testDefaults['body']:''),$isTest?'Текст над тестом':'Текст и блоки страницы',false,true); ?>
    <?php if($isTest): ?>
      <fieldset><legend>12 вопросов теста</legend>
      <?php foreach(($edit['test_questions']??$testDefaults['test_questions']) as $questionIndex=>$question): ?>
        <label class="field"><span>Вопрос <?=($questionIndex+1)?></span><textarea name="test_question[]" rows="2" required><?=h((string)$question)?></textarea></label>
      <?php endforeach; ?>
      </fieldset>
      <fieldset><legend>Результат теста</legend>
        <?php admin_input('test_attention_heading',$edit['test_attention_heading']??$testDefaults['test_attention_heading'],'text','Заголовок при 4 и более ответах «Да»'); ?>
        <label class="field"><span>Текст при 4 и более ответах «Да»</span><textarea name="test_attention_text" rows="3"><?=h($edit['test_attention_text']??$testDefaults['test_attention_text'])?></textarea></label>
        <?php admin_input('test_other_heading',$edit['test_other_heading']??$testDefaults['test_other_heading'],'text','Заголовок при 0–3 ответах «Да»'); ?>
        <label class="field"><span>Текст при 0–3 ответах «Да»</span><textarea name="test_other_text" rows="3"><?=h($edit['test_other_text']??$testDefaults['test_other_text'])?></textarea></label>
      </fieldset>
    <?php endif; ?>
    <?php admin_page_actions_fieldset($edit['actions']??[], 'action'); ?>
    <div class="form-actions"><button class="button">Опубликовать</button><a class="button button--quiet" href="?section=newcomers">Отмена</a></div>
  </form>
  <?php endif; ?>
  <div class="item-list"><?php foreach($items as $item): ?><article><strong><?=h($item['title']??'')?></strong><span><?=($item['slug']??'')==='aa-test'?'/p/test-na-alkogolizm':'#'.h($item['slug']??'')?><?=empty($item['published'])?' · скрыта':''?></span><a href="?section=newcomers&edit=<?=h($item['id']??'')?>">Изменить</a><?php if(!in_array(($item['slug']??''),['menu','aa-test'],true)): ?><form method="post" onsubmit="return confirm('Удалить страницу?')"><input type="hidden" name="action" value="delete_newcomer"><input type="hidden" name="csrf" value="<?=h(cms_csrf())?>"><input type="hidden" name="id" value="<?=h($item['id']??'')?>"><button class="link-danger">Удалить</button></form><?php endif; ?></article><?php endforeach; ?></div>
<?php endif; ?>

<?php if ($section === 'tradition'): $data=$content['tradition']??[]; ?><h1>7-я традиция</h1><form method="post" class="admin-form"><input type="hidden" name="action" value="save_section"><input type="hidden" name="section" value="tradition"><input type="hidden" name="csrf" value="<?=h(cms_csrf())?>"><div class="form-grid"><?php admin_input('treasurer',$data['treasurer']??'','text','Казначей'); admin_input('report_url',$data['report_url']??'','url','Ссылка на отчёт казначея'); admin_document_position('report_position',$data['report_position']??'top','Где показывать кнопку отчёта'); ?></div><?php admin_rich('lead',$data['lead']??'','Основной текст',false,true); ?><fieldset class="repeatable" data-repeat="payments"><legend>Реквизиты и способы перевода</legend><div data-repeat-items><?php foreach(($data['payments']??[]) as $payment): ?><div class="card-editor"><input type="hidden" name="payment_id[]" value="<?=h($payment['id']??'')?>"><div class="form-grid"><input name="payment_title[]" value="<?=h($payment['title']??'')?>" placeholder="Название"><input name="payment_icon[]" value="<?=h($payment['icon']??'')?>" placeholder="Буква или эмодзи"><input name="payment_value[]" value="<?=h($payment['value']??'')?>" placeholder="Реквизиты"><input name="payment_url[]" value="<?=h($payment['url']??'')?>" placeholder="Ссылка или tel:"></div><div class="field"><span>Комментарий</span><textarea name="payment_comment[]" class="js-rich" data-label="Комментарий к способу перевода" rows="2"><?=h(admin_rich_legacy_text((string)($payment['comment']??'')))?></textarea></div><button type="button" class="remove-row">Удалить способ</button></div><?php endforeach; ?></div><button type="button" class="button button--quiet" data-add-row>Добавить способ перевода</button><template><div class="card-editor"><input type="hidden" name="payment_id[]"><div class="form-grid"><input name="payment_title[]" placeholder="Название"><input name="payment_icon[]" placeholder="Буква или эмодзи"><input name="payment_value[]" placeholder="Реквизиты"><input name="payment_url[]" placeholder="Ссылка или tel:"></div><div class="field"><span>Комментарий</span><textarea name="payment_comment[]" class="js-rich" data-label="Комментарий к способу перевода" rows="2"></textarea></div><button type="button" class="remove-row">Удалить способ</button></div></template></fieldset><button class="button">Опубликовать 7-ю традицию</button></form><?php endif; ?>

<?php if (!$section && $tab === 'home'): $data=$content['pages']??[]; $custom=$data['custom']??[]; $editId=(string)($_GET['edit']??''); $edit=[]; foreach($custom as $item)if(($item['id']??'')===$editId){$edit=$item; $edit['page_actions']=cms_page_actions($content,'custom:'.($item['slug']??''));} ?><div class="section-heading"><h1>Страницы сайта</h1><a class="button button--quiet" href="?edit=new">Создать страницу</a></div><?php admin_page_links($content); ?><form method="post" enctype="multipart/form-data" class="admin-form"><input type="hidden" name="action" value="save_section"><input type="hidden" name="section" value="pages"><input type="hidden" name="page_kind" value="basics"><input type="hidden" name="csrf" value="<?=h(cms_csrf())?>"><h2>Главная страница</h2><?php admin_rich('home_intro',$data['home_intro']??'','Текст на главной странице',false,true); admin_home_navigation_fields($content['navigation']??cms_default_navigation()); ?><h3>Дополнительные кнопки на главной</h3><p class="admin-tip">Эти кнопки показываются отдельно от главного меню — под текстом или под другими блоками, в зависимости от выбранного места.</p><?php admin_page_actions_fieldset(cms_page_actions($content,'home'),'home_action'); ?><button class="button">Опубликовать главную страницу</button></form><?php if($editId): ?><form method="post" enctype="multipart/form-data" class="admin-form"><input type="hidden" name="action" value="save_section"><input type="hidden" name="section" value="pages"><input type="hidden" name="page_kind" value="custom"><input type="hidden" name="csrf" value="<?=h(cms_csrf())?>"><input type="hidden" name="id" value="<?=h($edit['id']??'')?>"><h2><?= $edit?'Редактирование':'Новая страница' ?></h2><div class="form-grid"><?php admin_input('title',$edit['title']??'','text','Заголовок',true); admin_input('slug',$edit['slug']??'','text','Адрес латиницей',true); ?></div><?php admin_input('description',$edit['description']??'','text','Краткое описание для поисковиков'); ?><label class="check"><input type="checkbox" name="published" <?=(!$edit||!empty($edit['published']))?'checked':''?>> Показывать на сайте</label><fieldset class="repeatable text-blocks-editor" data-repeat="text-blocks"><legend>Текстовые блоки</legend><p class="admin-tip">Добавляйте сколько угодно самостоятельных блоков. Каждый блок получает общий шрифт и может форматироваться.</p><div data-repeat-items><?php foreach(($edit['blocks']??[]) as $block): ?><?php admin_rich('block_body[]',$block['body']??'','Текстовый блок',false,true); ?><?php endforeach; ?><?php if(empty($edit['blocks'])): ?><?php admin_rich('block_body[]',$edit['body']??'','Текстовый блок',false,true); ?><?php endif; ?></div><button type="button" class="button button--quiet" data-add-row>Добавить текст</button><template><div class="field field--wide block-template"><span>Текстовый блок</span><textarea name="block_body[]" class="js-rich" rows="7" data-inline-images="true"></textarea></div></template></fieldset><?php admin_page_actions_fieldset($edit['page_actions']??[], 'page_action'); ?><div class="form-actions"><button class="button">Опубликовать</button><a class="button button--quiet" href="/admin/">Отмена</a></div></form><?php endif; ?><h2>Созданные страницы</h2><div class="item-list"><?php foreach($custom as $item): ?><article><strong><?=h($item['title']??'')?></strong><span>/p/<?=h($item['slug']??'')?><?=empty($item['published'])?' · скрыта':''?></span><a href="?edit=<?=h($item['id']??'')?>">Изменить</a><form method="post" onsubmit="return confirm('Удалить страницу?')"><input type="hidden" name="action" value="delete_page"><input type="hidden" name="csrf" value="<?=h(cms_csrf())?>"><input type="hidden" name="id" value="<?=h($item['id']??'')?>"><button class="link-danger">Удалить</button></form></div></article><?php endforeach; ?></div><?php if (cms_is_admin()): $backups=cms_backup_files('content'); ?><section class="admin-form backups-panel"><h2>Резервные копии содержимого</h2><p>Восстановление возвращает все разделы сайта в состояние на выбранный момент. Текущее состояние сначала тоже сохранится.</p><?php if(!$backups): ?><p>Копий пока нет: они появятся после первого сохранения.</p><?php else: ?><div class="backup-list"><?php foreach($backups as $backup): ?><form method="post"><input type="hidden" name="action" value="restore_content"><input type="hidden" name="csrf" value="<?=h(cms_csrf())?>"><input type="hidden" name="backup" value="<?=h(basename($backup))?>"><span><?=h(date('d.m.Y H:i',filemtime($backup)))?></span><button class="button button--quiet" onclick="return confirm('Восстановить все разделы из этой копии?')">Восстановить</button></form><?php endforeach; ?></div><?php endif; ?></section><?php endif; ?><?php endif; ?>

<?php if ($section === 'navigation'): $data=$content['navigation']??[]; ?><h1>Кнопки и меню</h1><p class="admin-tip">Здесь меняются кнопки главной страницы, боковое меню и мобильное меню. Поле «Вложить в раздел» создаёт раскрывающийся подраздел.</p><form method="post" enctype="multipart/form-data" class="admin-form"><input type="hidden" name="action" value="save_section"><input type="hidden" name="section" value="navigation"><input type="hidden" name="csrf" value="<?=h(cms_csrf())?>"><fieldset class="repeatable" data-repeat="navigation"><legend>Кнопки сайта</legend><div data-repeat-items><?php foreach(($data['items']??[]) as $i=>$item): ?><div class="card-editor" data-nav-item><input type="hidden" name="nav_id[]" value="<?=h($item['id']??'')?>"><div class="form-grid"><input name="nav_title[]" value="<?=h($item['title']??'')?>" placeholder="Надпись"><input name="nav_url[]" value="<?=h($item['url']??'')?>" placeholder="Ссылка"><input name="nav_parent[]" value="<?=h($item['parent']??'')?>" placeholder="Вложить в раздел (название)"><input name="nav_icon_url[]" value="<?=h($item['icon']??'')?>" placeholder="Картинка кнопки"></div><input type="file" name="nav_icon_file[]" accept="image/jpeg,image/png,image/webp"><label class="check"><input type="checkbox" name="nav_external[<?=$i?>]" <?=!empty($item['external'])?'checked':''?>> Открывать в новой вкладке</label><button type="button" class="remove-row">Удалить кнопку</button></div><?php endforeach; ?></div><button type="button" class="button button--quiet" data-add-row>Добавить кнопку</button><template><div class="card-editor" data-nav-item><input type="hidden" name="nav_id[]"><div class="form-grid"><input name="nav_title[]" placeholder="Надпись"><input name="nav_url[]" placeholder="Ссылка"><input name="nav_parent[]" placeholder="Вложить в раздел (название)"><input name="nav_icon_url[]" placeholder="Картинка кнопки"></div><input type="file" name="nav_icon_file[]" accept="image/jpeg,image/png,image/webp"><label class="check"><input type="checkbox" data-nav-external> Открывать в новой вкладке</label><button type="button" class="remove-row">Удалить кнопку</button></div></template></fieldset><fieldset class="repeatable" data-repeat="socials"><legend>Zoom, Telegram, MAX и другие быстрые ссылки</legend><div data-repeat-items><?php foreach(($data['socials']??[]) as $item): ?><div class="card-editor"><div class="form-grid"><input name="social_title[]" value="<?=h($item['title']??'')?>" placeholder="Название"><input name="social_url[]" value="<?=h($item['url']??'')?>" placeholder="Ссылка"><input name="social_icon_url[]" value="<?=h($item['icon']??'')?>" placeholder="Картинка"></div><input type="file" name="social_icon_file[]" accept="image/jpeg,image/png,image/webp"><button type="button" class="remove-row">Удалить</button></div><?php endforeach; ?></div><button type="button" class="button button--quiet" data-add-row>Добавить быструю ссылку</button><template><div class="card-editor"><div class="form-grid"><input name="social_title[]" placeholder="Название"><input name="social_url[]" placeholder="Ссылка"><input name="social_icon_url[]" placeholder="Картинка"></div><input type="file" name="social_icon_file[]" accept="image/jpeg,image/png,image/webp"><button type="button" class="remove-row">Удалить</button></div></template></fieldset><button class="button">Опубликовать меню</button></form><?php endif; ?>
<?php if ($section === 'site_texts'): ?>
  <h1>Заголовки и подписи</h1>
  <p class="admin-tip">Здесь меняются короткие надписи сайта: заголовки, вводные тексты, описания для поиска и подписи постоянных кнопок. Основной текст страниц и обычные кнопки редактируются в соответствующих разделах админки.</p>
  <form method="post" class="admin-form">
    <input type="hidden" name="action" value="save_section">
    <input type="hidden" name="section" value="site_texts">
    <input type="hidden" name="csrf" value="<?=h(cms_csrf())?>">
    <?php $currentGroup = null; foreach (cms_site_text_fields() as $key => $definition):
      if ($currentGroup !== $definition[0]):
        if ($currentGroup !== null) echo '</fieldset>';
        $currentGroup = $definition[0]; ?>
        <fieldset><legend><?=h($currentGroup)?></legend>
      <?php endif; $value = cms_site_text($content, $key); ?>
      <label class="field"><span><?=h($definition[1])?></span>
        <?php if (str_contains($key, 'description') || str_ends_with($key, '_lead')): ?>
          <textarea name="site_text[<?=h($key)?>]" rows="3"><?=h($value)?></textarea>
        <?php else: ?>
          <input name="site_text[<?=h($key)?>]" value="<?=h($value)?>">
        <?php endif; ?>
      </label>
    <?php endforeach; if ($currentGroup !== null) echo '</fieldset>'; ?>
    <button class="button" type="submit">Сохранить заголовки и подписи</button>
  </form>
<?php endif; ?>
</main></div><script src="admin.js?v=25"></script></body></html>
