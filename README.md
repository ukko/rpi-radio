rpi-radio
=========

 Managing your online radio via lirc on raspberry pi

Commands
--------

    cli.php --track=next                     Play next track (radio)
    cli.php --track=prev                     Play previous track (radio)
    cli.php --playback=stop_play             Stop (if playing), or Play (if stopping)
    cli.php --playback=stop                  Stop playing
    cli.php --playback=play --number=<NUM>   Play track by number, default NUM=0
    cli.php --info=time                      Say current time
    cli.php --info=track                     Say current track
    cli.php --sleep=30                       Set timer sleep on X minutes
    cli.php --volume=up                      Increase volume
    cli.php --volume=down                    Decrease volume


Third-party components
______________________

 * Sound files are from "Gnome Sound Theme"
 * MPD client fork from https://github.com/mutantlabs/SimpleMPDWrapper
 * Idea from http://kostya-ov.webnode.ru/news/proverka-dobavleniya-audio-fajla/
