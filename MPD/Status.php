<?php
namespace MPD;

/**
 * Class Status
 *
 * @package MPD
 */
class Status
{
    /**
     * Request the server status. Reports the current status of the player and the volume level.
     *
     * @param string|null $info
     *
     * volume: 0-100
     * repeat: 0 or 1
     * random: 0 or 1
     * single: [2] 0 or 1
     * consume: [2] 0 or 1
     * playlist: 31-bit unsigned integer, the playlist version number
     * playlistlength: integer, the length of the playlist
     * state: play, stop, or pause
     * song: playlist song number of the current song stopped on or playing
     * songid: playlist songid of the current song stopped on or playing
     * nextsong: [2] playlist song number of the next song to be played
     * nextsongid: [2] playlist songid of the next song to be played
     * time: total time elapsed (of current playing/paused song)
     * elapsed: [3] Total time elapsed within the current song, but with higher resolution.
     * bitrate: instantaneous bitrate in kbps
     * xfade: crossfade in seconds
     * mixrampdb: mixramp threshold in dB
     * mixrampdelay: mixrampdelay in seconds
     * audio: sampleRate:bits:channels
     * updating_db: job id
     * error: if there is an error, returns message here
     *
     * @return array The response from the server
     */
    public function status($info = null)
    {
        $status = $this->send("status");

        if ($info && isset($status[$info])) {
            $result = $status[$info];
        } else {
            $result = $status;
        }

        return $result;
    }

    /**
     *
     *
     * @param string|null $info
     *
     * @return mixed
     */
    public function stats($info = null)
    {
        $stats = $this->send('stats');

        if ($info && isset($stats[$info])) {
            $result = $stats[$info];
        } else {
            $result = $stats;
        }

        return $result;
    }
}
