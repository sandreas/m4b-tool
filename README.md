# m4b-tool
m4b-tool is a is a wrapper for ffmpeg and mp4v2 to merge, split or and manipulate m4b audiobook files with chapters


## Features

- Merge a set of audio files (e.g. MP3 or AAC) into a single m4b file
- Split a single m4b-File into several output files by chapters
- Add or adjust chapters to an existing m4b-File via musicbrainz and / or silence detection

## Requirements

m4b-tool is written in PHP and uses ffmpeg, mp4v2 and optionally fdkaac for high efficiency codecs to perform conversions. Therefore you will need the following tools in your $PATH:

- PHP >= 7.0 with mbstring extension enabled
- ffmpeg
- mp4v2
- fdkaac (only if you need high efficiency for low bitrates <= 32k)


### Installation

Download the built application from [releases](https://github.com/sandreas/m4b-tool/releases) and install the runtime dependencies (instructions follow).  Or, [build from source](#building-from-source).

#### General Notes

If you think there is an issue with m4b-tool, first head over to the [Known Issues](#known-issues).

#### Before you start - notes about audio quality

In m4b-tool all audio conversions are performed with ffmpeg with descent audio quality using its free encoders. 
However, best quality takes some extra effort. To get the best possible audio quality, you have to use a non-free encoder, that is not integrated in ffmpeg by default (licensing reasons). 
Depending on the operating system you are using, installing the non-free encoder may require a little extra skills, effort and time (see the notes for your operating system below).
You must decide for yourself, if it is worth the additional effort for getting the slightly better quality.

If you are using very low bitrates (<= 32k), you could use high efficiency profiles, to further improve audio quality. Unfortunately, `ffmpeg` produces files, that are incompatible with many players (including iTunes). To produce high efficiency files, that are compatible with at least most common players, you will need fdkaac for now.

More Details:
- https://github.com/sandreas/m4b-tool/issues/19
- https://trac.ffmpeg.org/wiki/Encode/AAC
- https://trac.ffmpeg.org/wiki/Encode/HighQualityAudio 

#### MacOS
On MacOS you can use **brew tap** to install `m4b-tool` via its own formula:


##### brew formula (recommended)
```
brew tap sandreas/tap

# this can take a while
brew install m4b-tool
```

##### manual installation

***Install requirements via brew***
```
# ffmpeg - Note: The flag _--with-fdk-aac easily activates the non-free aac encoder for best audio quality - there should be no reason to skip that
brew install ffmpeg --with-chromaprint --with-fdk-aac --with-freetype --with-libass --with-sdl2 --with-freetype --with-libquvi --with-libvorbis --with-libquvi --with-libvpx --with-opus --with-x265

# additional requirements
brew install php mp4v2 fdk-aac-encoder
```

**Install m4b-tool**
Download the latest release of m4b-tool.phar from https://github.com/sandreas/m4b-tool/releases to a directory of your choice.

```
wget https://github.com/sandreas/m4b-tool/releases/download/v.0.3.1/m4b-tool.phar -O m4b-tool && chmod +x m4b-tool
m4b-tool --version
```

#### Ubuntu

**Install ffmpeg**
> Note: For best audio quality with --with-fdk-aac, you could try to use a non-free repo, like https://launchpad.net/~spvkgn/+archive/ubuntu/ffmpeg-nonfree :
> ```
> sudo add-apt-repository ppa:spvkgn/ffmpeg-nonfree
> sudo apt-get update
> ```
> if this does not work, you have to compile yourself (https://trac.ffmpeg.org/wiki/CompilationGuide/Ubuntu) or must use the free codec with the command below

```
# Free codecs, not the best possible audio quality for aac / m4b
sudo apt install ffmpeg
```

Install mp4v2-utils
```
sudo apt install mp4v2-utils
```

Install fdkaac
```
sudo apt install fdkaac
```

Install PHP > 7.0
```
sudo apt install php-cli
```

#### Windows


To install, download releases from:

- ffmpeg: https://ffmpeg.org 
> Note: For best audio quality, you have to compile ffmpeg yourself with --with-fdk-aac (experts only) - as an easy approach, you could try the media-autobuild suite: https://github.com/jb-alvarado/media-autobuild_suite

- mp4v2: https://github.com/sandreas/m4b-tool/releases/download/0.1/mp4v2-windows.zip

- fdkaac (no official source!): http://wlc.io/2015/06/20/fdk-aac/

- PHP: http://windows.php.net/download/

And place them in your %PATH%


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

***Note*** If you use untagged audio files, you could provide a musicbrainz id to get the correct chapter names, see command [chapter](#chapter) for more info.

### Reference
For all options, see `php dist/m4b-tool.phar merge --help`:

```
Usage:
  merge [options] [--] <input> [<more-input-files>]...

Arguments:
  input                                          Input file or folder
  more-input-files                               Other Input files or folders

Options:
  -d, --debug                                    file to dump debugging info
      --debug-filename[=DEBUG-FILENAME]          file to dump debugging info [default: "m4b-tool_debug.log"]
  -f, --force                                    force overwrite of existing files
      --no-cache                                 do not use cached values and clear cache completely
      --ffmpeg-threads[=FFMPEG-THREADS]          specify -threads parameter for ffmpeg [default: ""]
      --convert-charset[=CONVERT-CHARSET]        Convert from this filesystem charset to utf-8, when tagging files (e.g. Windows-1252, mainly used on Windows Systems) [default: ""]
      --ffmpeg-param[=FFMPEG-PARAM]              Add argument to every ffmpeg call, append after all other ffmpeg parameters (e.g. --ffmpeg-param="-max_muxing_queue_size" --ffmpeg-param="1000" for ffmpeg [...] -max_muxing_queue_size 1000) (multiple values allowed)
      --audio-format[=AUDIO-FORMAT]              output format, that ffmpeg will use to create files [default: "m4b"]
      --audio-channels[=AUDIO-CHANNELS]          audio channels, e.g. 1, 2 [default: ""]
      --audio-bitrate[=AUDIO-BITRATE]            audio bitrate, e.g. 64k, 128k, ... [default: ""]
      --audio-samplerate[=AUDIO-SAMPLERATE]      audio samplerate, e.g. 22050, 44100, ... [default: ""]
      --audio-codec[=AUDIO-CODEC]                audio codec, e.g. libmp3lame, aac, ... [default: ""]
      --audio-profile[=AUDIO-PROFILE]            audio profile, when using extra low bitrate - valid values (mono, stereo): aac_he, aac_he_v2  [default: ""]
      --adjust-for-ipod                          auto adjust bitrate and sampling rate for ipod, if track is to long (may lead to poor quality)
      --name[=NAME]                              provide a custom audiobook name, otherwise the existing metadata will be used [default: ""]
      --album[=ALBUM]                            provide a custom audiobook album, otherwise the existing metadata for name will be used [default: ""]
      --artist[=ARTIST]                          provide a custom audiobook artist, otherwise the existing metadata will be used [default: ""]
      --genre[=GENRE]                            provide a custom audiobook genre, otherwise the existing metadata will be used [default: ""]
      --writer[=WRITER]                          provide a custom audiobook writer, otherwise the existing metadata will be used [default: ""]
      --albumartist[=ALBUMARTIST]                provide a custom audiobook albumartist, otherwise the existing metadata will be used [default: ""]
      --year[=YEAR]                              provide a custom audiobook year, otherwise the existing metadata will be used [default: ""]
      --cover[=COVER]                            provide a custom audiobook cover, otherwise the existing metadata will be used
      --description[=DESCRIPTION]                provide a custom audiobook short description, otherwise the existing metadata will be used
      --comment[=COMMENT]                        provide a custom audiobook comment, otherwise the existing metadata will be used
      --copyright[=COPYRIGHT]                    provide a custom audiobook copyright, otherwise the existing metadata will be used
      --encoded-by[=ENCODED-BY]                  provide a custom audiobook encoded-by, otherwise the existing metadata will be used
      --skip-cover                               skip extracting and embedding covers
      --output-file=OUTPUT-FILE                  output file
      --include-extensions[=INCLUDE-EXTENSIONS]  comma separated list of file extensions to include (others are skipped) [default: "aac,alac,flac,m4a,m4b,mp3,oga,ogg,wav,wma,mp4"]
  -m, --musicbrainz-id=MUSICBRAINZ-ID            musicbrainz id so load chapters from
      --mark-tracks                              add chapter marks for each track
      --auto-split-seconds[=AUTO-SPLIT-SECONDS]  auto split chapters after x seconds, if track is too long
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

This splits the file `data/my-audio-book.m4b into` an mp3 file for each chapter, writing the files into `data/my-audio-book_splitted/`.

### Reference
For all options, see `php dist/m4b-tool.phar split --help`:

```
Usage:
  split [options] [--] <input>

Arguments:
  input                                      Input file or folder

Options:
  -d, --debug                                file to dump debugging info
      --debug-filename[=DEBUG-FILENAME]      file to dump debugging info [default: "m4b-tool_debug.log"]
  -f, --force                                force overwrite of existing files
      --no-cache                             do not use cached values and clear cache completely
      --ffmpeg-threads[=FFMPEG-THREADS]      specify -threads parameter for ffmpeg [default: ""]
      --convert-charset[=CONVERT-CHARSET]    Convert from this filesystem charset to utf-8, when tagging files (e.g. Windows-1252, mainly used on Windows Systems) [default: ""]
      --ffmpeg-param[=FFMPEG-PARAM]          Add argument to every ffmpeg call, append after all other ffmpeg parameters (e.g. --ffmpeg-param="-max_muxing_queue_size" --ffmpeg-param="1000" for ffmpeg [...] -max_muxing_queue_size 1000) (multiple values allowed)
      --audio-format[=AUDIO-FORMAT]          output format, that ffmpeg will use to create files [default: "m4b"]
      --audio-channels[=AUDIO-CHANNELS]      audio channels, e.g. 1, 2 [default: ""]
      --audio-bitrate[=AUDIO-BITRATE]        audio bitrate, e.g. 64k, 128k, ... [default: ""]
      --audio-samplerate[=AUDIO-SAMPLERATE]  audio samplerate, e.g. 22050, 44100, ... [default: ""]
      --audio-codec[=AUDIO-CODEC]            audio codec, e.g. libmp3lame, aac, ... [default: ""]
      --audio-profile[=AUDIO-PROFILE]        audio profile, when using extra low bitrate - valid values (mono, stereo): aac_he, aac_he_v2  [default: ""]
      --adjust-for-ipod                      auto adjust bitrate and sampling rate for ipod, if track is to long (may lead to poor quality)
      --name[=NAME]                          provide a custom audiobook name, otherwise the existing metadata will be used [default: ""]
      --album[=ALBUM]                        provide a custom audiobook album, otherwise the existing metadata for name will be used [default: ""]
      --artist[=ARTIST]                      provide a custom audiobook artist, otherwise the existing metadata will be used [default: ""]
      --genre[=GENRE]                        provide a custom audiobook genre, otherwise the existing metadata will be used [default: ""]
      --writer[=WRITER]                      provide a custom audiobook writer, otherwise the existing metadata will be used [default: ""]
      --albumartist[=ALBUMARTIST]            provide a custom audiobook albumartist, otherwise the existing metadata will be used [default: ""]
      --year[=YEAR]                          provide a custom audiobook year, otherwise the existing metadata will be used [default: ""]
      --cover[=COVER]                        provide a custom audiobook cover, otherwise the existing metadata will be used
      --description[=DESCRIPTION]            provide a custom audiobook short description, otherwise the existing metadata will be used
      --comment[=COMMENT]                    provide a custom audiobook comment, otherwise the existing metadata will be used
      --copyright[=COPYRIGHT]                provide a custom audiobook copyright, otherwise the existing metadata will be used
      --encoded-by[=ENCODED-BY]              provide a custom audiobook encoded-by, otherwise the existing metadata will be used
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

Many m4b audiobook files do not contain valid chapters for different reasons. `m4b-tool` can handle two cases:

- Correct misplaced chapters by silence detection
- Add chapters from an internet source (mostly for well known titles)

### Misplaced chapters
In some cases there is a shift between the chapter mark and the real beginning of a chapter. `m4b-tool` could try
to correct that by detecting silences and relocating the chapter to the nearest silence:

```
php m4b-tool.phar chapters --adjust-by-silence -o "data/destination-with-adjusted-chapters.m4b" "data/source-with-misplaced-chapters.m4b"
```

It won't work, if the shift is to large or if the chapters are strongly misplaced, but since everything is done automatically, it's worth a try, isn't it?


### No chapters at all
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
  -d, --debug                                                file to dump debugging info
      --debug-filename[=DEBUG-FILENAME]                      file to dump debugging info [default: "m4b-tool_debug.log"]
  -f, --force                                                force overwrite of existing files
      --no-cache                                             do not use cached values and clear cache completely
      --ffmpeg-threads[=FFMPEG-THREADS]                      specify -threads parameter for ffmpeg [default: ""]
      --convert-charset[=CONVERT-CHARSET]                    Convert from this filesystem charset to utf-8, when tagging files (e.g. Windows-1252, mainly used on Windows Systems) [default: ""]
      --ffmpeg-param[=FFMPEG-PARAM]                          Add argument to every ffmpeg call, append after all other ffmpeg parameters (e.g. --ffmpeg-param="-max_muxing_queue_size" --ffmpeg-param="1000" for ffmpeg [...] -max_muxing_queue_size 1000) (multiple values allowed)
  -m, --musicbrainz-id=MUSICBRAINZ-ID                        musicbrainz id so load chapters from
  -a, --silence-min-length[=SILENCE-MIN-LENGTH]              silence minimum length in milliseconds [default: 1750]
  -b, --silence-max-length[=SILENCE-MAX-LENGTH]              silence maximum length in milliseconds [default: 0]
  -s, --merge-similar                                        merge similar chapter names
  -o, --output-file[=OUTPUT-FILE]                            write chapters to this output file [default: ""]
      --adjust-by-silence                                    will try to adjust chapters of a file by silence detection and existing chapter marks
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


# Known Issues

## PHP Exceptions

If you are getting PHP Exceptions, it is a configuration issue with PHP in most cases. If are not familiar with PHP configuration, 
you could follow these instructions, to fix a few known issues:


### Exception with DateTime::__construct


```
  [Exception]
  DateTime::__construct(): It is not safe to rely on the system's timezone settings. You are *required* to use the date.time
  zone setting or the date_default_timezone_set() function. In case you used any of those methods and you are still getting
  this warning, you most likely misspelled the timezone identifier. We selected the timezone 'UTC' for now, but please set d
  ate.timezone to select your timezone.
```

This happens, because PHP needs a preconfigured timezone to work correctly. There are two ways to fix this:

1. Recommended: Set the value for date.timezone in your php.ini once, e.g. `date.timezone=Europe/Berlin`
2. Set the configuration value for date.timezone inline everytime you use m4b-tool.phar, e.g. `php -d "date.timezone=UTC" m4b-tool.phar merge "data/my-audio-book" --output-file="data/my-audio-book.m4b"`

**This issue should be fixed in v0.2 and later.**

### Exception Charset not supported

```
[Exception]
  charset windows-1252 is not supported - use one of these instead: utf-8
```

This mostly happens on windows, because the mbstring-Extension is used to internally convert charsets, so that special chars like german umlauts 
are supported on every platform. To fix this, you need to enable the mbstring-extension:

Run `php --ini` on the command line:
```
C:\>php --ini
...
Loaded Configuration File:         C:\Program Files\php\php.ini
```

Open the configuration file (e.g. `C:\Program Files\php\php.ini`) in a text editor and search for `extension=`. On Windows there should be an item like this:
```
;extension=php_mbstring.dll
```
remove the `;` to enable the extension:
```
extension=php_mbstring.dll
```

Now everything should work as expected.

# Building from source

m4b-tool contains a `build` script, which will create an executable m4b-tool.phar in the dist folder. Composer for PHP 
is required, so after installing composer, run following commands in project root folder:

## Linux / Unix

### Install Dependencies (Ubuntu)

```shell
sudo apt install ffmpeg mp4v2-utils fdkaac php-cli composer phpunit php-mbstring
```

### Build

```
composer update
./build
```

## Windows 
```
composer update
build
```

