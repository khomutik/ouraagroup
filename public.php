<?php
declare(strict_types=1);
require __DIR__ . '/cms.php';
require __DIR__ . '/site-search-lib.php';

$section = (string) ($_GET['section'] ?? '');
if (!in_array($section, CMS_SECTIONS, true)) { http_response_code(404); exit('Страница не найдена.'); }

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-cache, max-age=0, must-revalidate');
header('Pragma: no-cache');

$content = cms_content();
$textKey = ['announcements'=>'announcements','schedule'=>'schedule','library'=>'library','speakers'=>'speakers','services'=>'services','archive'=>'archive'];
function page_head(string $title, string $description, string $canonical, string $seoTitle): void { ?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= h($seoTitle) ?></title>
  <meta name="description" content="<?= h($description) ?>" />
  <link rel="canonical" href="https://pochtinormalnye.ru/<?= h($canonical) ?>.html" />
  <meta property="og:type" content="website" />
  <meta property="og:site_name" content="Почти нормальные" />
  <meta property="og:title" content="<?= h($seoTitle) ?>" />
  <meta property="og:description" content="<?= h($description) ?>" />
  <meta property="og:url" content="https://pochtinormalnye.ru/<?= h($canonical) ?>.html" />
  <meta name="theme-color" content="#f7efdf" />
  <link rel="manifest" href="manifest.webmanifest" />
  <link rel="icon" href="favicon.ico" sizes="any" />
  <link rel="icon" type="image/png" sizes="16x16" href="icons/favicon-16.png" />
  <link rel="icon" type="image/png" sizes="32x32" href="icons/favicon-32.png" />
  <link rel="apple-touch-icon" href="icons/apple-touch-icon-v3.png" />
  <link rel="stylesheet" href="styles.css?v=117" />
  <script defer src="/site-analytics.js?v=1"></script>
</head>
<body>
  <main class="page-shell" id="top">
    <header class="page-header"><h1 class="page-title"><?= h($title) ?></h1></header>
<?php }
function page_foot(): void { global $content; ?>
    <a class="back-to-top" href="#top"><?=h(cms_site_text($content, 'back_to_top'))?></a>
  </main>
  <script type="application/json" id="site-navigation-data"><?=json_encode(cms_navigation_payload($content), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR)?></script>
  <script src="site-nav.js?v=22"></script>
  <script src="pwa-install.js?v=11"></script>
  <script src="site-chat.js?v=7" data-api="/support-chat-api"></script>
  <script src="site-search.js?v=5"></script>
</body>
</html>
<?php }

$canonical = ['announcements'=>'announcements','schedule'=>'schedule','library'=>'library','speakers'=>'speakers','services'=>'service','archive'=>'archive'][$section];
page_head(cms_site_text($content, $textKey[$section] . '_heading'), cms_site_text($content, $textKey[$section] . '_description'), $canonical, cms_site_text($content, $textKey[$section] . '_seo_title'));

if ($section === 'announcements') {
    $items = $content['announcements'] ?? [];
    usort($items, static fn($a, $b) => (int)($a['order'] ?? 999999) <=> (int)($b['order'] ?? 999999));
    $today = date('Y-m-d'); $visible = [];
    foreach ($items as $item) if (empty($item['hide_after']) || $item['hide_after'] >= $today) $visible[] = $item;
    if (!$visible) echo '<section class="content-card"><p>' . h(cms_site_text($content, 'no_announcements')) . '</p></section>';
    foreach ($visible as $i => $item) { $eventDate = (string)($item['event_date'] ?? ''); $eventTime = (string)($item['event_time'] ?? ''); ?>
      <section class="announcement-card" id="<?=h(site_search_anchor('announcement', $item['id'] ?? '', (int)$i))?>" data-event-date="<?= h($eventDate) ?>" data-event-time="<?= h($eventTime) ?>">
        <?php if (!empty($item['image'])): ?><img src="<?= h(cms_safe_url($item['image'])) ?>" alt="<?= h($item['image_alt'] ?? '') ?>" /><?php endif; ?>
        <div class="announcement-copy">
          <div class="announcement-meta"><time datetime="<?= h($eventDate) ?>"><?= h(cms_date_ru($eventDate)) ?></time><time datetime="<?= h($eventDate . 'T' . $eventTime . ':00+03:00') ?>"><?= h($eventTime) ?> по МСК</time></div>
          <h2 class="announcement-title"><?= cms_display_text($item['title'] ?? '') ?></h2>
          <div class="announcement-text"><?= cms_rich((string)($item['body'] ?? '')) ?></div>
          <?= cms_actions_html($item['links'] ?? [], 'announcement-links') ?>
        </div>
      </section>
    <?php }
    echo cms_actions_html(cms_page_actions($content,'announcements'));
}

if ($section === 'archive') { $data=$content['archive']??[]; $items=$data['items']??[]; $driveUrl=cms_safe_url($data['drive_url']??''); $driveTop=($data['drive_position']??'bottom')==='top'; usort($items, static fn($a,$b)=>(int)($a['order']??PHP_INT_MAX)<=>(int)($b['order']??PHP_INT_MAX)); ?>
  <?php if ($driveUrl && $driveTop): ?><div class="page-actions"><a class="flow-button flow-button--soft flow-button--align-center" href="<?=h($driveUrl)?>" target="_blank" rel="noopener"><?=h(cms_site_text($content, 'archive_drive_button'))?></a></div><?php endif; ?>
  <?php foreach ($items as $i => $item):
      $archiveId = (string)($item['id'] ?? '');
      $agendaText = archive_plain_text((string)($item['body'] ?? ''), true);
      $protocolText = archive_plain_text((string)($item['protocol_text'] ?? ''));
  ?><section class="announcement-card archive-card" id="<?=h(site_search_anchor('archive', $archiveId, (int)$i))?>">
    <?php if (!empty($item['image'])): ?><img src="<?=h(cms_safe_url($item['image']))?>" alt="<?=h($item['title']??'')?>"><?php endif; ?>
    <div class="announcement-copy">
      <div class="announcement-meta"><time><?=h(cms_date_ru($item['event_date']??''))?></time><?php if (!empty($item['event_time'])): ?><time><?=h($item['event_time'])?> по МСК</time><?php endif; ?></div>
      <h2 class="announcement-title"><?=cms_display_text($item['title']??'')?></h2>
      <div class="announcement-text"><?=archive_document_html($agendaText, 'agenda', $archiveId)?><?=archive_document_html($protocolText, 'protocol', $archiveId)?><?php if (!empty($item['treasurer_report'])): ?><details class="archive-document archive-treasurer" id="<?=h(archive_treasurer_id($archiveId))?>"><summary>Отчёт казначея</summary><div class="archive-treasurer__body"><?=cms_rich((string)$item['treasurer_report'])?></div></details><?php endif; ?></div>
      <?=cms_actions_html(archive_public_links($item), 'announcement-links')?>
    </div>
  </section><?php endforeach; ?>
  <?php if ($driveUrl && !$driveTop): ?><div class="page-actions"><a class="flow-button flow-button--soft flow-button--align-center" href="<?=h($driveUrl)?>" target="_blank" rel="noopener"><?=h(cms_site_text($content, 'archive_drive_button'))?></a></div><?php endif; ?>
  <?=cms_actions_html(cms_page_actions($content,'archive'))?>
  <script>(()=>{const reveal=()=>{const id=decodeURIComponent(location.hash.slice(1));const target=document.getElementById(id);if(!target)return;if(target.tagName==='DETAILS')target.open=true;for(let node=target.parentElement;node;node=node.parentElement)if(node.tagName==='DETAILS')node.open=true;if(id.includes('-point-')||id.startsWith('treasurer-'))requestAnimationFrame(()=>target.scrollIntoView({block:'start'}))};addEventListener('hashchange',reveal);reveal()})();</script>
<?php }

if ($section === 'schedule') { $data = $content['schedule'] ?? []; ?>
  <p class="schedule-time"><?= h($data['time'] ?? '') ?></p>
  <section class="schedule schedule-page" aria-label="Темы собраний"><div class="schedule-list">
    <?php foreach (($data['days'] ?? []) as $i => $day): ?><article class="schedule-item" id="<?=h(site_search_anchor('day', $day['day'] ?? '', (int)$i))?>"><h2><?= h($day['day'] ?? '') ?></h2><div class="schedule-topic"><?= cms_rich((string)($day['topic'] ?? '')) ?></div></article><?php endforeach; ?>
  </div></section>
  <?=cms_actions_html(cms_page_actions($content,'schedule'))?>
<?php }

if ($section === 'library') { $data = $content['library'] ?? []; $other=cms_safe_url($data['other_url']??''); $otherTop=($data['other_position']??'bottom')==='top'; ?>
  <?php if ($other && $otherTop): ?><nav class="menu" aria-label="Разделы библиотеки"><a class="menu-button" href="<?= h($other) ?>" target="_blank" rel="noopener"><img src="assets/emoji/aa_book_blue.png" alt="" /><span><strong><?=h(cms_site_text($content, 'library_other_button'))?></strong></span></a></nav><?php endif; ?>
  <section class="library-list" aria-label="Книги АА">
    <?php if (!empty($data['note'])): ?><div class="library-note"><?= cms_rich((string)$data['note']) ?></div><?php endif; ?>
    <?php foreach (($data['items'] ?? []) as $i => $item): $resource = cms_safe_url($item['resource'] ?? ''); $readButton = is_array($item['read_button'] ?? null) ? $item['read_button'] : []; $readLabel = cms_text((string)($readButton['label'] ?? cms_site_text($content, 'library_read_button')), 160); ?>
      <article class="library-card" id="<?=h(site_search_anchor('book', $item['id'] ?? '', (int)$i))?>">
        <?php if (!empty($item['cover'])): ?><a class="library-card__cover-link" href="<?= h($resource) ?>" target="_blank" rel="noopener"><img class="library-card__cover" src="<?= h(cms_safe_url($item['cover'])) ?>" alt="Обложка: <?= h($item['title'] ?? '') ?>" /></a><?php endif; ?>
        <div class="library-card__body"><h2><?= cms_display_text($item['title'] ?? '') ?></h2><div class="library-card__description"><?= cms_rich((string)($item['description'] ?? '')) ?></div><?php if ($resource && $readLabel !== ''): ?><a class="<?=h($readButton ? cms_action_classes($readButton) : 'flow-button')?>" href="<?= h($resource) ?>" target="_blank" rel="noopener"><?=cms_display_text($readLabel)?></a><?php endif; ?></div>
      </article>
    <?php endforeach; ?>
  </section>
  <?php if ($other && !$otherTop): ?><nav class="menu" aria-label="Разделы библиотеки"><a class="menu-button" href="<?= h($other) ?>" target="_blank" rel="noopener"><img src="assets/emoji/aa_book_blue.png" alt="" /><span><strong><?=h(cms_site_text($content, 'library_other_button'))?></strong></span></a></nav><?php endif; ?>
  <?=cms_actions_html(cms_page_actions($content,'library'))?>
<?php }

if ($section === 'speakers') { $data = $content['speakers'] ?? []; $driveUrl=cms_safe_url($data['drive_url']??''); $speakerItems=cms_speakers_newest_first($data['items']??[]); ?>
  <?php if (!empty($data['intro'])): ?><section class="content-card speakers-intro"><?= cms_rich((string)$data['intro']) ?></section><?php endif; ?>
  <?php if (!empty($data['privacy'])): ?><section class="speakers-privacy"><?= cms_rich((string)$data['privacy']) ?></section><?php endif; ?>
  <section class="content-card speakers-materials" aria-label="Материалы об анонимности"><h2><?=h(cms_site_text($content, 'speakers_materials_heading'))?></h2><?php foreach (($data['materials'] ?? []) as $item): if ($url = cms_safe_url($item['resource'] ?? '')): ?><a class="speakers-file" href="<?= h($url) ?>" target="_blank" rel="noopener"><span>📄</span><span><?= cms_display_text($item['title'] ?? '') ?></span></a><?php endif; endforeach; ?></section>
  <?php if ($driveUrl): ?><a class="document-link speakers-drive-link" href="<?= h($driveUrl) ?>" target="_blank" rel="noopener"><img src="assets/emoji/aa_microphone.png" alt="" /><span><?=h(cms_site_text($content, 'speakers_drive_button'))?></span></a><?php endif; ?>
  <?php foreach($speakerItems as $i => $item): $previewUrl=cms_speaker_preview_url($item); $downloadUrl=cms_speaker_download_url($item); $playerButton=cms_speaker_button($item,'player'); $downloadButton=cms_speaker_button($item,'download'); ?>
    <article class="announcement-card speaker-card" id="<?=h(site_search_anchor('speaker', $item['id'] ?? '', (int)$i))?>">
      <div class="announcement-copy">
        <div class="announcement-meta"><time datetime="<?=h($item['event_date']??'')?>"><?=h(cms_date_ru((string)($item['event_date']??'')))?></time></div>
        <h2 class="announcement-title"><?=cms_display_text($item['title']??'')?></h2>
        <dl class="speaker-card__facts">
          <div><dt>Спикер</dt><dd><?=h($item['speaker']??'')?></dd></div>
          <?php foreach(['city'=>'Город','home_group'=>'Домашняя группа','sobriety'=>'Трезвость'] as $key=>$label): if (!empty($item[$key])): ?><div><dt><?=h($label)?></dt><dd><?=h($item[$key])?></dd></div><?php endif; endforeach; ?>
        </dl>
        <?php if($previewUrl || $downloadUrl): ?><div class="speaker-audio"><?php if($previewUrl): ?><button class="speaker-audio__load <?=h(cms_action_classes($playerButton))?>" type="button" data-speaker-preview="<?=h($previewUrl)?>" data-open-label="<?=h($playerButton['label'])?>" aria-expanded="false"><?=h($playerButton['label'])?></button><?php endif; ?><?php if($downloadUrl): ?><a class="speaker-audio__download <?=h(cms_action_classes($downloadButton))?>" href="<?=h($downloadUrl)?>" target="_blank" rel="noopener noreferrer"><?=h($downloadButton['label'])?></a><?php endif; ?><div class="speaker-audio__frame" hidden></div></div><?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>
  <?=cms_actions_html(cms_page_actions($content,'speakers'))?>
  <script defer src="/speakers-audio.js?v=4"></script>
<?php }

if ($section === 'services') { $data = $content['services'] ?? []; $chartUrl=cms_safe_url($data['chart_url']??''); $chartTop=($data['chart_position']??'top')!=='bottom'; ?>
  <?php if ($chartUrl && $chartTop): ?><a class="document-link" href="<?= h($chartUrl) ?>" target="_blank" rel="noopener"><img src="assets/emoji/aa_calendar.png" alt="" /><span><?=h(cms_site_text($content, 'services_chart_button'))?></span></a><?php endif; ?>
  <div class="service-lead"><?= cms_rich((string)($data['lead'] ?? '')) ?></div>
  <section class="content-card service-list">
    <?php foreach (($data['items'] ?? []) as $i => $item): ?><article class="service-item" id="<?=h(site_search_anchor('service', $item['id'] ?? '', (int)$i))?>"><h2><?= cms_display_text($item['title'] ?? '') ?><?php if (!empty($item['open'])): ?> - <span class="service-open"><?=h(cms_site_text($content, 'services_open_label'))?></span><?php endif; ?></h2><p><em>Ценз трезвости — <?= cms_display_text($item['sobriety'] ?? '') ?>; срок служения — <?= cms_display_text($item['term'] ?? '') ?></em></p><?php $holders = $item['holders'] ?? []; if (count($holders) > 1): ?><ol><?php foreach ($holders as $holder): ?><li><?= cms_display_text($holder['name'] ?? '') ?><?php if (!empty($holder['rotation'])): ?><br>Дата ротации: <?= h(date('d.m.Y', strtotime($holder['rotation']))) ?><?php endif; ?></li><?php endforeach; ?></ol><?php else: foreach ($holders as $holder): ?><p><?= cms_display_text($holder['name'] ?? '') ?></p><?php if (!empty($holder['rotation'])): ?><p>Дата ротации: <?= h(date('d.m.Y', strtotime($holder['rotation']))) ?></p><?php endif; endforeach; endif; ?><?=cms_actions_html($item['actions']??[], 'service-actions')?></article><?php endforeach; ?>
  </section>
  <?php if ($chartUrl && !$chartTop): ?><a class="document-link" href="<?= h($chartUrl) ?>" target="_blank" rel="noopener"><img src="assets/emoji/aa_calendar.png" alt="" /><span><?=h(cms_site_text($content, 'services_chart_button'))?></span></a><?php endif; ?>
  <?=cms_actions_html(cms_page_actions($content,'services'))?>
<?php }
page_foot();
