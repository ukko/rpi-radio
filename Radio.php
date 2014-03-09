<?php
namespace Rpi;

require_once __DIR__ . '/MPD/Client.php';

use MPD\Client;

class Radio
{
    /**
     * Instance MPD/Client
     *
     * @var Client
     */
    private     $mpd    = null;

    /**
     * Cache mpd status
     *
     * @var array
     */
    private     $status = null;

    /**
     * Path to console mp3 player
     *
     * @var string
     */
    private     $mpg        = '/usr/bin/mpg123';
    private     $at         = '/usr/bin/at';
    private     $atq        = '/usr/bin/atq';
    private     $atrm       = '/usr/bin/atrm';

    /**
     *
     * @var null|string
     */
    private     $lang       = null;

    private     $playlist   = null; // @FIXME

    private     $messages   = array(
        'ru'    => array(
            'sleep_on'  => 'Включен таймер спящего режима на %s %s',
            'sleep_off' => 'Выключен таймер спящего режима',
            'minutes'   => array('минута', 'минуты', 'минут'),
            'hours'     => array('час', 'часа', 'часов'),
        ),
    );

    public function __construct($host = '127.0.0.1', $lang = 'ru', $playlist)
    {
        $this->mpd      = new Client($host);
        $this->status   = $this->mpd->status();
        $this->lang     = $lang;
        $this->playlist = $playlist;
    }

    /**
     * Play next track
     * @param string $track next|prev|stop_play
     */
    public function changeTrack($track)
    {
        if ($track === 'next') {
            $this->mpd->next();
        } elseif ($track === 'prev') {
            $this->mpd->previous();
        }

        $this->sayCurrentRadio();
    }

    /**
     * Change the radio volume
     *
     * @param string $volume up|down
     */
    public function changeVolume($volume)
    {
        $step   = 10; // @TODO
        $file   = __DIR__ . '/data/audio-volume-change.mp3';

        if ($volume === 'down') {
            $val = $this->status['volume'] - $step;
        } else {
            $val = $this->status['volume'] + $step;
        }

        if ($val <= 0 || $val >= 100) {
            $val = ($val <= 0) ? 0 : 100;
            $file = __DIR__ . '/data/sonar.mp3';
        }

        $this->mpd->send('setvol', $val);

        $this->playFile($file);
    }

    /**
     * Says the name of the current station
     */
    public function sayCurrentRadio()
    {
        $radio      = $this->playlist[(int)$this->mpd->status('song')]; // FIXME
        $file       = __DIR__ . '/cache/radio_' . md5($radio[0]);
        if (! file_exists($file)) {
            $this->loadVoice($radio[0], $radio[1], $file);
        }

        $this->speakText($file);
    }

    /**
     * Says the current time
     */
    public function sayCurrentTime()
    {
        $string = $this->getTime();
        $file   = realpath(__DIR__ . '/cache/time.mp3');

        $this->loadVoice($string, $this->lang, $file);
        $this->speakText($file);
    }

    /**
     * Says the name of current track
     */
    public function sayCurrentTrack()
    {
        if ($this->status['state'] === 'play') {
            $title = $this->mpd->currentSong('title');
            if ($title) {
                $file   = __DIR__ . '/cache/current_song.mp3';
                $this->loadVoice($title, 'en', $file); // TODO Add autodetect language
                $this->speakText($file);
            }
        }
    }

    /**
     * Enables / disables the off timer
     *
     * @param $sleep
     * @return string
     */
    public function setSleepTime($sleep)
    {
        if ($this->removeCurrentJobs()) {
            $file = __DIR__ . '/cache/sleep_off.mp3';
            if (! file_exists($file)) {
                $this->loadVoice($this->getMessage('sleep_off'), $this->lang, $file);
            }
            return $this->playFile($file);
        }

        $command    = '%s now +%s min -q s -f %s/sleep.sh';
        system(sprintf($command, $this->at, $sleep, __DIR__));

        $file = __DIR__ . '/cache/sleep_on_' . $sleep . '.mp3';
        if (! file_exists($file)) {
            $minute = $this->plural_form($sleep, $this->getMessage('minutes'));
            $string = sprintf($this->getMessage('sleep_on'), $sleep, $minute); //TODO
            $this->loadVoice($string, $this->lang, $file);
        }

        $this->playFile($file);
    }

    /**
     * Play radio by position
     *
     * @param $position
     */
    public function playTrack($position)
    {
        $this->mpd->play($position);
        $this->sayCurrentRadio();
    }

    /**
     * Stop playing
     */
    public function stopPlayback()
    {
       $this->mpd->stop();
    }

    /**
     * Start or stop playing
     */
    public function startOrStopPlayback()
    {
        $this->mpd->playOrStop();

        if ($this->mpd->status('state') !== 'stop') {
            $this->sayCurrentRadio();
        }
    }

    private function getMessage($message)
    {
        return $this->messages[$this->lang][$message];
    }

    private function removeCurrentJobs()
    {
        $exists = false;
        exec(sprintf('%s -q s', $this->atq), $jobs);

        foreach($jobs as $jobs) {
            $num = explode("\t", $jobs);
            exec(sprintf('%s %s', $this->atrm, $num[0]));
            $exists = true;
        }

        return $exists;
    }

    private function playFile($file)
    {
        return system(sprintf('%s %s &', $this->mpg, $file));
    }

    private function speakText($file)
    {
        if ($this->status['state'] === 'play') {
            $this->mpd->stop();
        }

        $this->playFile($file);

        if ($this->status['state'] === 'play') {
            $this->mpd->play();
        }
    }

    /**
     * Keeps pronounced text file
     *
     * @param string    $string   text message
     * @param string    $lang
     * @param string    $file
     */
    private function loadVoice($string, $lang = 'ru', $file) {
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
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);

        curl_exec($ch);
        curl_close($ch);
        fclose($fp);
    }

    private function plural_form($n, $forms) {
        if ($n % 10 == 1 && $n % 100 != 11) {
            return $n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 10 || $n % 100 >= 20) ? $forms[1] : $forms[2];
        } else {
            return $forms[0];
        }
    }

    private function getTime()
    {
        $hour   = date('H');
        $minute = date('i');
        return sprintf(
            '%d %s %d %s',
            $hour,
            $this->plural_form($hour, $this->getMessage('hours')),
            $minute,
            $this->plural_form($minute, $this->getMessage('minutes'))
        );
    }
}
