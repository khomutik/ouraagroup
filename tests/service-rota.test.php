<?php
declare(strict_types=1);

require dirname(__DIR__) . '/cms.php';

$csv = "ДАТА,ДЕНЬ НЕДЕЛИ,ТЕМА СОБРАНИЯ,ВЕДУЩИЙ,ТЕХВЕД\n"
    . "12.10.2026,понедельник,\"Чтение БК, доп.темы\",Валя,Маня\n"
    . "09.10.2026,пятница,Спикерская,Никитос,Денис\n"
    . "не дата,понедельник,тест,не должно попасть,не должно попасть\n";
$rows = cms_parse_service_schedule_csv($csv);

if (count($rows) !== 2) throw new RuntimeException('Парсер графика должен пропускать строки без корректной даты.');
if ($rows[0]['date'] !== '2026-10-09' || $rows[0]['day'] !== 5) throw new RuntimeException('Даты должны сортироваться по возрастанию и получать правильный день недели.');
if ($rows[1]['speaker'] !== 'Валя' || $rows[1]['tech'] !== 'Маня') throw new RuntimeException('Не удалось прочитать Ведущего и Техведа из столбцов Google CSV.');
if (array_keys($rows[0]) !== ['date','day','speaker','tech']) throw new RuntimeException('В служебный график не должны попадать темы собраний и другие столбцы.');

echo "Service rota CSV checks passed.\n";
