<?php
namespace MPD;

/**
 * The MPD socket client
 *
 * Fork https://github.com/mutantlabs/SimpleMPDWrapper
 */
class Client
{
    /**
     * The file pointer used for accessing the server
     */
    private $fp = null;

    /**
     * The response
     */
    private $response;

    /**
     * @var Status
     */
    private $status = null;

    /**
     * Disconnect from the server
     */
    public function disconnect()
    {
        if ($this->fp !== null) {
            fclose($this->fp);
        }
    }

    /**
     * Construct
     *
     * @param string    $host
     * @param int       $port
     * @param string    $pass
     */
    public function __construct($host = "127.0.0.1", $port = 6600, $pass = "")
    {
        $this->connect($host, $port, $pass);
    }

    /**
     * Connect to the MPD server
     *
     * @param string $host The host name or IP address of the server
     * @param int    $port The port number via which to connect to the server
     * @param string $pass The password for the MPD server
     *
     * @throws MPDLoginFailedException
     * @throws MPDConnectionFailedException
     */
    public function connect($host = "127.0.0.1", $port = 6600, $pass = "")
    {
        // open the connection to the server
        $this->fp = fsockopen($host, $port, $errno, $errstr, 30);

        // check to see if we successfully connected
        if (!$this->fp) {
            // no connection
            throw new MPDConnectionFailedException("$errstr ($errno)");
        }

        // we did successfully connect

        // keep reading from the connection while we're getting data
        while (!feof($this->fp)) {
            // get a line from the stream
            $got = fgets($this->fp, 1024);

            // is the "MPD Ready" message? If so, leave the loop
            if (strncmp("OK", $got, strlen("OK")) == 0) {
                break;
            }

            // set the response value
            $this->response = "$got<br>";

            // is it an "ACK" (error) message? if so, leave the loop
            if (strncmp("ACK", $got, strlen("ACK")) == 0) {
                break;
            }
        }

        // do we have a password to send?
        if ($pass != "") {
            // send the password
            fputs($this->fp, "password \"$pass\"\n"); //Check Password

            // keep reading while we're getting data from the stream
            while (!feof($this->fp)) {
                // get a line from the stream
                $got = fgets($this->fp, 1024);

                // is it the "Login OK" message? if so, leave the loop
                if (strncmp("OK", $got, strlen("OK")) == 0) {
                    break;
                }

                // save the response string
                $this->response = "$got<br>";

                // is it an "ACK" (error) message? if so, the login failed
                if (strncmp("ACK", $got, strlen("ACK")) == 0) {
                    throw new MPDLoginFailedException();
                }
            }
        }
    }

    /**
     * "Condense" an array to a single non-associative array of scalar values.
     *
     * @param array $arr
     *
     * @throws Exception
     *
     * @return array
     */
    private function condense($arr)
    {
        $result = array();
        foreach (array_values($arr) as $a) {
            if (is_scalar($a)) {
                $result[] = $a;
            } elseif (is_array($a)) {
                $result = array_merge($result, $this->condense($a));
            } elseif (is_object($a)) {
                $result = array_merge($result, $this->condense((array) $a));
            } else {
                throw new \Exception("Unrecognized object type");
            }
        }

        return $result;
    }

    /**
     * Send a command to the server
     *
     * Our send method handles all commands and responses, you can use this
     * directly or the quick method wrappers below.
     *
     * @internal param string $method The method (command) string The method (command) string
     * @internal param string $arg1 The first argument* The first argument
     * @internal param string $arg2 The second argument* The second argument
     *
     * @return string
     * The response
     */
    public function send()
    {
        // get the arguments
        $args   = func_get_args();

        // the first argument is the method
        $method = array_shift($args);

        // now I want to handle any arrays in the arguments & build a single,
        // non-associative array of arguments whose elements are all scalar values
        $args = $this->condense($args);

        // wrap the remaining arguments in double quotes (escaping any quotes in the process)
        array_walk($args, function (&$value, $key)
        {
            $value = str_replace('"', '\"', $value);
            $value = str_replace("'", "\\'", $value);
            $value = '"' . $value . '"';
        });

        // build the command string
        $command = trim($method . ' ' . implode(' ', $args));

        // send the command to the server
        fputs($this->fp, "$command\n");

        $ret = array();

        // keep looping while we're getting data
        while (!feof($this->fp)) {
            // get a line of data
            $got = fgets($this->fp, 1024);

            // is the "OK" message? if so, leave the loop
            if (strncmp("OK", $got, strlen("OK")) == 0) {
                break;
            }

            // is the "ACK" (error) message? if so, leave the loop
            if (strncmp("ACK", $got, strlen("ACK")) == 0) {
                break;
            }

            // add whatever we got from the server to our list of strings
            $ret[] = $got;
        }

        // build a response array
        $sentResponse = array(
            "response" => $this->response,
            "status" => trim($got),
            "values" => $ret,
        );

        // return the response
        return $sentResponse;
    }

    /**
     * Add a resource to the playlist
     *
     * add {URI} - Adds the file URI to the playlist (directories add recursively). URI can also be a single file.
     *
     * @param string $string The item to add
     *
     * @throws MPDInvalidArgumentException
     *
     * @return array The response from the server
     */
    public function add($string)
    {
        // validation
        // the argument can be just about anything (I can't find a description
        // of the URI), so I'm just going to make sure it's a non-empty scalar
        // value
        if (!$string || !is_scalar($string)) {
            throw new MPDInvalidArgumentException("Add: invalid argument: $string");
        }

        // send the command
        return $this->send("add", $string);
    }

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
        $status = array();
        $dump   = $this->send("status");
        foreach ($dump['values'] as $value) {
            $array = explode(':', $value);
            $status[$array[0]] = trim($array[1]);
        }

        if ($info && isset($status[$info])) {
            $result = $status[$info];
        } else {
            $result = $status;
        }

        return $result;
    }

    /**
     * Clear the current playlist
     *
     * @return array
     * The response from the server
     */
    public function clear()
    {
        return $this->send("clear");
    }

    /**
     * Get the current song info
     * Displays the song info of the current song (same song that is identified in status).
     *
     * @param string $info file|title|name|id|pos
     *
     * @return array The response from the server
     */
    public function currentSong($info = null)
    {
        $current    = $this->send("currentsong");
        $values     = array();
        if ($info) {
            foreach ($current['values'] as $val) {
                $chunks = explode(':', $val, 2);
                $values[strtolower($chunks[0])] = trim($chunks[1]);
            }

            if (isset($values[$info])) {
                return $values[$info];
            } else {
                return false;
            }
        }

        return $current;
    }

    /**
     * Move a song within the current playlist
     *
     * move [{FROM} | {START:END}] {TO} - Moves the song at FROM or range of
     * songs at START:END to TO in the playlist.
     *
     * @param string $from The Song ID of the song to move
     * @param string $to   The playlist position to move to
     *
     * @throws MPDInvalidArgumentException
     *
     * @return array The response from the server
     */
    public function move($from, $to)
    {
        // validation

        // $from must be either a single integer value (a playlist position) or
        // a string describing a range (two integers separated by ':')
        if (!preg_match('/^([0-9]+|[0-9]+\:[0-9]+)$/', trim($from))) {
            throw new MPDInvalidArgumentException("Move: invalid FROM value: $from");
        }

        // $to must be a single integer value
        if (!is_numeric($to) || $to < 0) {
            throw new MPDInvalidArgumentException("Move: invalid TO value: $to");
        }

        // send the command
        return $this->send("move", $from, $to);
    }

    /**
     * Play song
     *
     * @param int|null $position
     *
     * @return string
     */
    public function play($position = null)
    {
        if ($position === null) {
            $result = $this->send('play');
        } else {
            $result = $this->send('play', $position);
        }

        return $result;
    }

    /**
     * Stop playing
     *
     * @return string
     */
    public function stop()
    {
        return $this->send('stop');
    }

    /**
     * @return string
     */
    public function next()
    {
        $currentId  = (int) $this->status('song');
        $nextId     = $currentId + 1;
        $count      = (int) $this->status('playlistlength');

        if ($nextId === $count) {
            $result = $this->play(0);
        } else {
            $result = $this->send('next');
        }

        return $result;
    }

    /**
     * @return string
     */
    public function previous()
    {
        $currentId  = (int) $this->status('song');
        $prevId     = $currentId - 1;
        $count      = (int) $this->status('playlistlength');

        if ($prevId === $count) {
            $result = $this->play($count - 1);
        } else {
            $result = $this->send('previous');
        }

        return $result;
    }

    /**
     * Play song if stopped, stop if played
     *
     * @return string
     */
    public function playOrStop()
    {
        if ($this->status('state') === 'play') {
            $result = $this->stop();
        } else {
            $result = $this->play();
        }

        return $result;
    }
}

/**
 * Class MPDConnectionFailedException
 *
 * @package MPD
 */
class MPDConnectionFailedException extends \Exception
{

}

/**
 * Class MPDLoginFailedException
 *
 * @package MPD
 */
class MPDLoginFailedException extends \Exception
{

}

/**
 * Class MPDInvalidArgumentException
 *
 * @package MPD
 */
class MPDInvalidArgumentException extends \Exception
{

}
