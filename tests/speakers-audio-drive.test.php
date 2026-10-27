<?php
declare(strict_types=1);
require_once __DIR__ . '/../cms.php';

$speaker = [
    'audio_url' => 'https://drive.google.com/file/d/ORIGINAL_ID/view',
    'audio_stream_url' => 'https://drive.google.com/file/d/LIGHT_ID/view',
];
$preview = cms_speaker_preview_url($speaker);
$download = cms_speaker_download_url($speaker);
if (!str_contains($preview, '/LIGHT_ID/preview')) throw new RuntimeException('Архивный предпросмотр должен брать облегчённую копию.');
if ($download !== 'https://drive.google.com/uc?export=download&id=LIGHT_ID') throw new RuntimeException('Встроенный аудиоплеер и скачивание должны использовать прямую ссылку на одну облегчённую копию.');
echo "Speaker audio player and download use the same lightweight Drive file.\n";
