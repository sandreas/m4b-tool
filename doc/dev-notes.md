# development notes

## todo
- build an autoupdater: https://moquet.net/blog/distributing-php-cli/

## FFMPEG samples

### detect silence

Detect 3 seconds of silence with -30dB noise tolerance:
```
ffmpeg -i "input.mov" -af silencedetect=noise=-30dB:d=3 -f null - 2> vol.txt
```

### extract part of m4b without losing quality

####  skip first 10 seconds
```
ffmpeg -i data/src.m4b -ss 10 -acodec copy -vn -f mp4 data/dest.m4b
```

####  from second 10 to 40
```
ffmpeg -i "data/src.m4b" -ss 10 -t 30 -acodec copy -vn -f mp4 data/dest.m4b
ffmpeg -i sample.avi -ss 00:03:05 -t 00:00:45.0 -q:a 0 -map a sample.mp3
```


### Merge files

```
ffmpeg -i 01.mp3 -i 02.mp3 -i 03.mp3 -filter_complex "[0:0] [1:0] [2:0] concat=n=3:v=0:a=1 [a]" -map [a] -ab 64k -f mp4 x.m4b
```

### extract metadata
```
ffmpeg -i data/src.m4b -f ffmetadata metadata.txt
```

### write chapters

#### chapter file format
Chapter file must contain following format:

Sample 1:
```
;FFMETADATA1
[CHAPTER]
TIMEBASE=1/1000
START=0
#chapter ends at 0:01:00
END=60000
title=chapter \#1
```

Sample 2:
```
;FFMETADATA1
major_brand=isom
minor_version=512
compatible_brands=isomiso2mp41
title=A title
artist=An Artist
composer=A composer
album=An Album
date=2011
description=A description
comment=A command
encoder=Lavf56.40.101
[CHAPTER]
TIMEBASE=1/1000
START=0
END=264034
title=001
[CHAPTER]
TIMEBASE=1/1000
START=264034
END=568958
title=002
[CHAPTER]
TIMEBASE=1/1000
START=568958
END=879455
title=003
```
#### Nero AND Quicktime
```
ffmpeg -i title01.mp4 -i title01.txt -c copy -map_metadata 1 title01m.mp4
ffmpeg -i title01.mp4 -i title01.txt -c copy -map_chapters 1 title01c.mp4
```

#### Quicktime only (-movflags disable_chpl)
```
ffmpeg -i title01.mp4 -i title01.txt -c copy -map_metadata 1 -movflags disable_chpl title01m1.mp4
ffmpeg -i title01.mp4 -i title01.txt -c copy -map_chapters 1 -movflags disable_chpl title01c1.mp4
```



### Possible fixes for merge issue
#### auto_convert 1 with mp3 sources
https://trac.ffmpeg.org/ticket/4498

ffmpeg -auto_convert 1 -f concat -i mylist.txt -c copy out.mp4


#### pad audio
 -af apad -shortest -avoid_negative_ts make_zero -fflags +genpts
 https://stackoverflow.com/questions/35416110/ffmpeg-concat-video-and-audio-out-of-sync

#### using filter instead of demuxer
ffmpeg -i -i -i ...

https://video.stackexchange.com/questions/19237/ffmpeg-concat-introduces-a-v-sync-problem
