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
ffmpeg -i "data\src.m4b" -ss 10 -acodec copy -vn -f mp4 data\dest.m4b
```

####  from second 10 to 40
```
ffmpeg -i "data\src.m4b" -ss 10 -t 30 -acodec copy -vn -f mp4 data\dest.m4b
```