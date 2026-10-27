<?php
declare(strict_types=1);
require_once __DIR__ . '/../cms.php';

$speaker = [
    'id' => 'speaker_123',
    'audio_url' => 'https://drive.google.com/file/d/ORIGINAL_ID/view',
    'audio_stream_url' => 'https://drive.google.com/file/d/LIGHT_ID/view',
];
$preview = cms_speaker_preview_url($speaker);
$download = cms_speaker_download_url($speaker);
if (!str_contains($preview, '/LIGHT_ID/preview')) throw new RuntimeException('Архивный предпросмотр должен брать облегчённую копию.');
if ($download !== 'https://drive.google.com/uc?export=download&id=LIGHT_ID') throw new RuntimeException('Скачивание должно вести к облегчённой копии.');
if (cms_speaker_audio_stream_url($speaker) !== '/speaker-audio/speaker_123') throw new RuntimeException('Встроенный плеер должен использовать адрес защищённого потока на сайте.');
if (cms_speaker_audio_stream_url(['id'=>'bad id', 'audio_url'=>$speaker['audio_url']]) !== '') throw new RuntimeException('Для потока нужно безопасное ID записи.');
if (cms_speaker_audio_stream_url(['id'=>['bad'], 'audio_url'=>$speaker['audio_url']]) !== '') throw new RuntimeException('Для потока ID записи должен быть текстом.');
echo "Speaker audio player and download use the same lightweight Drive file.\n";
