<?php
namespace Rpi;

require_once __DIR__ . '/Radio.php';

/**
 * - cli.php --channel=next       следующий канал
 * - cli.php --channel=prev       предыдущий канал
 * - cli.php --channel=info       текущий канал и трек
 *
 * cli.php --track=next                     следующий трек
 * cli.php --track=prev                     предыдущие трек
 * cli.php --playback=stop_play             остановить или запустить проигрывание
 * cli.php --playback=stop                  остановить проигрывание
 * cli.php --playback=play --number=<NUM>   воспроизвести трек по номеру, по-умолчанию NUM=0
 * cli.php --info=time                      произнести текущее время
 * cli.php --info=track                     произнести текущий трек
 * cli.php --sleep=30                       поставить таймер выключения, на указанное количество минут
 * cli.php --volume=up                      увеличить громкость
 * cli.php --volume=down                    уменьшить громкость
 */

$params = array(
    'h'     => 'help',
    't:'    => 'track:',
    'c:'    => 'channel:',
    'i:'    => 'info:',
    's:'    => 'sleep:',
    'p:'    => 'playback:',
    'v:'    => 'volume:',
    'n:'    => 'number:',
);
$options = getopt(implode('', array_keys($params)), $params);

$playlist = array(                  // @FIXME
    array('Радио Культура',  'ru'),
    array('Pinguin Radio',   'en'),
    array('Pinguin Classics','en'),
    array('Cruise One Radio','en'),
    array('Relax FM',        'en'),
    array('Zen Radio',       'en'),
    array('Calm Radio',      'en'),
);

$radio  = new Radio('127.0.0.1', 'ru', $playlist);

if (isset($options['channel']) || isset($options['c'])) {
    $channel = isset( $options['channel'] ) ? $options['channel'] : $options['c'];
    // TODO
}

if (isset($options['track']) || isset($options['t'])) {
    $track = isset($options['track']) ? $options['track'] : $options['t'];
    $radio->changeTrack($track);
}

if (isset($options['volume']) || isset($options['v'])) {
    $volume = isset($options['volume']) ? $options['volume'] : $options['v'];
    $radio->changeVolume($volume);
}

if (isset($options['info']) || isset($options['i'])) {
    $info   = isset( $options['info'] ) ? $options['info'] : $options['i'];
    if ($info === 'time') {
        $radio->sayCurrentTime();
    } else {
        $radio->sayCurrentTrack();
    }
}

if (isset($options['sleep']) || isset($options['s'])) {
    $sleep      = isset($options['sleep']) ? $options['sleep'] : $options['s'];
    $radio->setSleepTime($sleep);
}

if (isset($options['playback']) || isset($options['p'])) {
    $playback   = isset($options['playback']) ? $options['playback'] : $options['p'];
    if ($playback === 'start_stop') {
        $radio->startOrStopPlayback();
    } elseif ($playback === 'stop') {
        $radio->stopPlayback();
    } else {
        $position   = isset($options['number']) ? $options['number'] : $options['n'];
        $radio->playTrack((int)$position);
    }
}
