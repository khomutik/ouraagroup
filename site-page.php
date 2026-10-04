<?php
declare(strict_types=1);
require __DIR__ . '/cms.php';

$content = cms_content();
$path = trim((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');

if ($path === 'cms-navigation.json' || isset($_GET['navigation'])) {
    header('Content-Type: application/json; charset=UTF-8'); header('Cache-Control: no-store');
    echo json_encode(cms_navigation_payload($content), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit;
}

$kind = (string)($_GET['kind'] ?? '');
$slug = (string)($_GET['slug'] ?? '');
if ($kind === '') {
    $kind = match ($path) { '', 'index.html' => 'home', 'newcomers.html' => 'newcomers', 'tradition.html' => 'tradition', default => $slug !== '' ? 'custom' : '' };
}

function site_head(string $title, string $description, string $canonical, string $shell = 'page-shell'): void { ?>
<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=h($title)?></title><meta name="description" content="<?=h($description)?>"><?php if ($canonical === ''): ?><meta name="google-site-verification" content="5IWS7A0Fugk8U_UVSELaH5BBzBC2tqNW9qVOiSQtt2E"><meta name="msvalidate.01" content="0CB2AF7FFE0AAB04D4E791C26CB5FA3D"><?php endif; ?><link rel="canonical" href="https://pochtinormalnye.ru/<?=h($canonical)?>"><meta name="theme-color" content="#f7efdf"><link rel="manifest" href="/manifest.webmanifest"><link rel="icon" href="/favicon.ico" sizes="any"><link rel="icon" type="image/png" sizes="32x32" href="/icons/favicon-32.png"><link rel="apple-touch-icon" href="/icons/apple-touch-icon-v3.png"><link rel="stylesheet" href="/styles.css?v=112"><script defer src="/site-analytics.js?v=1"></script></head><body><main class="<?=h($shell)?>" id="top">
<?php }
function site_foot(bool $siteNav = true): void { global $content; ?>
<a class="back-to-top" href="#top"><?=h(cms_site_text($content, 'back_to_top'))?></a></main><?php if($siteNav):?><script type="application/json" id="site-navigation-data"><?=json_encode(cms_navigation_payload($content), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR)?></script><script src="/site-nav.js?v=21"></script><?php endif;?><script src="/pwa-install.js?v=11"></script><script src="/site-chat.js?v=7" data-api="/support-chat-api"></script><script src="/site-search.js?v=2"></script><script src="/aa-test.js?v=3"></script></body></html>
<?php }

if ($kind === 'home') {
    $pages=$content['pages']??[]; $nav=$content['navigation']??cms_default_navigation();
    $homeHeading = cms_site_text($content, 'home_heading');
    $homeTitle = preg_replace('/\s+/u', ' ', trim($homeHeading)) ?? $homeHeading;
    site_head($homeTitle, cms_site_text($content, 'home_description'), '', 'app-shell'); ?>
    <header class="hero" aria-label="Группа АА «Почти нормальные»"><span class="hero__stroke hero__stroke--left" aria-hidden="true"></span><picture class="hero__logo"><source media="(max-width:560px)" srcset="/assets/pn-text-mobile-tight.png?v=1"><img class="hero__mark" src="/assets/pn-text-tight.png?v=1" alt="Группа АА «Почти нормальные»"></picture><span class="hero__stroke hero__stroke--right" aria-hidden="true"></span></header>
    <section class="intro"><h1 class="intro__heading"><?=nl2br(h($homeHeading), false)?></h1><?=cms_rich((string)($pages['home_intro']??''))?></section>
    <?php $socials=$nav['socials']??[]; $zoom=$socials[0]??[]; ?><nav class="quick-actions" aria-label="Быстрый вход"><?php if($url=cms_safe_url($zoom['url']??'')):?><a class="quick-action quick-action--primary" href="<?=h($url)?>" target="_blank" rel="noopener"><img src="<?=h(cms_safe_url($zoom['icon']??''))?>" alt=""><span><strong><?=h(rtrim(cms_site_text($content, 'zoom_prefix')))?> <?=cms_display_text($zoom['title']??'Zoom')?></strong><small><?=h(cms_site_text($content, 'zoom_subtitle'))?></small></span></a><?php endif;?><a class="quick-action" href="/schedule.html"><img src="/assets/emoji/aa_calendar.png" alt=""><span><strong><?=h(cms_site_text($content, 'schedule_button_title'))?></strong><small><?=h(cms_site_text($content, 'schedule_button_subtitle'))?></small></span></a></nav>
    <nav class="menu" aria-label="Разделы сайта">
      <?php foreach(($nav['items']??[]) as $item):
        $itemUrl = (string)($item['url'] ?? '');
        if (($item['parent']??'') !== '' || $itemUrl === 'index.html' || $itemUrl === 'schedule.html') continue;
        $url = cms_safe_url($itemUrl); if (!$url) continue;
        if ($itemUrl === 'service.html'): ?><span class="menu-divider" aria-hidden="true"></span><?php endif; ?>
        <a class="menu-button" href="<?=h($url)?>"<?=!empty($item['external'])?' target="_blank" rel="noopener"':''?>><?php if($icon=cms_safe_url($item['icon']??'')):?><img src="<?=h($icon)?>" alt=""><?php endif;?><span><strong><?=cms_display_text($item['title']??'')?></strong></span></a>
      <?php endforeach; ?>
      <a class="menu-admin-link" href="/admin/"><?=h(cms_site_text($content, 'admin_link'))?></a>
    </nav>
    <nav class="socials" aria-label="Быстрые ссылки"><?php foreach($socials as $item):if(!($url=cms_safe_url($item['url']??'')))continue;?><a class="social-link" href="<?=h($url)?>" target="_blank" rel="noopener"><?php if($icon=cms_safe_url($item['icon']??'')):?><img src="<?=h($icon)?>" alt=""><?php endif;?><small><?=cms_display_text($item['title']??'')?></small></a><?php endforeach;?></nav>
    <?=cms_actions_html(cms_page_actions($content,'home'))?>
<?php site_foot(false); exit; }

if ($kind === 'newcomers') { $pages=array_values(array_filter($content['newcomers']['pages']??[],static fn($p)=>!empty($p['published']) && ($p['slug'] ?? '') !== 'aa-test')); usort($pages,static fn($a,$b)=>(int)($a['order']??0)<=>(int)($b['order']??0)); site_head(cms_site_text($content, 'newcomers_heading') . ': как прийти на собрание АА - Почти нормальные', cms_site_text($content, 'newcomers_description'),'newcomers.html'); ?><header class="page-header"><h1 class="page-title"><?=h(cms_site_text($content, 'newcomers_heading'))?></h1></header><section class="newcomers-flow" aria-live="polite"><?php foreach($pages as $i=>$page):?><article class="newcomers-card" data-page="<?=h($page['slug']??'')?>"<?=$i?' hidden':''?>><?php if(($page['slug']??'')!=='menu'):?><h2><?=cms_display_text($page['title']??'')?></h2><?php endif;?><?=cms_actions_html($page['actions']??[],'newcomers-actions','under-title')?><?=cms_rich((string)($page['body']??''))?><?=cms_actions_html($page['actions']??[],'newcomers-actions','bottom')?></article><?php endforeach;?></section><script>(()=>{if(location.hash==='#aa-test'){location.replace('/p/test-na-alkogolizm');return}const p=[...document.querySelectorAll('[data-page]')],h=[];let c='menu';const show=(n,keep=true,hash=true)=>{const x=p.find(e=>e.dataset.page===n);if(!x||n===c)return;if(keep)h.push(c);p.forEach(e=>e.hidden=e!==x);c=n;if(hash)history.replaceState(null,'',location.pathname+(n==='menu'?'':'#'+n));scrollTo({top:0,behavior:'smooth'})};window.handleNewcomersBack=()=>{if(c==='menu')return false;show(h.pop()||'menu',false);return true};const fromHash=()=>{const n=decodeURIComponent(location.hash.slice(1));if(n&&p.some(e=>e.dataset.page===n))show(n,false,false)};fromHash();addEventListener('hashchange',fromHash);document.addEventListener('click',e=>{const b=e.target.closest('[data-target]');if(b)show(b.dataset.target)})})();</script><?php site_foot(); exit; }

if ($kind === 'custom' && $slug === 'test-na-alkogolizm') {
    $testPage = null;
    foreach (($content['newcomers']['pages'] ?? []) as $candidate) {
        if (($candidate['slug'] ?? '') === 'aa-test' && !empty($candidate['published'])) $testPage = $candidate;
    }
    if ($testPage === null) { http_response_code(404); exit('Страница не найдена.'); }
    $defaults = cms_default_aa_test();
    $questions = $testPage['test_questions'] ?? $defaults['test_questions'];
    if (!is_array($questions) || count($questions) !== 12) $questions = $defaults['test_questions'];
    $testConfig = ['questions'=>$questions];
    foreach (['test_attention_heading','test_attention_text','test_other_heading','test_other_text'] as $key) {
        $testConfig[$key] = $testPage[$key] ?? $defaults[$key];
    }
    site_head((string)($testPage['seo_title'] ?? $defaults['seo_title']), (string)($testPage['seo_description'] ?? $defaults['seo_description']), 'p/test-na-alkogolizm'); ?>
    <header class="page-header"><h1 class="page-title"><?=h((string)($testPage['title'] ?? 'Тест «Подходит ли тебе АА?»'))?></h1></header>
    <section class="newcomers-flow"><article class="newcomers-card newcomers-card--test" data-page="aa-test"><div class="aa-test__intro" data-aa-test-intro><?=cms_rich((string)($testPage['body'] ?? $defaults['body']))?></div></article></section>
    <script type="application/json" id="aa-test-config"><?=json_encode($testConfig, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR)?></script>
    <?php site_foot(); exit;
}
if ($kind === 'tradition') { $data=$content['tradition']??[]; site_head(cms_site_text($content, 'tradition_heading') . ' - Почти нормальные',cms_site_text($content, 'tradition_description'),'tradition.html'); $reportUrl=cms_safe_url($data['report_url']??''); $reportTop=($data['report_position']??'top')!=='bottom'; ?><header class="page-header"><h1 class="page-title"><?=h(cms_site_text($content, 'tradition_heading'))?></h1></header><?php if($reportUrl && $reportTop):?><a class="document-link" href="<?=h($reportUrl)?>" target="_blank" rel="noopener"><img src="/assets/emoji/aa_ballot.png" alt=""><span><?=h(cms_site_text($content, 'tradition_report_button'))?></span></a><?php endif;?><section class="tradition-page" aria-label="Реквизиты седьмой традиции"><div class="tradition-lead"><?=cms_rich((string)($data['lead']??''))?></div><?php if(!empty($data['treasurer'])):?><p class="tradition-treasurer"><strong>Казначей: <?=cms_display_text($data['treasurer'])?></strong></p><?php endif;?><?php foreach(($data['payments']??[]) as $payment):?><article class="payment-card"><h2><span class="payment-icon"><?=cms_icon_html((string)($payment['icon']??'•'))?></span><?=cms_display_text($payment['title']??'')?></h2><?php if($url=cms_safe_url($payment['url']??'')):?><a class="payment-value" href="<?=h($url)?>"<?=str_starts_with($url,'http')?' target="_blank" rel="noopener"':''?>><?=cms_display_text($payment['value']??$url)?></a><?php else:?><p class="payment-value"><?=cms_display_text($payment['value']??'')?></p><?php endif;?><?php if(!empty($payment['comment'])):?><div class="payment-comment"><?=cms_rich((string)$payment['comment'])?></div><?php endif;?></article><?php endforeach;?></section><?php if($reportUrl && !$reportTop):?><a class="document-link" href="<?=h($reportUrl)?>" target="_blank" rel="noopener"><img src="/assets/emoji/aa_ballot.png" alt=""><span><?=h(cms_site_text($content, 'tradition_report_button'))?></span></a><?php endif;?><?=cms_actions_html(cms_page_actions($content,'tradition'))?><?php site_foot(); exit; }

if ($kind === 'custom') { $page=null; foreach(($content['pages']['custom']??[]) as $candidate)if(($candidate['slug']??'')===$slug&&!empty($candidate['published']))$page=$candidate;if(!$page){http_response_code(404);exit('Страница не найдена.');}site_head(($page['title']??'Страница').' - Почти нормальные',$page['description']??'','p/'.rawurlencode($slug));?><header class="page-header"><h1 class="page-title"><?=cms_display_text($page['title']??'')?></h1></header><?=cms_actions_html(cms_page_actions($content,'custom:' . $slug),'page-actions page-actions--under-title','under-title')?><section class="content-card custom-page-body"><?php $blocks=is_array($page['blocks']??null)?$page['blocks']:[]; if(!$blocks && !empty($page['body']))$blocks=[['body'=>$page['body']]]; foreach($blocks as $block): ?><div class="custom-text-block"><?=cms_rich((string)($block['body']??''))?></div><?php endforeach; ?></section><?=cms_actions_html(cms_page_actions($content,'custom:' . $slug),'page-actions','bottom')?><?php site_foot(); exit; }

http_response_code(404); echo 'Страница не найдена.';
