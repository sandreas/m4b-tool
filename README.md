# m4b-tool
m4b-tool is a tool to merge, split and manipulate m4b audiobook files with chapters


## Features

- Merge a set of audio files into a single m4b file
- Split a single m4b-File into several output files by chapters
- Add chapters to an existing m4b-File via musicbrainz and silence detection

## Requirements

m4b-tool is written in PHP (yes!) and uses ffmpeg and mp4v2 to perform conversions. Therefore you will need the following tools in your $PATH:

- PHP >= 7.0
- ffmpeg
- mp4v2


### Installation


#### MacOS
On MacOS you can use brew to install the most requirements:


Install ffmpeg
```
brew install ffmpeg --with-fdk-aac --with-ffplay --with-freetype --with-libass --with-libquvi --with-libvorbis --with-libvpx --with-opus --with-x265
```

Install mp4v2

```
brew install mp4v2
```


Install PHP >= 7.0

Follow the instructions on https://php-osx.liip.ch


#### Ubuntu

Install ffmpeg
```
sudo apt install ffmpeg
```

Install mp4v2
```
sudo apt install mp4v2
```

Install PHP > 7.0
```
sudo apt install php-cli
```

#### Windows

Download releases from:
ffmpeg: https://ffmpeg.org
 
mp4tools: http://forum.doom9.org/showthread.php?t=171038

PHP: http://windows.php.net/download/

And place them in your %Path%


# m4b-tool commands

## merge

With m4b-tool you can merge a set of audio files to one single m4b audiobook file. 

### An example:
```
php m4b-tool.phar merge "data/my-audio-book" --output-file="data/my-audio-book.m4b"
```

This merges all Audio-Files in folder `data/my-audio-book` into `my-audio-book.m4b`, using 
the tag-title of every file for generating chapters.

If there is a file `data/my-audio-book/cover.jpg`, it will be used as cover for the resulting m4b file.

### Reference
For all options, see `php dist/m4b-tool.phar merge --help`:

```
Usage:
  merge [options] [--] <input> [<more-input-files>]...

Arguments:
  input                                          Input file or folder
  more-input-files                               Other Input files or folders

Options:
  -d, --debug                                    show debugging info about chapters and silences
  -f, --force                                    force overwrite of existing files
      --no-cache                                 do not use cached values and clear cache completely
      --audio-format[=AUDIO-FORMAT]              output format, that ffmpeg will use to create files [default: "m4b"]
      --audio-channels[=AUDIO-CHANNELS]          audio channels, e.g. 1, 2 [default: ""]
      --audio-bitrate[=AUDIO-BITRATE]            audio bitrate, e.g. 64k, 128k, ... [default: ""]
      --audio-samplerate[=AUDIO-SAMPLERATE]      audio samplerate, e.g. 22050, 44100, ... [default: ""]
      --audio-codec[=AUDIO-CODEC]                audio codec, e.g. libmp3lame, aac, ... [default: ""]
      --name[=NAME]                              provide a custom audiobook name, otherwise the existing metadata will be used [default: ""]
      --artist[=ARTIST]                          provide a custom audiobook artist, otherwise the existing metadata will be used [default: ""]
      --genre[=GENRE]                            provide a custom audiobook genre, otherwise the existing metadata will be used [default: ""]
      --writer[=WRITER]                          provide a custom audiobook writer, otherwise the existing metadata will be used [default: ""]
      --albumartist[=ALBUMARTIST]                provide a custom audiobook albumartist, otherwise the existing metadata will be used [default: ""]
      --year[=YEAR]                              provide a custom audiobook year, otherwise the existing metadata will be used [default: ""]
      --cover[=COVER]                            provide a custom audiobook cover, otherwise the existing metadata will be used
      --skip-cover                               skip extracting and embedding covers
      --output-file=OUTPUT-FILE                  output file
      --include-extensions[=INCLUDE-EXTENSIONS]  comma separated list of file extensions to include (others are skipped) [default: "m4b,mp3,aac,mp4,flac"]
  -h, --help                                     Display this help message
  -q, --quiet                                    Do not output any message
  -V, --version                                  Display this application version
      --ansi                                     Force ANSI output
      --no-ansi                                  Disable ANSI output
  -n, --no-interaction                           Do not ask any interactive question
  -v|vv|vvv, --verbose                           Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Help:
  Merges a set of files to one single file
```


## split

m4b-tool can be used to split a single m4b into a file per chapter.

### An example:
```
php m4b-tool.phar split --audio-format mp3 --audio-bitrate 96k --audio-channels 1 --audio-samplerate 22050 "data/my-audio-book.m4b"
```

This splits the file `data/my-audio-book.m4b into` am mp3 file for each chapter, writing the files into `data/my-audio-book_splitted/`.

### Reference
For all options, see `php dist/m4b-tool.phar merge --help`:

```
Usage:
  split [options] [--] <input>

Arguments:
  input                                      Input file or folder

Options:
  -d, --debug                                show debugging info about chapters and silences
  -f, --force                                force overwrite of existing files
      --no-cache                             do not use cached values and clear cache completely
      --audio-format[=AUDIO-FORMAT]          output format, that ffmpeg will use to create files [default: "m4b"]
      --audio-channels[=AUDIO-CHANNELS]      audio channels, e.g. 1, 2 [default: ""]
      --audio-bitrate[=AUDIO-BITRATE]        audio bitrate, e.g. 64k, 128k, ... [default: ""]
      --audio-samplerate[=AUDIO-SAMPLERATE]  audio samplerate, e.g. 22050, 44100, ... [default: ""]
      --audio-codec[=AUDIO-CODEC]            audio codec, e.g. libmp3lame, aac, ... [default: ""]
      --name[=NAME]                          provide a custom audiobook name, otherwise the existing metadata will be used [default: ""]
      --artist[=ARTIST]                      provide a custom audiobook artist, otherwise the existing metadata will be used [default: ""]
      --genre[=GENRE]                        provide a custom audiobook genre, otherwise the existing metadata will be used [default: ""]
      --writer[=WRITER]                      provide a custom audiobook writer, otherwise the existing metadata will be used [default: ""]
      --albumartist[=ALBUMARTIST]            provide a custom audiobook albumartist, otherwise the existing metadata will be used [default: ""]
      --year[=YEAR]                          provide a custom audiobook year, otherwise the existing metadata will be used [default: ""]
      --cover[=COVER]                        provide a custom audiobook cover, otherwise the existing metadata will be used
      --skip-cover                           skip extracting and embedding covers
      --use-existing-chapters-file           adjust chapter position by nearest found silence
  -h, --help                                 Display this help message
  -q, --quiet                                Do not output any message
  -V, --version                              Display this application version
      --ansi                                 Force ANSI output
      --no-ansi                              Disable ANSI output
  -n, --no-interaction                       Do not ask any interactive question
  -v|vv|vvv, --verbose                       Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Help:
  Split an m4b into multiple m4b or mp3 files by chapter

```


## chapter

Many m4b audiobook files do not contain valid chapters for different reasons. 
If you have a well known audiobook, like ***Harry Potter and the Philosopher’s Stone***, 
you might be lucky that it is on musicbrainz.
 
In this case m4b-tool can try to correct the chapter information using silence 
detection and the musicbrainz data.

Since this is not a trivial task and prone to error, m4b-tool offers some parameters to correct 
misplaced chapter positions manually. 

### A typical workflow

#### Getting the musicbrainz id
You have to find the exact musicbrainz id:

- An easy way to find the book is to use the authors name or the readers name to search for it
- Once you found the book of interest, click on the list entry to show further information
- To get the musicbrainz id, open the ***details*** page and find the MBID (e.g. `8669da33-bf9c-47fe-adc9-23798a37b096`)

Example: https://musicbrainz.org/work/8669da33-bf9c-47fe-adc9-23798a37b096 
```
MBID: 8669da33-bf9c-47fe-adc9-23798a37b096
```
#### Finding main chapters

After getting the MBID you should find the main chapter points (where the name of the current chapter name is read aloud by the author).
```
php m4b-tool.phar chapters --merge-similar --first-chapter-offset 4000 --last-chapter-offset 3500 -m 8669da33-bf9c-47fe-adc9-23798a37b096 "../data/harry-potter-1.m4b"
```

Explanation:

- `--merge-similar`: merges all similar chapters (e.g. ***The Boy Who Lived, Part 1*** and ***The Boy Who Lived, Part 2*** will be merged to ***The Boy Who Lived***)
- `--first-chapter-offset`: creates an start offset chapter called ***Offset First Chapter*** with a length of 4 seconds for skipping intros (e.g. audible, etc.)
- `--last-chapter-offset`: creates an end offset chapter called ***Offset Last Chapter*** with a length of 3,5 seconds for skipping outros (e.g. audible, etc.)
- `-m`: MBID


#### Finding misplaced main chapters

Now listen to the audiobook an go through the chapters. Lets assume, all but 2 chapters were detected correctly. 
The two misplaced chapters are chapter number 6 and 9.

To find the real position of chapters 6 and 9 invoke:

```
php m4b-tool.phar chapter --find-misplaced-chapters 5,8  --merge-similar --first-chapter-offset 4000 --last-chapter-offset 3500 -m 8669da33-bf9c-47fe-adc9-23798a37b096 "../data/harry-potter-1.m4b"
```

Explanation:
`--find-misplaced-chapters`: Comma separated list of chapter numbers, that were not detected correctly.

Now m4b-tool will generate a ***potential chapter*** for every silence around the used chapter mark to find the right chapter position.

Listen to the audiobook again and find the right chapter position. Note them down.

#### Manually adjust misplaced chapters

Next run the full chapter detection with the --no-chapter-import option, which prevents writing the chapters directly to the file.
```
php m4b-tool.phar chapter --no-chapter-import --first-chapter-offset 4000 --last-chapter-offset 3500 -m 8669da33-bf9c-47fe-adc9-23798a37b096 "../data/harry-potter-1.m4b"
```

To Adjust misplaced chapters, do the following:

- Change the start position of all misplaced chapters manually in the file `../data/harry-potter-1.chapters.txt`
- Import the corrected chapters with `mp4chaps -i ../data/harry-potter-1.m4b`

Listen to `harry-potter-1.m4b` again, now the chapters should be at the correct position.


#### Troubleshooting

If none of the chapters are detected correctly, this can have different reasons:

- The silence parts of this audiobook are too short for detection. To adjust the minimum silence length, use `--silence-min-length 1000` setting the silence length to 1 second. 
  - Caution: To low values can lead to misplaced chapters and increased detection time.
- You provided the wrong MBID
- There is too much background noise in this specific audiobook, so that silences cannot be detected



#### Reference
For all options, see `php dist/m4b-tool.phar chapters --help`:

```
Usage:
  chapters [options] [--] <input>

Arguments:
  input                                                      Input file or folder

Options:
  -d, --debug                                                show debugging info about chapters and silences
  -f, --force                                                force overwrite of existing files
      --no-cache                                             do not use cached values and clear cache completely
  -m, --musicbrainz-id=MUSICBRAINZ-ID                        musicbrainz id so load chapters from
  -a, --silence-min-length[=SILENCE-MIN-LENGTH]              silence minimum length in milliseconds [default: 1750]
  -b, --silence-max-length[=SILENCE-MAX-LENGTH]              silence maximum length in milliseconds [default: 0]
  -s, --merge-similar                                        merge similar chapter names
  -o, --output-file[=OUTPUT-FILE]                            write chapters to this output file [default: ""]
      --find-misplaced-chapters[=FIND-MISPLACED-CHAPTERS]    mark silence around chapter numbers that where not detected correctly, e.g. 8,15,18 [default: ""]
      --find-misplaced-offset[=FIND-MISPLACED-OFFSET]        mark silence around chapter numbers with this offset seconds maximum [default: 120]
      --find-misplaced-tolerance[=FIND-MISPLACED-TOLERANCE]  mark another chapter with this offset before each silence to compensate ffmpeg mismatches [default: -4000]
      --no-chapter-numbering                                 do not append chapter number after name, e.g. My Chapter (1)
      --no-chapter-import                                    do not import chapters into m4b-file, just create chapters.txt
      --chapter-pattern[=CHAPTER-PATTERN]                    regular expression for matching chapter name [default: "/^[^:]+[1-9][0-9]*:[\s]*(.*),.*[1-9][0-9]*[\s]*$/i"]
      --chapter-replacement[=CHAPTER-REPLACEMENT]            regular expression replacement for matching chapter name [default: "$1"]
      --chapter-remove-chars[=CHAPTER-REMOVE-CHARS]          remove these chars from chapter name [default: "„“”"]
      --first-chapter-offset[=FIRST-CHAPTER-OFFSET]          milliseconds to add after silence on chapter start [default: 0]
      --last-chapter-offset[=LAST-CHAPTER-OFFSET]            milliseconds to add after silence on chapter start [default: 0]
  -h, --help                                                 Display this help message
  -q, --quiet                                                Do not output any message
  -V, --version                                              Display this application version
      --ansi                                                 Force ANSI output
      --no-ansi                                              Disable ANSI output
  -n, --no-interaction                                       Do not ask any interactive question
  -v|vv|vvv, --verbose                                       Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Help:
  Can add Chapters to m4b files via different types of inputs

```

# Building from source

m4b-tool contains a `build` script, which will create an executable m4b-tool.phar in the dist folder. Composer for PHP 
is required, so after installing composer, run following commands in project root folder:

## Linux / Unix
```
composer update
./build
```

## Windows 
```
composer update
build
```





