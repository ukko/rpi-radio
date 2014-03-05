<?php
require_once __DIR__ . '/MPD/Client.php';

use MPD\Client;


$playlist   = [
    ['Радио Культура',  'ru'],
    ['Pinguin Radio',   'en'],
    ['Cruise One Radio','en'],
    ['Relax FM',        'en'],
    ['Zen Radio',       'en'],
    ['Calm Radio',      'en'],
];

$mpg = '/usr/bin/mpg123';

/**
 * - cli.php --channel=next       следующий канал
 * - cli.php --channel=prev       предыдущий канал
 * - cli.php --channel=info       текущий канал и трек
 * cli.php --track=next         следующий трек
 * cli.php --track=prev         предыдущие трек
 * cli.php --track=stop_play    остановить проигрывание или запустить
 * cli.php --play=<NUM>         воспроизвести трек по номеру
 * cli.php --info=time          произнести текущее время
 * cli.php --info=track         произнести текущий трек
 * cli.php --sleep=30           поставить таймер выключения, на указанное количество минут
 * cli.php --volume=up
 * cli.php --volume=down
 */

$mpd    = new Client('127.0.0.1');
$status = $mpd->status();
$params = array(
    'h'     => 'help',
    't:'    => 'track:',
    'c:'    => 'channel:',
    'i:'    => 'info:',
    's:'    => 'sleep:',
    'p:'    => 'play:',
    'v:'    => 'volume:',
);

$options = getopt(implode('', array_keys($params)), $params);

if (isset($options['channel']) || isset($options['c'])) {
    $channel = isset( $options['channel'] ) ? $options['channel'] : $options['c'];
}

if (isset($options['track']) || isset($options['t'])) {
    $track = isset( $options['track'] ) ? $options['track'] : $options['t'];

    if ($track === 'next') {
        $mpd->next();
    } elseif ($track === 'prev') {
        $mpd->previous();
    } elseif ($track === 'stop_play') {
        $mpd->playOrStop();

        if ($mpd->status('state') === 'stop') {
            return;
        }
    }

    nowPlaying($mpd, $mpg, $status, $playlist);
}

if (isset($options['volume']) || isset($options['v'])) {
    $volume = isset($options['volume']) ? $options['volume'] : $options['v'];
    $file   = __DIR__ . '/data/audio-volume-change.mp3';

    if ($volume === 'down') {
        $val = $status['volume'] - 5;
    } else {
        $val = $status['volume'] + 5;
    }

    if ($val <= 0 || $val >= 100) {
        $val = ($val <= 0) ? 0 : 100;
        $file = __DIR__ . '/data/sonar.mp3';
    }

    $mpd->send('setvol', $val);

    system(sprintf('%s %s &', $mpg, $file));
}

if (isset($options['info']) || isset($options['i'])) {
    $info   = isset( $options['info'] ) ? $options['info'] : $options['i'];

    if ($info === 'time') {
        $string = getTime();
        $file   = realpath(__DIR__ . '/cache/time.mp3');
        loadVoice($string, 'ru', $file);
        if ($status['state'] === 'play') {
            $mpd->stop();
        }
        system(sprintf('%s %s &', $mpg, $file));
        if ($status['state'] === 'play') {
            $mpd->play();
        }
    } else if ($info === 'track') {
         if ($status['state'] === 'play') {
            $mpd->stop();
            $title = $mpd->currentSong('title');
            if ($title) {
                $file   = __DIR__ . '/cache/current_song.mp3';
                loadVoice($title, 'en', $file);
                system(sprintf('%s %s &', $mpg, $file));
            }
            $mpd->play();
         }
    }

}

if (isset($options['sleep']) || isset($options['s'])) {
    exec('/usr/bin/atq -q s', $jobs);

    if (count($jobs)) {

        foreach($jobs as $jobs) {
            $num = explode("\t", $jobs);
            exec(sprintf('/usr/bin/atrm %s', $num[0]));
        }

        $file = __DIR__ . '/cache/sleep_off.mp3';
        if (! file_exists($file)) {
            $string = 'Выключен таймер спящего режима';
            loadVoice($string, 'ru', $file);
        }
        system(sprintf('%s %s &', $mpg, $file));
        return;
    }

    $sleep      = isset($options['sleep']) ? $options['sleep'] : $options['s'];
    $command    = '/usr/bin/at now +%s min -q s -f /home/pi/rpi-radio/sleep.sh';
    $res        = system(sprintf($command, $sleep));

    $file = __DIR__ . '/cache/sleep_on_' . $sleep . '.mp3';
    if (! file_exists($file)) {
        $string = sprintf('Включен таймер спящего режима на %s %s', $sleep, plural_form($sleep, ['минута', 'минуты', 'минут']));
        loadVoice($string, 'ru', $file);
    }
    system(sprintf('%s %s &', $mpg, $file));
}

if (isset($options['play']) || isset($options['p'])) {
    $position   = isset($options['play']) ? $options['play'] : $options['p'];
    $mpd->play($position);
    nowPlaying($mpd, $mpg, $status, $playlist);
}

function nowPlaying($mpd, $mpg, $status, $playlist) {
    $radio      = $playlist[(int)$mpd->status('song')];
    $file       = __DIR__ . '/cache/radio_' . md5($radio[0]);
    if (! file_exists($file)) {
        loadVoice($radio[0], $radio[1], $file);
    }

    if ($status['state'] === 'play') {
        $mpd->stop();
    }
    system(sprintf('%s %s &', $mpg, $file));

    if ($status['state'] === 'play') {
        $mpd->play();
    }
}

function getTime() {
    $hour   = date('H');
    $minute = date('i');
    return sprintf('%d %s %d %s', $hour, plural_form($hour, ['час', 'часа', 'часов']),
        $minute, plural_form($minute, ['минута', 'минуты', 'минут']));
}

function plural_form($n, $forms) {
    return $n%10==1&&$n%100!=11?$forms[0]:($n%10>=2&&$n%10<=4&&($n%100<10||$n%100>=20)?$forms[1]:$forms[2]);
}

/**
 * Загружает голос в файл
 *
 * @param $string
 * @param string $lang
 * @param $file
 */
function loadVoice($string, $lang = 'ru', $file) {
    $userAgent  = "Mozilla/5.0 (Windows; U; Windows NT 6.0; en-US; rv:1.9.1.5) Gecko/20091102 Firefox/3.5.5";
    $url        = 'http://translate.google.ru/translate_tts?tl=%s&prev=input&q=%s';
    $url        = sprintf($url, $lang, urlencode($string));

    $fp         = fopen($file, 'w+');
    $ch         = curl_init();
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER,0);
    curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_BINARYTRANSFER,1);

    curl_exec($ch);
    curl_close($ch);
    fclose($fp);
}
