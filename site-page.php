<?php
declare(strict_types=1);
require __DIR__ . '/cms.php';

$content = cms_content();
$path = trim((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');

if ($path === 'cms-navigation.json' || isset($_GET['navigation'])) {
    $nav = $content['navigation'] ?? cms_default_navigation();
    $hiddenMenuTitles = ['Другая литература', 'График служений', 'Отчет казначея', 'Отчёт казначея'];
    $items = array_values(array_filter(
        $nav['items'] ?? [],
        static fn(array $item): bool => !in_array((string)($item['title'] ?? ''), $hiddenMenuTitles, true)
    ));
    $knownUrls = array_column($items, 'url');
    foreach (($content['newcomers']['pages'] ?? []) as $page) {
        if (empty($page['published']) || ($page['slug'] ?? '') === 'menu') continue;
        $url = 'newcomers.html#' . ($page['slug'] ?? '');
        if (!in_array($url, $knownUrls, true)) $items[] = ['id'=>'newcomer-' . ($page['slug'] ?? ''),'title'=>$page['title'] ?? 'Страница','url'=>$url,'parent'=>'Новичкам','icon'=>'','external'=>false];
    }
    foreach (($content['pages']['custom'] ?? []) as $page) {
        if (empty($page['published'])) continue;
        $url = '/p/' . ($page['slug'] ?? '');
        if (!in_array($url, $knownUrls, true)) $items[] = ['id'=>'page-' . ($page['slug'] ?? ''),'title'=>$page['title'] ?? 'Страница','url'=>$url,'parent'=>'','icon'=>'','external'=>false];
    }
    header('Content-Type: application/json; charset=UTF-8'); header('Cache-Control: no-store');
    echo json_encode(['items'=>$items,'socials'=>$nav['socials']??[]], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit;
}

$kind = (string)($_GET['kind'] ?? '');
$slug = (string)($_GET['slug'] ?? '');
if ($kind === '') {
    $kind = match ($path) { '', 'index.html' => 'home', 'newcomers.html' => 'newcomers', 'tradition.html' => 'tradition', default => $slug !== '' ? 'custom' : '' };
}

function site_head(string $title, string $description, string $canonical, string $shell = 'page-shell'): void { ?>
<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=h($title)?></title><meta name="description" content="<?=h($description)?>"><link rel="canonical" href="https://pochtinormalnye.ru/<?=h($canonical)?>"><meta name="theme-color" content="#f7efdf"><link rel="manifest" href="/manifest.webmanifest"><link rel="icon" href="/favicon.ico" sizes="any"><link rel="icon" type="image/png" sizes="32x32" href="/icons/favicon-32.png"><link rel="apple-touch-icon" href="/icons/apple-touch-icon-v3.png"><link rel="stylesheet" href="/styles.css?v=103"></head><body><main class="<?=h($shell)?>" id="top">
<?php }
function site_foot(bool $siteNav = true): void { ?>
<a class="back-to-top" href="#top">↑ Наверх</a></main><?php if($siteNav):?><script src="/site-nav.js?v=17"></script><?php endif;?><script src="/pwa-install.js?v=10"></script><script src="/site-chat.js?v=6" data-api="/support-chat-api"></script><script src="/aa-test.js?v=2"></script></body></html>
<?php }

if ($kind === 'home') {
    $pages=$content['pages']??[]; $nav=$content['navigation']??cms_default_navigation();
    site_head('АА Почти нормальные - онлайн-группа Анонимных Алкоголиков','Международная русскоязычная онлайн-группа Анонимных Алкоголиков «Почти нормальные».','', 'app-shell'); ?>
    <header class="hero" aria-label="Группа АА «Почти нормальные»"><span class="hero__stroke hero__stroke--left" aria-hidden="true"></span><picture class="hero__logo"><source media="(max-width:560px)" srcset="/assets/pn-text-mobile-tight.png?v=1"><img class="hero__mark" src="/assets/pn-text-tight.png?v=1" alt="Группа АА «Почти нормальные»"></picture><span class="hero__stroke hero__stroke--right" aria-hidden="true"></span></header>
    <section class="intro"><?=cms_rich((string)($pages['home_intro']??''))?></section>
    <?php $socials=$nav['socials']??[]; $zoom=$socials[0]??[]; ?><nav class="quick-actions" aria-label="Быстрый вход"><?php if($url=cms_safe_url($zoom['url']??'')):?><a class="quick-action quick-action--primary" href="<?=h($url)?>" target="_blank" rel="noopener"><img src="<?=h(cms_safe_url($zoom['icon']??''))?>" alt=""><span><strong>Войти в <?=cms_display_text($zoom['title']??'Zoom')?></strong><small>Собрание группы</small></span></a><?php endif;?><a class="quick-action" href="/schedule.html"><img src="/assets/emoji/aa_calendar.png" alt=""><span><strong>Расписание собраний</strong><small>Дни и время</small></span></a></nav>
    <nav class="menu" aria-label="Разделы сайта">
      <?php foreach(($nav['items']??[]) as $item):
        $itemUrl = (string)($item['url'] ?? '');
        if (($item['parent']??'') !== '' || $itemUrl === 'index.html' || $itemUrl === 'schedule.html') continue;
        $url = cms_safe_url($itemUrl); if (!$url) continue;
        if ($itemUrl === 'service.html'): ?><span class="menu-divider" aria-hidden="true"></span><?php endif; ?>
        <a class="menu-button" href="<?=h($url)?>"<?=!empty($item['external'])?' target="_blank" rel="noopener"':''?>><?php if($icon=cms_safe_url($item['icon']??'')):?><img src="<?=h($icon)?>" alt=""><?php endif;?><span><strong><?=cms_display_text($item['title']??'')?></strong></span></a>
      <?php endforeach; ?>
      <a class="menu-admin-link" href="/admin/">↗ Вход в админку</a>
    </nav>
    <nav class="socials" aria-label="Быстрые ссылки"><?php foreach($socials as $item):if(!($url=cms_safe_url($item['url']??'')))continue;?><a class="social-link" href="<?=h($url)?>" target="_blank" rel="noopener"><?php if($icon=cms_safe_url($item['icon']??'')):?><img src="<?=h($icon)?>" alt=""><?php endif;?><small><?=cms_display_text($item['title']??'')?></small></a><?php endforeach;?></nav>
    <?=cms_actions_html(cms_page_actions($content,'home'))?>
<?php site_foot(false); exit; }

if ($kind === 'newcomers') { $pages=array_values(array_filter($content['newcomers']['pages']??[],static fn($p)=>!empty($p['published']))); usort($pages,static fn($a,$b)=>(int)($a['order']??0)<=>(int)($b['order']??0)); site_head('Новичкам - Почти нормальные','Материалы для тех, кто впервые знакомится с Анонимными Алкоголиками.','newcomers.html'); ?><header class="page-header"><h1 class="page-title">Новичкам</h1></header><section class="newcomers-flow" aria-live="polite"><?php foreach($pages as $i=>$page):?><article class="newcomers-card" data-page="<?=h($page['slug']??'')?>"<?=$i?' hidden':''?>><?php if(($page['slug']??'')!=='menu'):?><h2><?=cms_display_text($page['title']??'')?></h2><?php endif;?><?=cms_actions_html($page['actions']??[],'newcomers-actions','under-title')?><?=cms_rich((string)($page['body']??''))?><?=cms_actions_html($page['actions']??[],'newcomers-actions','bottom')?></article><?php endforeach;?></section><script>(()=>{const p=[...document.querySelectorAll('[data-page]')],h=[];let c='menu';const show=(n,keep=true,hash=true)=>{const x=p.find(e=>e.dataset.page===n);if(!x||n===c)return;if(keep)h.push(c);p.forEach(e=>e.hidden=e!==x);c=n;if(hash)history.replaceState(null,'',location.pathname+(n==='menu'?'':'#'+n));scrollTo({top:0,behavior:'smooth'})};window.handleNewcomersBack=()=>{if(c==='menu')return false;show(h.pop()||'menu',false);return true};const fromHash=()=>{const n=decodeURIComponent(location.hash.slice(1));if(n&&p.some(e=>e.dataset.page===n))show(n,false,false)};fromHash();addEventListener('hashchange',fromHash);document.addEventListener('click',e=>{const b=e.target.closest('[data-target]');if(b)show(b.dataset.target)})})();</script><?php site_foot(); exit; }

if ($kind === 'tradition') { $data=$content['tradition']??[]; site_head('7-я традиция - Почти нормальные','Реквизиты седьмой традиции группы «Почти нормальные».','tradition.html'); $reportUrl=cms_safe_url($data['report_url']??''); $reportTop=($data['report_position']??'top')!=='bottom'; ?><header class="page-header"><h1 class="page-title">7-я традиция</h1></header><?php if($reportUrl && $reportTop):?><a class="document-link" href="<?=h($reportUrl)?>" target="_blank" rel="noopener"><img src="/assets/emoji/aa_ballot.png" alt=""><span>Отчёт казначея</span></a><?php endif;?><section class="tradition-page" aria-label="Реквизиты седьмой традиции"><div class="tradition-lead"><?=cms_rich((string)($data['lead']??''))?></div><?php if(!empty($data['treasurer'])):?><p class="tradition-treasurer"><strong>Казначей: <?=cms_display_text($data['treasurer'])?></strong></p><?php endif;?><?php foreach(($data['payments']??[]) as $payment):?><article class="payment-card"><h2><span class="payment-icon"><?=cms_icon_html((string)($payment['icon']??'•'))?></span><?=cms_display_text($payment['title']??'')?></h2><?php if($url=cms_safe_url($payment['url']??'')):?><a class="payment-value" href="<?=h($url)?>"<?=str_starts_with($url,'http')?' target="_blank" rel="noopener"':''?>><?=cms_display_text($payment['value']??$url)?></a><?php else:?><p class="payment-value"><?=cms_display_text($payment['value']??'')?></p><?php endif;?><?php if(!empty($payment['comment'])):?><p class="payment-comment"><?=cms_display_text($payment['comment'])?></p><?php endif;?></article><?php endforeach;?></section><?php if($reportUrl && !$reportTop):?><a class="document-link" href="<?=h($reportUrl)?>" target="_blank" rel="noopener"><img src="/assets/emoji/aa_ballot.png" alt=""><span>Отчёт казначея</span></a><?php endif;?><?=cms_actions_html(cms_page_actions($content,'tradition'))?><?php site_foot(); exit; }

if ($kind === 'custom') { $page=null; foreach(($content['pages']['custom']??[]) as $candidate)if(($candidate['slug']??'')===$slug&&!empty($candidate['published']))$page=$candidate;if(!$page){http_response_code(404);exit('Страница не найдена.');}site_head(($page['title']??'Страница').' - Почти нормальные',$page['description']??'','p/'.rawurlencode($slug));?><header class="page-header"><h1 class="page-title"><?=cms_display_text($page['title']??'')?></h1></header><?=cms_actions_html(cms_page_actions($content,'custom:' . $slug),'page-actions page-actions--under-title','under-title')?><section class="content-card custom-page-body"><?php $blocks=is_array($page['blocks']??null)?$page['blocks']:[]; if(!$blocks && !empty($page['body']))$blocks=[['body'=>$page['body']]]; foreach($blocks as $block): ?><div class="custom-text-block"><?=cms_rich((string)($block['body']??''))?></div><?php endforeach; ?></section><?=cms_actions_html(cms_page_actions($content,'custom:' . $slug),'page-actions','bottom')?><?php site_foot(); exit; }

http_response_code(404); echo 'Страница не найдена.';
