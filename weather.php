<?php
$url    = 'http://export.yandex.ru/weather-ng/forecasts/27612.xml';
$file   = __DIR__ . '/cache/temp.txt';

$data   = file_get_contents($url);
$xml    = new SimpleXMLElement($data);
$wtemp  = ($xml->fact->temperature > 0 ? 'плюс ' : 'минус ') . abs((int)$xml->fact->temperature);
$wtype  = $xml->fact->weather_type;

$result = sprintf('В Москве %s. %s. ', $wtemp, $wtype);
file_put_contents($file, $result);

