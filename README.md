# m4b-tool
`m4b-tool` is a is a wrapper for `ffmpeg` and `mp4v2` to merge, split or and manipulate audiobook files with chapters. Although `m4b-tool` is designed to handle m4b files, nearly all audio formats should be supported, e.g. mp3, aac, ogg, alac and flac.

## Support me via GitHub sponsors

If you are using any of my projects and find them helpful, please consider [donating to support me](https://github.com/sponsors/sandreas). I plan to use the money to support other open source projects or charitable purposes. Thank you!

### Current monthly sponsors  `>= 25.00$`

Special thanks to all sponsors donating a monthly amount of `>= 25.00$`.

| Name                                  | Amount |
|---------------------------------------|--------|
| [numinit](https://github.com/numinit) | 25.00$ |

### Become a sponsor
<p>
<a href="https://github.com/sponsors/sandreas"><img src="./assets/help.svg" width="200" alt="sponsor me and donate" style="margin:auto;"></a>
</p>

## Usage with Nix

Get [Nix](https://nixos.org/download.html) and ensure that [Flakes](https://nixos.wiki/wiki/Flakes#Permanent) are enabled.

- Running: `nix run github:sandreas/m4b-tool` or `nix run github:sandreas/m4b-tool#m4b-tool-libfdk`
    - The latter will build FFMpeg using libfdk_aac, which will take longer.
- Building: `nix build github:sandreas/m4b-tool` or `nix build github:sandreas/m4b-tool#m4b-tool-libfdk`
    - Wrapper script is located at `./result/bin/m4b-tool`
- Developing: Clone and `nix develop`
    - When done updating dependencies, run `composer2nix --executable --composition=composer.nix` to update the .nix files

## Announcement

I started an experiment, that now has reached an early alpha level and can be tried out. The command line tool is written in `C#`, fully open source and is called `tone`. It already has a pretty decent feature set, so if you would like to try it, here it is:

https://github.com/sandreas/tone

This announcement does NOT mean, that `m4b-tool` is deprecated or will be soon. The development of `m4b-tool` will go on (at least until tone has a feature-set similar to `m4b-tool`). It's just to have an alternative tool for features, that may have limitations.

Have fun, can't wait to get your feedback.


## ❗❗❗Important Note❗❗❗
Unfortunately I am pretty busy at the moment, so `m4b-tool 0.4.2` is very old. Since it is not planned to release a
newer version without having complete documentation, there is
only [the latest pre-release](https://github.com/sandreas/m4b-tool/releases/tag/latest) getting bug fixes. It is already
pretty stable, so if you are experiencing bugs with `v0.4.2`, please try the latest pre-release, if it has been already
fixed there.

Thank you, sandreas

https://pilabor.com

## Features

- `merge` a set of audio files (e.g. MP3 or AAC) into a single m4b file
- `split` a single m4b file into several output files by chapters or a `flac` encoded album into single tracks via cue sheet
- Add or adjust `chapters` for an existing m4b file via silence detection or musicbrainz

## TL;DR - examples for the most common tasks

### Merge multiple files
`merge` all audio files in directory `data/my-audio-book` into file `data/merged.m4b` (tags are retained and `data/my-audio-book/cover.jpg`  and `data/my-audio-book/description.txt` are embedded, if available)
```
m4b-tool merge "data/my-audio-book/" --output-file="data/merged.m4b"
```
### Split one file by chapters
`split` one big m4b file by chapters into multiple mp3 files at `data/my-audio-book_splitted/` (tags are retained, `data/my-audio-book_splitted/cover.jpg` is created, if m4b contains a cover)
```
m4b-tool split --audio-format mp3 --audio-bitrate 96k --audio-channels 1 --audio-samplerate 22050 "data/my-audio-book.m4b"
```

### Chapters adjustment of a file via silence detection
`chapters` can try to adjust existing chapters of an m4b by silence detection
```
m4b-tool chapters --adjust-by-silence -o "data/destination-with-adjusted-chapters.m4b" "data/source-with-misplaced-chapters.m4b"
```

## Best practices

Since the most used subcommand of `m4b-tool` seems to be `merge`, lets talk about best practice...

### Step 0 - Take a look at the docker image
Unfortunately `m4b-tool` has many dependencies. Not only one-liners, if you would like to get the best quality and tagging support, many dependencies have to be compiled manually with extra options. Thats why you should take a look at the [docker image](#docker), which comes with all the bells and whistles of top audio quality, top tagging support and easy installation and has almost no disadvantages.

> Note: If you are on windows, it might be difficult to make it work

### Step 1 - Organizing your audiobooks in directories
When merging audiobooks, you should prepare them - the following directory structure helps a lot, even if you only merge one single audiobook:

`input/<main genre>/<author>/<title>`

or if it is a series

`input/<main genre>/<author>/<series>/<series-part> - <title>`

Examples:
```
input/Fantasy/J.K. Rowling/Quidditch Through the Ages/
input/Fantasy/J.K. Rowling/Harry Potter/1 - Harry Potter and the Philosopher's Stone/
```
> Note: If your audiobook title contains invalid path characters like `/`, just replace them with a dash `-`.

### Step 2 - add cover and a description
Now, because you almost always want a cover and a description for your audiobook, you should add the following files in the main directory:

- `cover.jpg`
- `description.txt` (Be sure to use `UTF-8` text file encoding for the contents)

Examples:
```
input/Fantasy/J.K. Rowling/Quidditch Through the Ages/cover.jpg
input/Fantasy/J.K. Rowling/Quidditch Through the Ages/description.txt
```

> Note: `m4b-tool` will find and embed these files automatically but does not fail, if they are not present

### Step 3 - chapters

Chapters are nice to add *waypoints* for your audiobook. They help to remember the last position and improve the
experience in general.

#### fixed chapters
If you would like to adjust chapters manually, you can add a `chapters.txt` (same location as `cover.jpg`) with following contents (`<chapter-start>` `<chapter-title>`):
```
00:00:00.000 Intro
00:04:19.153 This is
00:09:24.078 A way to add
00:14:34.500 Chapters manually
```

#### by tag
If your input files are tagged, these tags will be used to create the chapter metadata by its `title`. So if you tag your input files with valid chapter names as track `title`, this will result in a nice and clean `m4b`-file with valid chapter names.

#### by length
Another great feature since `m4b-tool` *v.0.4.0* is the `--max-chapter-length` parameter. Often the individual input files are too big which results in chapters with a very long duration. This can be annoying, if you would like to jump to a certain point, since you have to rewind or fast-forward and hold the button for a long time, instead of just tipping previous or next a few times. To automatically add sub-chapters, you could provide:

`--max-chapter-length=300,900`

This will cause `m4b-tool`
- Trying to preserve original chapters as long as they are not longer than 15 minutes (900 seconds)
- If a track is longer than 15 minutes
    - Perform a silence detection and try to add sub-chapters at every silence between 5 minutes (300 seconds) and 15 minutes (900 seconds)
    - If no silence is detected, add a hard cut sub-chapter every 5 minutes

Sub-chapters are named like the original and get an additional index. This is a nice way to keep the real names but not having chapters with a too long duration.


### Step 4 (optional) - for iPod owners

If you own an iPod, there might be a problem with too long audiobooks, since iPods only support 32bit sampling rates. If your audiobook is longer than 27 hours with 22050Hz sampling rate, you could provide `--adjust-for-ipod`, to automatically downsample your audiobook, which results in lower quality, but at least its working on your good old iPod...

### Step 5 (optional) - more cpu cores, faster conversion

`m4b-tool` supports multiple conversion tasks in parallel with the `--jobs` parameter (e.g. `--jobs=2`). If you have to convert more than one file, which is the common case, you nearly double the merge speed by providing the `--jobs=2` parameter (or quadruplicate with `--jobs=4`, if you have a quad core system, etc.). Don't provide a number higher than the number of cores on your system - this will slow down the merge...

> Note: If you run the conversion on all your cores, it will result in almost 100% CPU usage, which may lead to slower system performance

### Step 6 - Use the `--batch-pattern` feature

In `m4b-tool v.0.4.0` the `--batch-pattern` feature was added. It can be used to batch-convert multiple audiobooks at
once, but also to just convert one single audiobook - because you can create tags from an existing directory structure.

> Hint: The `output-file` parameter has to be a directory, when using `--batch-pattern`.

Even multiple `--batch-pattern` parameters are supported, while the first match will be used first. So if you created the directory structure as described above, the final command to merge `input/Fantasy/Harry Potter/1 - Harry Potter and the Philosopher's Stone/` to `output/Fantasy/Harry Potter/1 - Harry Potter and the Philosopher's Stone.m4b` would look like this:

```
m4b-tool merge -v --jobs=2 --output-file="output/" --max-chapter-length=300,900 --adjust-for-ipod --batch-pattern="input/%g/%a/%s/%p - %n/"  --batch-pattern="input/%g/%a/%n/" "input/"
```

>In `--batch-pattern` mode, existing files are skipped by default

### Result
If you performed the above steps with the docker image or installed and compiled all dependencies, you should get the following result:

- Top quality audio by using `libfdk_aac` encoder
- Series and single audiobooks have valid tags for `genre`, `author`, `title`, `sorttitle`, etc. from `--batch-pattern` usage
- If the files `cover.jpg` and `description.txt` exist in the main directories, a `cover`, a `description` and a `longdesc` are embedded
- If you tagged the input files, real chapter names should appear in your player
- No more chapters longer than 15 minutes
- Working iPod versions for audiobooks longer than 27 hours


## Installation


### Docker

To use docker with `m4b-tool`, you first have to
- `pull` the official docker image (recommended)
- or `build` the `Dockerfile` in the main directory


#### Official image

The *official* docker images are available on [DockerHub](https://hub.docker.com/repository/docker/sandreas/m4b-tool/tags?page=1&ordering=name). They are somewhat experimental, but have proven to work well. The `latest` tag is considered as *way to go* with the bleeding edge features and fixes. Every now and then a dated tag is published (e.g. `sandreas/m4b-tool:2022-09-25`), that is considered as *pretty* stable, to ensure a broken `latest` image will not break your whole setup.

```
# pull the image
docker pull sandreas/m4b-tool:latest

# create an alias for m4b-tool running docker
alias m4b-tool='docker run -it --rm -u $(id -u):$(id -g) -v "$(pwd)":/mnt sandreas/m4b-tool:latest'

# testing the command
m4b-tool --version
```

> Note: If you use the alias above, keep in mind that you cannot use absolute paths (e.g. `/tmp/data/audiobooks/harry potter 1`) or symlinks. You must change into the directory and use relative paths (e.g. `cd /tmp/data && m4b-tool merge "audiobooks/harry potter 1" --output-file harry.m4b`)

#### Build manually or use a specific release version

To manually build a docker container for a specific `m4b-tool` release, it is required to provide an extra parameter for downloading a specific version into the image, e.g. for `v.0.4.1`:

```
# clone m4b-tool repository
git clone https://github.com/sandreas/m4b-tool.git

# change directory
cd m4b-tool

# build docker image - this will take a while
docker build . -t m4b-tool

# create an alias for m4b-tool running docker
alias m4b-tool='docker run -it --rm -u $(id -u):$(id -g) -v "$(pwd)":/mnt m4b-tool'

# testing the command
m4b-tool --version

# use the specific pre-release from 2022-07-16
docker build . --build-arg M4B_TOOL_DOWNLOAD_LINK=https://github.com/sandreas/m4b-tool/files/9125095/m4b-tool.tar.gz -t m4b-tool
```

> Note: You could also just edit the according variable in the `Dockerfile`.

#### Dockerize a custom build, that is not available via download link
Developers or experts might want to run a complete custom build of `m4b-tool` or build the code themselves (e.g. if you forked the repository and applied some patches). If that is the case, you can store the custom build to `dist/m4b-tool.phar` relative to the `Dockerfile` and then do a default build.

```
# dist/m4b-tool.phar is available
docker build . -t m4b-tool
```

After this the custom build should be integrated into the docker image.

### MacOS

On MacOS you may use the awesome package manager `brew` to install `m4b-tool`.

#### Recommended: High audio quality, sort tagging

Getting best audio quality requires some additional effort. You have to *recompile* `ffmpeg` with the non-free `libfdk_aac` codec. This requires uninstalling the default `ffmpeg` package if installed, since `brew` dropped the possibility for *extra options*. There is no official `ffmpeg-with-options` repository, but a pretty decent `tap`, that you could use to save time.

```
# FIRST INSTALL ONLY: if not already done, remove existing ffmpeg with default audio quality options
# check for ffmpeg with libfdk and uninstall if libfdk is not already available
[ -x "$(which ffmpeg)" ] && (ffmpeg -hide_banner -codecs 2>&1 | grep libfdk || brew uninstall ffmpeg)

# tap required repositories
brew tap sandreas/tap
brew tap homebrew-ffmpeg/ffmpeg

# check available ffmpeg options and which you would like to use
brew options homebrew-ffmpeg/ffmpeg/ffmpeg

# install ffmpeg with at least libfdk_aac for best audio quality
brew install homebrew-ffmpeg/ffmpeg/ffmpeg --with-fdk-aac

# install m4b-tool
brew install sandreas/tap/m4b-tool

# check installed m4b-tool version
m4b-tool --version
```

#### Stick to defaults (acceptable audio quality, no sort tagging)

If the above did not work for you or you would just to checkout `m4b-tool` before using it in production, you might want
to try the *quick and easy* way. It will work, but you get lower audio quality and there is **no support for sort
tagging**.

```
# tap m4b-tool repository
brew tap sandreas/tap

# install dependencies
brew install ffmpeg fdk-aac-encoder mp4v2

# install m4b-tool with acceptable audio quality and no sort tagging
brew install --ignore-dependencies sandreas/tap/m4b-tool
```

### Ubuntu

```
# install all dependencies
sudo apt install ffmpeg mp4v2-utils fdkaac php-cli php-intl php-json php-mbstring php-xml

# install / upgrade m4b-tool
sudo wget https://github.com/sandreas/m4b-tool/releases/download/v.0.4.2/m4b-tool.phar -O /usr/local/bin/m4b-tool && sudo chmod +x /usr/local/bin/m4b-tool

# check installed m4b-tool version
m4b-tool --version
```

> Note: If you would like to get the [best possible audio quality](#about-audio-quality), you have to compile `ffmpeg` with the high quality encoder `fdk-aac` (`--enable-libfdk_aac`) - see https://trac.ffmpeg.org/wiki/CompilationGuide/Ubuntu for a step-by-step guide to compile `ffmpeg`.


### Manual installation (only recommended on Windows systems)

`m4b-tool` is written in `PHP` and uses `ffmpeg`, `mp4v2` and optionally `fdkaac` for high efficiency codecs to perform conversions. Therefore you will need the following tools in your %PATH%:

- `php` >= 7.1 with `mbstring` extension enabled (https://php.net)
- `ffmpeg` (https://www.ffmpeg.org)
- `mp4v2` (`mp4chaps`, `mp4art`, etc. https://github.com/sandreas/m4b-tool/releases/download/v0.2/mp4v2-windows.zip)
- `fdkaac` (optional, only if you need high efficiency for low bitrates <= 32k, http://wlc.io/2015/06/20/fdk-aac/ - caution: not official!)

To check the dependencies, running following commands via command line should show similar output:

```
$ php -v
Copyright (c) 1997-2018 The PHP Group [...]

$ ffmpeg -version
ffmpeg version 4.1.1 Copyright (c) 2000-2019 the FFmpeg developers [...]

$ mp4chaps --version
mp4chaps - MP4v2 2.0.0

$ fdkaac
fdkaac 1.0.0 [...]

```

If you are sure, all dependencies are installed, the next step is to download the latest release of `m4b-tool` from

https://github.com/sandreas/m4b-tool/releases

Depending on the operating system, you can rename `m4b-tool.phar` to `m4b-tool` and run `m4b-tool --version` directly from the command line. If you are not sure, you can always use the command `php m4b-tool.phar --version` to check if the installation was successful. This should work on every system.

If you would like to use the latest source code with all new features and fixes, you could also [build from source](#building-from-source). The current build might be in  unstable and should only be used for testing purposes or if you need a specific feature that has not been released.


### Custom `mp4v2` for accurate sorting order

Most audiobooks are not released in alphabetical order. A prominent example is Harry Potter. So if you have all the Harry Potter audiobooks, it depends on your player, but probably they are not listed in the correct order... let's see, what the alphabetical order would be:

- Harry Potter and the Chamber of Secrets (Part 2)
- Harry Potter and the Philosopher's Stone (Part 1)
- Harry Potter and the Prisoner of Azkaban (Part 3)

And the correct order would have been:

- Harry Potter and the Philosopher's Stone (Part 1)
- Harry Potter and the Chamber of Secrets (Part 2)
- Harry Potter and the Prisoner of Azkaban (Part 3)

Well, there is a solution for this. You have to tag the audiobook with a custom `sortname` and / or `sortalbum`. If your
player supports these tags, the order is now correct, even when the title is still the original title. To achieve this,
i had to build a custom version of `mp4v2` (more accurate `mp4tags`), to add options for these tags and add the pseudo
tags `--series` and `--series-part`.

So if you do the following:

```
m4b-tool merge --name="Harry Potter and the Chamber of Secrets" --series="Harry Potter" --series-part="2" --output-file="output/Harry Potter and the Chamber of Secrets.m4b" "input/Harry Potter and the Chamber of Secrets"
```

It would result in:
- Name: `Harry Potter and the Chamber of Secrets`
- Sortname: `Harry Potter 2 - Harry Potter and the Chamber of Secrets`

#### Install custom `mp4v2`

> In the docker image, the custom version is already installed

```
git clone https://github.com/sandreas/mp4v2
cd mp4v2
./configure
make && sudo make install
```

## About audio quality

In `m4b-tool` all audio conversions are performed with `ffmpeg` resulting in pretty descent audio quality using its free encoders. However, best quality takes some extra effort, so if you are using the free encoders, `m4b-tool` might show the following hint:

> Your ffmpeg version cannot produce top quality aac using encoder aac instead of libfdk_aac

That's not really a problem, because the difference between the `aac` and `libfdk_aac` encoder is hardly noticeable in
most cases. But to overcome the hint and get the best audio quality possible, you have to use a non-free encoder, that
is not integrated in `ffmpeg` by default (licensing reasons). Depending on the operating system you are using,
installing the non-free encoder may require a little extra skills, effort and time (see the notes for your operating
system above). You have to decide, if it is worth the additional effort for getting the slightly better quality. If you
are using the docker image, you should get the best quality by default.

If you are using very low bitrates (<= 32k), you could also use high efficiency profiles to further improve audio
quality (e.g. `--audio-profile=aac_he` for mono). Unfortunately, `ffmpeg`'s high efficiency implementation produces
audio files, that are incompatible with many players (including iTunes). To produce high efficiency files, that are
compatible with at least most common players, you will need to install `fdkaac` for now.

More Details:
- https://github.com/sandreas/m4b-tool/issues/19
- https://trac.ffmpeg.org/wiki/Encode/AAC
- https://trac.ffmpeg.org/wiki/Encode/HighQualityAudio



# Submitting issues

You think there is an issue with `m4b-tool`? First take a look at the [Known Issues](#known-issues) below. If this does not help, please provide the following information when adding an issue:

- the operating system you use
- the exact command, that you tried, e.g. `m4b-tool merge my-audio-book/ --output-file merged.m4b`
- the error message, that occured or the circumstances, e.g. `the resulting file merged.m4b is only 5kb`
- other relevant information, e.g. sample files if needed

> Example:
```
Title: m4b-tool does not embed covers

If i run m4b-tool with a folder containing a cover.png, it does not embed the cover and shows an error message.

OS: Ubuntu 16.04 LTS
Command: `m4b-tool merge my-audio-book/ ---output-file merged.m4b`
Error: Cannot embed cover, cover is not a valid image file

Attached files: cover.png
```

## Known issues

If you are getting PHP Exceptions, it is a configuration issue with PHP in most cases. If are not familiar with PHP
configuration, you could follow these instructions, to fix a few known issues:

### Exception Charset not supported

```
[Exception]
  charset windows-1252 is not supported - use one of these instead: utf-8
```

This mostly happens on windows, because the `mbstring`-Extension is used to internally convert charsets, so that special
chars like german umlauts are supported on every platform. To fix this, you need to enable the mbstring-extension:

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


# m4b-tool commands

The following list contains all possible commands including [`merge`](#merge), [`split`](#split) and [`chapters`](#chapters) accompanied by the reference of parameters available in every command.

## merge

With `m4b-tool` you can merge a set of audio files to one single m4b audiobook file.

### Example:
```
m4b-tool merge "data/my-audio-book" --output-file="data/my-audio-book.m4b"
```

This merges all Audio-Files in folder `data/my-audio-book` into `my-audio-book.m4b`, using the tag-title of every file
for generating chapters.

If there is a file `data/my-audio-book/cover.jpg`, it will be used as cover for the resulting m4b file.

> Note: If you use untagged audio files, you could provide a musicbrainz id to get the correct chapter names, see command [chapter](#chapters) for more info.

### Reference
For all options, see `m4b-tool merge --help`:

```
Description:
  Merges a set of files to one single file

Usage:
  merge [options] [--] <input> [<more-input-files>...]

Arguments:
  input                                          Input file or folder
  more-input-files                               Other Input files or folders

Options:
      --logfile[=LOGFILE]                        file to log all output [default: ""]
      --debug                                    enable debug mode - sets verbosity to debug, logfile to m4b-tool.log and temporary encoded files are not deleted
  -f, --force                                    force overwrite of existing files
      --no-cache                                 clear cache completely before doing anything
      --ffmpeg-threads[=FFMPEG-THREADS]          specify -threads parameter for ffmpeg - you should also consider --jobs when merge is used [default: ""]
      --platform-charset[=PLATFORM-CHARSET]      Convert from this filesystem charset to utf-8, when tagging files (e.g. Windows-1252, mainly used on Windows Systems) [default: ""]
      --ffmpeg-param[=FFMPEG-PARAM]              Add argument to every ffmpeg call, append after all other ffmpeg parameters (e.g. --ffmpeg-param="-max_muxing_queue_size" --ffmpeg-param="1000" for ffmpeg [...] -max_muxing_queue_size 1000) (multiple values allowed)
  -a, --silence-min-length[=SILENCE-MIN-LENGTH]  silence minimum length in milliseconds [default: 1750]
  -b, --silence-max-length[=SILENCE-MAX-LENGTH]  silence maximum length in milliseconds [default: 0]
      --max-chapter-length[=MAX-CHAPTER-LENGTH]  maximum chapter length in seconds - its also possible to provide a desired chapter length in form of 300,900 where 300 is desired and 900 is max - if the max chapter length is exceeded, the chapter is placed on the first silence between desired and max chapter length [default: "0"]
      --name[=NAME]                              custom name, otherwise the existing metadata will be used
      --sortname[=SORTNAME]                      custom sortname, that is used only for sorting
      --album[=ALBUM]                            custom album, otherwise the existing metadata for name will be used
      --sortalbum[=SORTALBUM]                    custom sortalbum, that is used only for sorting
      --artist[=ARTIST]                          custom artist, otherwise the existing metadata will be used
      --sortartist[=SORTARTIST]                  custom sortartist, that is used only for sorting
      --genre[=GENRE]                            custom genre, otherwise the existing metadata will be used
      --writer[=WRITER]                          custom writer, otherwise the existing metadata will be used
      --albumartist[=ALBUMARTIST]                custom albumartist, otherwise the existing metadata will be used
      --year[=YEAR]                              custom year, otherwise the existing metadata will be used
      --description[=DESCRIPTION]                custom short description, otherwise the existing metadata will be used
      --longdesc[=LONGDESC]                      custom long description, otherwise the existing metadata will be used
      --comment[=COMMENT]                        custom comment, otherwise the existing metadata will be used
      --copyright[=COPYRIGHT]                    custom copyright, otherwise the existing metadata will be used
      --encoded-by[=ENCODED-BY]                  custom encoded-by, otherwise the existing metadata will be used
      --cover[=COVER]                            custom cover, otherwise the existing metadata will be used
      --skip-cover                               skip extracting and embedding covers
      --series[=SERIES]                          custom series, this pseudo tag will be used to auto create sort order (e.g. Harry Potter or The Kingkiller Chronicles)
      --series-part[=SERIES-PART]                custom series part, this pseudo tag will be used to auto create sort order (e.g. 1 or 2.5)
      --audio-format[=AUDIO-FORMAT]              output format, that ffmpeg will use to create files [default: "m4b"]
      --audio-channels[=AUDIO-CHANNELS]          audio channels, e.g. 1, 2 [default: ""]
      --audio-bitrate[=AUDIO-BITRATE]            audio bitrate, e.g. 64k, 128k, ... [default: ""]
      --audio-samplerate[=AUDIO-SAMPLERATE]      audio samplerate, e.g. 22050, 44100, ... [default: ""]
      --audio-codec[=AUDIO-CODEC]                audio codec, e.g. libmp3lame, aac, ... [default: ""]
      --audio-profile[=AUDIO-PROFILE]            audio profile, when using extra low bitrate - valid values: aac_he, aac_he_v2 [default: ""]
      --adjust-for-ipod                          auto adjust bitrate and sampling rate for ipod, if track is too long (may result in low audio quality)
      --fix-mime-type                            try to fix MIME-type (e.g. from video/mp4 to audio/mp4) - this is needed for some players to prevent an empty video window
  -o, --output-file=OUTPUT-FILE                  output file
      --include-extensions[=INCLUDE-EXTENSIONS]  comma separated list of file extensions to include (others are skipped) [default: "aac,alac,flac,m4a,m4b,mp3,oga,ogg,wav,wma,mp4"]
  -m, --musicbrainz-id=MUSICBRAINZ-ID            musicbrainz id so load chapters from
      --no-conversion                            skip conversion (destination file uses same encoding as source - all encoding specific options will be ignored)
      --batch-pattern[=BATCH-PATTERN]            multiple batch patterns that can be used to merge all audio books in a directory matching the given patterns (e.g. %a/%t for author/title) - parameter --output-file must be a directory (multiple values allowed)
      --dry-run                                  perform a dry run without converting all the files in batch mode (requires --batch-pattern)
      --jobs[=JOBS]                              Specifies the number of jobs (commands) to run simultaneously [default: 1]
      --use-filenames-as-chapters                Use filenames for chapter titles instead of tag contents
      --no-chapter-reindexing                    Do not perform any reindexing for index-only chapter names (by default m4b-tool will try to detect index-only chapters like Chapter 1, Chapter 2 and reindex it with its numbers only)
  -h, --help                                     Display this help message
  -q, --quiet                                    Do not output any message
  -V, --version                                  Display this application version
      --ansi                                     Force ANSI output
      --no-ansi                                  Disable ANSI output
  -n, --no-interaction                           Do not ask any interactive question
  -v|vv|vvv, --verbose                           Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
```


### Placeholder reference for `--batch-pattern`

If you use the `--batch-pattern` parameter, the following placeholders are supported

- `title` / `name`: `%n`
- `sort_name`: `%N`
- `album`: `%m`,
- `sort_album`: `%M`,
- `artist`: `%a`,
- `sort_artist`: `%A`,
- `genre`: `%g`,
- `writer`: `%w`,
- `album_artist`: `%t`,
- `year`: `%y`,
- `description`: `%d`,
- `long_description`: `%D`,
- `comment`: `%c`,
- `copyright`: `%C`,
- `encoded_by`: `%e`,
- `group(ing)`: `%G`,
- `purchase_date`: `%U`,
- `series`: `%s`,
- `series_part`: `%p`,

## split

`m4b-tool` can be used to split a single `m4b` into a file per chapter or a `flac` encoded album into single tracks via cue sheet.

### Example:
```
m4b-tool split --audio-format mp3 --audio-bitrate 96k --audio-channels 1 --audio-samplerate 22050 "data/my-audio-book.m4b"
```

This splits the file `data/my-audio-book.m4b into` an mp3 file for each chapter, writing the files into `data/my-audio-book_splitted/`.


### Cue sheet splitting (experimental)
If you would like to split a `flac` file containing multiple tracks, a cue sheet with the exact filename of the `flac` is
required (`my-album.flac` requires `my-album.cue`):

```
# my-album.cue is automatically found and used for splitting
m4b-tool split --audio-format=mp3 --audio-bitrate=192k --audio-channels=2 --audio-samplerate=48000 "data/my-album.flac"
```


### Reference
For all options, see `m4b-tool split --help`:

```
Description:
  Splits an m4b file into parts

Usage:
  split [options] [--] <input>

Arguments:
  input                                          Input file or folder

Options:
      --logfile[=LOGFILE]                        file to dump all output [default: ""]
      --debug                                    enable debug mode - sets verbosity to debug, logfile to m4b-tool.log and temporary files are not deleted
  -f, --force                                    force overwrite of existing files
      --no-cache                                 do not use cached values and clear cache completely
      --ffmpeg-threads[=FFMPEG-THREADS]          specify -threads parameter for ffmpeg [default: ""]
      --platform-charset[=PLATFORM-CHARSET]      Convert from this filesystem charset to utf-8, when tagging files (e.g. Windows-1252, mainly used on Windows Systems) [default: ""]
      --ffmpeg-param[=FFMPEG-PARAM]              Add argument to every ffmpeg call, append after all other ffmpeg parameters (e.g. --ffmpeg-param="-max_muxing_queue_size" --ffmpeg-param="1000" for ffmpeg [...] -max_muxing_queue_size 1000) (multiple values allowed)
  -a, --silence-min-length[=SILENCE-MIN-LENGTH]  silence minimum length in milliseconds [default: 1750]
  -b, --silence-max-length[=SILENCE-MAX-LENGTH]  silence maximum length in milliseconds [default: 0]
      --max-chapter-length[=MAX-CHAPTER-LENGTH]  maximum chapter length in seconds - its also possible to provide a desired chapter length in form of 300,900 where 300 is desired and 900 is max - if the max chapter length is exceeded, the chapter is placed on the first silence between desired and max chapter length [default: "0"]
      --audio-format[=AUDIO-FORMAT]              output format, that ffmpeg will use to create files [default: "m4b"]
      --audio-channels[=AUDIO-CHANNELS]          audio channels, e.g. 1, 2 [default: ""]
      --audio-bitrate[=AUDIO-BITRATE]            audio bitrate, e.g. 64k, 128k, ... [default: ""]
      --audio-samplerate[=AUDIO-SAMPLERATE]      audio samplerate, e.g. 22050, 44100, ... [default: ""]
      --audio-codec[=AUDIO-CODEC]                audio codec, e.g. libmp3lame, aac, ... [default: ""]
      --audio-profile[=AUDIO-PROFILE]            audio profile, when using extra low bitrate - valid values (mono, stereo): aac_he, aac_he_v2  [default: ""]
      --adjust-for-ipod                          auto adjust bitrate and sampling rate for ipod, if track is to long (may lead to poor quality)
      --name[=NAME]                              provide a custom audiobook name, otherwise the existing metadata will be used [default: ""]
      --sortname[=SORTNAME]                      provide a custom audiobook name, that is used only for sorting purposes [default: ""]
      --album[=ALBUM]                            provide a custom audiobook album, otherwise the existing metadata for name will be used [default: ""]
      --sortalbum[=SORTALBUM]                    provide a custom audiobook album, that is used only for sorting purposes [default: ""]
      --artist[=ARTIST]                          provide a custom audiobook artist, otherwise the existing metadata will be used [default: ""]
      --sortartist[=SORTARTIST]                  provide a custom audiobook artist, that is used only for sorting purposes [default: ""]
      --genre[=GENRE]                            provide a custom audiobook genre, otherwise the existing metadata will be used [default: ""]
      --writer[=WRITER]                          provide a custom audiobook writer, otherwise the existing metadata will be used [default: ""]
      --albumartist[=ALBUMARTIST]                provide a custom audiobook albumartist, otherwise the existing metadata will be used [default: ""]
      --year[=YEAR]                              provide a custom audiobook year, otherwise the existing metadata will be used [default: ""]
      --cover[=COVER]                            provide a custom audiobook cover, otherwise the existing metadata will be used
      --description[=DESCRIPTION]                provide a custom audiobook short description, otherwise the existing metadata will be used
      --longdesc[=LONGDESC]                      provide a custom audiobook long description, otherwise the existing metadata will be used
      --comment[=COMMENT]                        provide a custom audiobook comment, otherwise the existing metadata will be used
      --copyright[=COPYRIGHT]                    provide a custom audiobook copyright, otherwise the existing metadata will be used
      --encoded-by[=ENCODED-BY]                  provide a custom audiobook encoded-by, otherwise the existing metadata will be used
      --series[=SERIES]                          provide a custom audiobook series, this pseudo tag will be used to auto create sort order (e.g. Harry Potter or The Kingkiller Chronicles)
      --series-part[=SERIES-PART]                provide a custom audiobook series part, this pseudo tag will be used to auto create sort order (e.g. 1 or 2.5)
      --skip-cover                               skip extracting and embedding covers
      --fix-mime-type                            try to fix MIME-type (e.g. from video/mp4 to audio/mp4) - this is needed for some players to prevent video window
  -o, --output-dir[=OUTPUT-DIR]                  output directory [default: ""]
  -p, --filename-template[=FILENAME-TEMPLATE]    filename twig-template for output file naming [default: "{{\"%03d\"|format(track)}}-{{title|raw}}"]
      --use-existing-chapters-file               use an existing manually edited chapters file <audiobook-name>.chapters.txt instead of embedded chapters for splitting
  -h, --help                                     Display this help message
  -q, --quiet                                    Do not output any message
  -V, --version                                  Display this application version
      --ansi                                     Force ANSI output
      --no-ansi                                  Disable ANSI output
  -n, --no-interaction                           Do not ask any interactive question
  -v|vv|vvv, --verbose                           Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

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

## chapters

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

## Too long chapters

Sometimes you have a file that contains valid chapters, but they are too long, so you would like to split them into
sub-chapters. This is tricky, because the `chapters` command relays only on metadata and not on track length - so it
won't work. BUT: There might be a workaround. In the latest pre-release since July 2020 you can do the following:

- Put the source file into an empty directory, e.g. `input/my-file.m4b` (this is important, don't skip this step!)
- Run `m4b-tool merge -v --no-conversion --max-chapter-length=300,900 "input/" -o "output/my-rechaptered-file.m4b"`

Because of `--no-conversion` the chaptering process is lossless, but it takes the existing chapters as input and
recalculates it based on the `--max-chapter-length` parameter and a new silence detection.

### No chapters at all

If you have a well known audiobook, like ***Harry Potter and the Philosopher’s Stone***, you might be lucky that it is
on musicbrainz.

In this case `m4b-tool` can try to correct the chapter information using silence detection and the musicbrainz data.

Since this is not a trivial task and prone to error, `m4b-tool` offers some parameters to correct misplaced chapter
positions manually.

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

Now listen to the audiobook an go through the chapters. Lets assume, all but 2 chapters were detected correctly. The two
misplaced chapters are chapter number 6 and 9.

To find the real position of chapters 6 and 9 invoke:

```
m4b-tool chapter --find-misplaced-chapters 5,8  --merge-similar --first-chapter-offset 4000 --last-chapter-offset 3500 -m 8669da33-bf9c-47fe-adc9-23798a37b096 "../data/harry-potter-1.m4b"
```

Explanation:
`--find-misplaced-chapters`: Comma separated list of chapter numbers, that were not detected correctly.

Now `m4b-tool` will generate a ***potential chapter*** for every silence around the used chapter mark to find the right chapter position.

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

- The silence parts of this audiobook are too short for detection. To adjust the minimum silence length,
  use `--silence-min-length 1000` setting the silence length to 1 second.
    - Caution: To low values can lead to misplaced chapters and increased detection time.
- You provided the wrong MBID
- There is too much background noise in this specific audiobook, so that silences cannot be detected



#### Reference
For all options, see `m4b-tool chapters --help`:

```
Description:
  Adds chapters to m4b file

Usage:
  chapters [options] [--] <input>

Arguments:
  input                                                      Input file or folder

Options:
      --logfile[=LOGFILE]                                    file to dump all output [default: ""]
      --debug                                                enable debug mode - sets verbosity to debug, logfile to m4b-tool.log and temporary files are not deleted
  -f, --force                                                force overwrite of existing files
      --no-cache                                             do not use cached values and clear cache completely
      --ffmpeg-threads[=FFMPEG-THREADS]                      specify -threads parameter for ffmpeg [default: ""]
      --platform-charset[=PLATFORM-CHARSET]                  Convert from this filesystem charset to utf-8, when tagging files (e.g. Windows-1252, mainly used on Windows Systems) [default: ""]
      --ffmpeg-param[=FFMPEG-PARAM]                          Add argument to every ffmpeg call, append after all other ffmpeg parameters (e.g. --ffmpeg-param="-max_muxing_queue_size" --ffmpeg-param="1000" for ffmpeg [...] -max_muxing_queue_size 1000) (multiple values allowed)
  -a, --silence-min-length[=SILENCE-MIN-LENGTH]              silence minimum length in milliseconds [default: 1750]
  -b, --silence-max-length[=SILENCE-MAX-LENGTH]              silence maximum length in milliseconds [default: 0]
      --max-chapter-length[=MAX-CHAPTER-LENGTH]              maximum chapter length in seconds - its also possible to provide a desired chapter length in form of 300,900 where 300 is desired and 900 is max - if the max chapter length is exceeded, the chapter is placed on the first silence between desired and max chapter length [default: "0"]
  -m, --musicbrainz-id=MUSICBRAINZ-ID                        musicbrainz id so load chapters from
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

# Latest release

`m4b-tool` is a one-man-project, so sometimes it evolves quickly and often nothing happens. If you have reported an
issue and it is marked as fixed, there might be no **stable** release for a long time. That's why now there is
a `latest` tag in combination with a **Pre-Release** for testing purposes. These releases always contain the most recent
builds with all available fixes and new features. Mostly untested, there may be new bugs, non-functional features or -
pretty unlikely - critical issues with the risk of data loss. Feedback is always welcome, but don't expect that these
are fixed quickly.

To get the Pre-Release, go to https://github.com/sandreas/m4b-tool/releases/tag/latest and download
the `m4b-tool.tar.gz` or if using docker rebuild the image with:
```
docker build . --build-arg M4B_TOOL_DOWNLOAD_LINK=<link-to-pre-release> -t m4b-tool
```

# Building from source

`m4b-tool` contains a `build` script, which will create an executable m4b-tool.phar in the dist folder. Composer for PHP
is required, so after installing composer, run following commands in project root folder:

## Linux / Unix

### Install Dependencies (Ubuntu)

```shell
sudo apt install ffmpeg mp4v2-utils fdkaac php-cli composer phpunit php-mbstring
```

### Build

```
composer install
./build
```

## macOS

### Install Dependencies (brew)

```shell
brew update
brew install php@7.4 phpunit
brew link php@7.4
```

### Build

```
composer install
./build
```

## Windows
```
composer install
build
```


## ![#f03c15](https://via.placeholder.com/15/f03c15/000000?text=+) Request for help - especially german users
Right now, I'm experimenting with speech recognition and *speech to text* using [this project](https://github.com/gooofy/zamia-speech)

This is for a feature to automatically add chapter names by speech recognition. I'm not sure this will be ever working as expected, but right now I'm pretty confident, it is possible to do the following, if there are enough speech samples in a specific language:

- Extract chapter names and first sentences of a chapter from an ebook
- Detect all silences in the audiobook
- Perform a speech to text for the first 30 seconds after the silence
- Compare it with the text parts of the ebook, mark the chapter positions and add real chapters names


To do that and improve the german speech recognition, I would really appreciate *YOUR* help on:

**https://voice.mozilla.org/de (german)**

> No account is needed to help


You can support mozilla DeepSpeech to better support german speech recognition by just verifying sentences after listening or, even more important, reading out loud and uploading sentences. I try to add a few ones every day, its really easy and quite fun. At the moment the german speech recognition is not good enough for the algorithm, but I will check out every now and then - as soon the recognition is working good enough, I'll go on with this feature.
