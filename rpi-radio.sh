#!/bin/bash
# здесь объявляем активный режим
# две переменные передаются при вызове файла

USERAGENT="Mozilla/5.0 (Windows; U; Windows NT 6.0; en-US; rv:1.9.1.5) Gecko/20091102 Firefox/3.5.5"
SPEAKURL="http://translate.google.com/translate_tts?tl=ru&q="

CURDIR="/home/pi/rpi-radio"

TEMPPATH="$CURDIR/cache/temp.txt"
TIMEPATH="$CURDIR/cache/time.mp3"
RADIOPATH="$CURDIR/cache/radio.mp3"
RADIOPLAYLIST="$CURDIR/playlist.txt"

# в режиме радио озвучиваем проигрываемую станцию
if ( [ "$1" = "radio" ] ); then
    SPEAKURL="http://translate.google.com/translate_tts?tl=en&q="
    NUMSTR=`mpc | grep "playing"` # выбираем строку с номером канала
    # выбираем именно номер одинарный или двухзначный
    # теоретически добавив еще одно ?[0-9] можно сделать трехзначный
    if [[ "$NUMSTR" =~ [+-]?[0-9]?[0-9] ]]; then
        NUM=$BASH_REMATCH'p' #добавляем "р", для правильного параметра комады sed
    fi

    #присваиваем переменной значение из строки с номером канала
    RADIOTEXT=`sed -n $NUM $RADIOPLAYLIST`
    wget -U "$USERAGENT" "$SPEAKURL$RADIOTEXT" -O "$RADIOPATH"
    # и воспроизводим его
    mpc stop && mpg123 "$RADIOPATH" && mpc play
fi

# говорим погоду и время
if ( [ "$1" = "time" ] ); then
    TEMPTEXT=`cat $TEMPPATH`
    TIMETEXT=`date +%k" ч. "%M" мин"`

    wget -U "$USERAGENT" "$SPEAKURL$TIMETEXT" -O "$TIMEPATH"
    mpg123 "$TIMEPATH"
fi
