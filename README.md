# m4b-tool
m4b-tool is a is a wrapper for `ffmpeg` and `mp4v2` to merge, split or and manipulate audiobook files with chapters. Although `m4b-tool` is designed to handle m4b files, nearly all audio formats should be supported, e.g. mp3, aac, ogg, alac and flac.

## ![#f03c15](https://placehold.it/15/f03c15/000000?text=+) Warning: using`--no-conversion` parameter may lead to data loss
<span style="color:red">Using the `--no-conversion` flag may delete or overwrite files without interaction and can cause data-loss. Use only, if you backup the original files! See https://github.com/sandreas/m4b-tool/issues/27 for details.</span>

## Features

- `merge` a set of audio files (e.g. MP3 or AAC) into a single m4b file
- `split` a single m4b file into several output files by chapters
- Adding or adjusting `chapters` for an existing m4b file via silence detection or musicbrainz

## TL;DR - examples for the most common tasks

> `merge` all audio files in directory `data/my-audio-book` into file `data/merged.m4b` (tags are retained and `data/my-audio-book/cover.jpg` is embedded, if available)
```
m4b-tool merge "data/my-audio-book/" --output-file="data/merged.m4b"
```

> `split` one big m4b file by chapter into multiple mp3 files at `data/my-audio-book_splitted/` (tags are retained, `data/my-audio-book_splitted/cover.jpg` is created, if m4b contains a cover)
```
m4b-tool split --audio-format mp3 --audio-bitrate 96k --audio-channels 1 --audio-samplerate 22050 "data/my-audio-book.m4b"
``` 

> `chapters` can try to adjust existing chapters of an m4b by silence detection
```
m4b-tool chapters --adjust-by-silence -o "data/destination-with-adjusted-chapters.m4b" "data/source-with-misplaced-chapters.m4b"
``` 

## Installation

### MacOS

```
# install ffmpeg with best audio quality options
brew tap varenc/ffmpeg
brew tap-pin varenc/ffmpeg
brew uninstall ffmpeg
brew install ffmpeg --with-chromaprint --with-fdk-aac


# tap m4b-tool repository
brew tap sandreas/tap
brew tap-pin sandreas/tap

# install m4b-tool
brew install m4b-tool

# check installed m4b-tool version
m4b-tool --version
```


### Ubuntu

```
# install all dependencies
sudo apt install ffmpeg mp4v2-utils fdkaac php-cli

# install / upgrade m4b-tool
sudo wget https://github.com/sandreas/m4b-tool/releases/download/v.0.3.2/m4b-tool.phar -O /usr/local/bin/m4b-tool && sudo chmod +x /usr/local/bin/m4b-tool

# check installed m4b-tool version 
m4b-tool --version
```

> Note: If you would like to get the [best possible audio quality](#about-audio-quality), you have to compile `ffmpeg` with the high quality encoder `fdk-aac` - see https://trac.ffmpeg.org/wiki/CompilationGuide/Ubuntu for a step-by-step guide to compile `ffmpeg`.


### Manual installation (only recommended on Windows systems)

m4b-tool is written in PHP and uses `ffmpeg`, `mp4v2` and optionally `fdkaac` for high efficiency codecs to perform conversions. Therefore you will need the following tools in your $PATH:

- `php` >= 7.0 with `mbstring` extension enabled
- `ffmpeg`
- `mp4v2` (`mp4chaps`, `mp4art`, etc.)
- `fdkaac` (optional, only if you need high efficiency for low bitrates <= 32k)

If these are all installed, `m4b-tool` should work like expected. To install `m4b-tool` and its dependencies manually:

- Ensure that the required tools are installed, placed in your `%PATH%` and available via command line
    - `ffmpeg` (https://www.ffmpeg.org)
    - `mp4v2` (https://github.com/sandreas/m4b-tool/releases/download/0.1/mp4v2-windows.zip, sources at https://github.com/TechSmith/mp4v2)
    - `fdkaac` (http://wlc.io/2015/06/20/fdk-aac/ - caution: not official!), sources at https://github.com/nu774/fdkaac
    - `php` (https://php.net)
- And download the latest release from https://github.com/sandreas/m4b-tool/releases, call e.g. `php m4b-tool.phar --version` or `m4b-tool --version`, you could also [build from source](#building-from-source).


You think there is an issue with `m4b-tool`? First head over to the [Known Issues](#known-issues), if this does not help, please provide the following information when adding an issue:

- the operating system you use
- the exact command, that you tried, e.g. `m4b-tool merge my-audio-book/ -o merged.m4b`
- the error message, that occured or the circumstances, e.g. `the resulting file merged.m4b is only 5kb`
- other relevant information, e.g. sample files if needed

## About audio quality

In `m4b-tool` all audio conversions are performed with `ffmpeg` resulting in pretty descent audio quality using its free encoders. However, best quality takes some extra effort, so if you are using the free encoders, `m4b-tool` will show the following hint:

> Your ffmpeg version cannot produce top quality aac using encoder aac instead of libfdk_aac

To overcome this hint and get the best possible audio quality, you have to use a non-free encoder, that is not integrated in `ffmpeg` by default (licensing reasons). 
Depending on the operating system you are using, installing the non-free encoder may require a little extra skills, effort and time (see the notes for your operating system above).
You have to decide, if it is worth the additional effort for getting the slightly better quality.

If you are using very low bitrates (<= 32k), you could also use high efficiency profiles to further improve audio quality (e.g. `--audio-profile=aac_he` for mono). Unfortunately, `ffmpeg`'s high efficiency implementation produces audio files, that are incompatible with many players (including iTunes). To produce high efficiency files, that are compatible with at least most common players, you will need to install `fdkaac` for now.

More Details:
- https://github.com/sandreas/m4b-tool/issues/19
- https://trac.ffmpeg.org/wiki/Encode/AAC
- https://trac.ffmpeg.org/wiki/Encode/HighQualityAudio 


# m4b-tool commands

## merge

With m4b-tool you can merge a set of audio files to one single m4b audiobook file. 

### Example:
```
m4b-tool merge "data/my-audio-book" --output-file="data/my-audio-book.m4b"
```

This merges all Audio-Files in folder `data/my-audio-book` into `my-audio-book.m4b`, using 
the tag-title of every file for generating chapters.

If there is a file `data/my-audio-book/cover.jpg`, it will be used as cover for the resulting m4b file.

***Note*** If you use untagged audio files, you could provide a musicbrainz id to get the correct chapter names, see command [chapter](#chapter) for more info.

### Reference
For all options, see `m4b-tool merge --help`:

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
      --no-conversion                            skip conversion (destination file uses same encoding as source - all encoding specific options will be ignored)
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

### Example:
```
m4b-tool split --audio-format mp3 --audio-bitrate 96k --audio-channels 1 --audio-samplerate 22050 "data/my-audio-book.m4b"
```

This splits the file `data/my-audio-book.m4b into` an mp3 file for each chapter, writing the files into `data/my-audio-book_splitted/`.

### Reference
For all options, see `m4b-tool split --help`:

```
Usage:
  split [options] [--] <input>

Arguments:
  input                                        Input file or folder

Options:
  -d, --debug                                  file to dump debugging info
      --debug-filename[=DEBUG-FILENAME]        file to dump debugging info [default: "m4b-tool_debug.log"]
  -f, --force                                  force overwrite of existing files
      --no-cache                               do not use cached values and clear cache completely
      --ffmpeg-threads[=FFMPEG-THREADS]        specify -threads parameter for ffmpeg [default: ""]
      --convert-charset[=CONVERT-CHARSET]      Convert from this filesystem charset to utf-8, when tagging files (e.g. Windows-1252, mainly used on Windows Systems) [default: ""]
      --ffmpeg-param[=FFMPEG-PARAM]            Add argument to every ffmpeg call, append after all other ffmpeg parameters (e.g. --ffmpeg-param="-max_muxing_queue_size" --ffmpeg-param="1000" for ffmpeg [...] -max_muxing_queue_size 1000) (multiple values allowed)
      --audio-format[=AUDIO-FORMAT]            output format, that ffmpeg will use to create files [default: "m4b"]
      --audio-channels[=AUDIO-CHANNELS]        audio channels, e.g. 1, 2 [default: ""]
      --audio-bitrate[=AUDIO-BITRATE]          audio bitrate, e.g. 64k, 128k, ... [default: ""]
      --audio-samplerate[=AUDIO-SAMPLERATE]    audio samplerate, e.g. 22050, 44100, ... [default: ""]
      --audio-codec[=AUDIO-CODEC]              audio codec, e.g. libmp3lame, aac, ... [default: ""]
      --audio-profile[=AUDIO-PROFILE]          audio profile, when using extra low bitrate - valid values (mono, stereo): aac_he, aac_he_v2  [default: ""]
      --adjust-for-ipod                        auto adjust bitrate and sampling rate for ipod, if track is to long (may lead to poor quality)
      --name[=NAME]                            provide a custom audiobook name, otherwise the existing metadata will be used [default: ""]
      --album[=ALBUM]                          provide a custom audiobook album, otherwise the existing metadata for name will be used [default: ""]
      --artist[=ARTIST]                        provide a custom audiobook artist, otherwise the existing metadata will be used [default: ""]
      --genre[=GENRE]                          provide a custom audiobook genre, otherwise the existing metadata will be used [default: ""]
      --writer[=WRITER]                        provide a custom audiobook writer, otherwise the existing metadata will be used [default: ""]
      --albumartist[=ALBUMARTIST]              provide a custom audiobook albumartist, otherwise the existing metadata will be used [default: ""]
      --year[=YEAR]                            provide a custom audiobook year, otherwise the existing metadata will be used [default: ""]
      --cover[=COVER]                          provide a custom audiobook cover, otherwise the existing metadata will be used
      --description[=DESCRIPTION]              provide a custom audiobook short description, otherwise the existing metadata will be used
      --comment[=COMMENT]                      provide a custom audiobook comment, otherwise the existing metadata will be used
      --copyright[=COPYRIGHT]                  provide a custom audiobook copyright, otherwise the existing metadata will be used
      --encoded-by[=ENCODED-BY]                provide a custom audiobook encoded-by, otherwise the existing metadata will be used
      --skip-cover                             skip extracting and embedding covers
  -o, --output-dir[=OUTPUT-DIR]                output directory [default: ""]
      --filename-template[=FILENAME-TEMPLATE]  filename twig-template for output file naming [default: "{{\"%03d\"|format(track)}}-{{title}}"]
      --use-existing-chapters-file             use an existing manually edited chapters file <audiobook-name>.chapters.txt instead of embedded chapters for splitting
  -h, --help                                   Display this help message
  -q, --quiet                                  Do not output any message
  -V, --version                                Display this application version
      --ansi                                   Force ANSI output
      --no-ansi                                Disable ANSI output
  -n, --no-interaction                         Do not ask any interactive question
  -v|vv|vvv, --verbose                         Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Help:
  Split an m4b into multiple m4b or mp3 files by chapter
```

### filename-template reference

If you would like to use a custom filename template, the [Twig](https://twig.symfony.com/) template engine is provided. The following variables are available:

```
{{encoder}}         
{{title}}           
{{artist}}          
{{genre}}
{{writer}}
{{album}}
{{disk}}
{{disks}}
{{albumArtist}}
{{year}}
{{track}}
{{tracks}}
{{cover}}
{{description}}
{{longDescription}}
{{comment}}
{{copyright}}
{{encodedBy}}
```

- You can also use some Twig specific template extensions to pad or reformat these values. The default template is `{{\"%03d\"|format(track)}}-{{title}}`, which results in filenames like `001-mychapter`
- Slashes are interpreted as directory separators, so if you use a template `{{year}}/{{artist}}/{{title}}` the resulting directory and file is `2018/Joanne K. Rowling/Harry Potter 1`
- It is not recommended to use `{{description}}` or `{{longdescription}}` for filenames but they are also provided, if the field contains other information than intended
- Special chars, that are forbidden in filenames are removed automatically

## chapter

Many m4b audiobook files do not contain valid chapters for different reasons. `m4b-tool` can handle two cases:

- Correct misplaced chapters by silence detection
- Add chapters from an internet source (mostly for well known titles)

### Misplaced chapters
In some cases there is a shift between the chapter mark and the real beginning of a chapter. `m4b-tool` could try
to correct that by detecting silences and relocating the chapter to the nearest silence:

```
m4b-tool chapters --adjust-by-silence -o "data/destination-with-adjusted-chapters.m4b" "data/source-with-misplaced-chapters.m4b"
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
m4b-tool chapters --merge-similar --first-chapter-offset 4000 --last-chapter-offset 3500 -m 8669da33-bf9c-47fe-adc9-23798a37b096 "../data/harry-potter-1.m4b"
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
m4b-tool chapter --find-misplaced-chapters 5,8  --merge-similar --first-chapter-offset 4000 --last-chapter-offset 3500 -m 8669da33-bf9c-47fe-adc9-23798a37b096 "../data/harry-potter-1.m4b"
```

Explanation:
`--find-misplaced-chapters`: Comma separated list of chapter numbers, that were not detected correctly.

Now m4b-tool will generate a ***potential chapter*** for every silence around the used chapter mark to find the right chapter position.

Listen to the audiobook again and find the right chapter position. Note them down.

#### Manually adjust misplaced chapters

Next run the full chapter detection with the --no-chapter-import option, which prevents writing the chapters directly to the file.
```
m4b-tool chapter --no-chapter-import --first-chapter-offset 4000 --last-chapter-offset 3500 -m 8669da33-bf9c-47fe-adc9-23798a37b096 "../data/harry-potter-1.m4b"
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
For all options, see `m4b-tool chapters --help`:

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

