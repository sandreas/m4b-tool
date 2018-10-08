FROM ubuntu:18.04

# install base dependencies
RUN apt-get update
RUN apt-get -y install wget mp4v2-utils php-cli


# compile ffmpeg with nonfree
RUN apt-get -y install autoconf automake build-essential cmake git-core libass-dev libfreetype6-dev libsdl2-dev libtool libva-dev libvdpau-dev libvorbis-dev libxcb1-dev libxcb-shm0-dev libxcb-xfixes0-dev pkg-config texinfo wget zlib1g-dev
RUN mkdir -p ~/ffmpeg_sources ~/bin
RUN cd ~/ffmpeg_sources && wget https://www.nasm.us/pub/nasm/releasebuilds/2.13.03/nasm-2.13.03.tar.bz2 && tar xjvf nasm-2.13.03.tar.bz2 && cd nasm-2.13.03 && ./autogen.sh && PATH="$HOME/bin:$PATH" ./configure --prefix="$HOME/ffmpeg_build" --bindir="$HOME/bin" && make && make install
RUN apt-get -y install yasm libx264-dev libx265-dev libnuma-dev libvpx-dev libfdk-aac-dev libmp3lame-dev libopus-dev
RUN cd ~/ffmpeg_sources && git -C aom pull 2> /dev/null || git clone --depth 1 https://aomedia.googlesource.com/aom && mkdir aom_build && cd aom_build && PATH="$HOME/bin:$PATH" cmake -G "Unix Makefiles" -DCMAKE_INSTALL_PREFIX="$HOME/ffmpeg_build" -DENABLE_SHARED=off -DENABLE_NASM=on ../aom && PATH="$HOME/bin:$PATH" make && make install
RUN cd ~/ffmpeg_sources && wget -O ffmpeg-snapshot.tar.bz2 https://ffmpeg.org/releases/ffmpeg-snapshot.tar.bz2 && tar xjvf ffmpeg-snapshot.tar.bz2 && cd ffmpeg && PATH="$HOME/bin:$PATH" PKG_CONFIG_PATH="$HOME/ffmpeg_build/lib/pkgconfig" ./configure --prefix="$HOME/ffmpeg_build" --pkg-config-flags="--static" --extra-cflags="-I$HOME/ffmpeg_build/include" --extra-ldflags="-L$HOME/ffmpeg_build/lib" --extra-libs="-lpthread -lm" --bindir="$HOME/bin" --enable-gpl --enable-libaom --enable-libass --enable-libfdk-aac --enable-libfreetype --enable-libmp3lame --enable-libopus --enable-libvorbis --enable-libvpx --enable-libx264 --enable-libx265 --enable-nonfree && PATH="$HOME/bin:$PATH" make && make install && hash -r
RUN cp ~/bin/* /usr/local/bin/

# install m4b-tool and multi-convert script
RUN wget -O /usr/local/bin/m4b-tool https://github.com/sandreas/m4b-tool/releases/download/v.0.3/m4b-tool.phar && chmod +x /usr/local/bin/m4b-tool
RUN wget -O /usr/local/bin/multi-convert https://raw.githubusercontent.com/sandreas/m4b-tool/master/multi-convert && chmod +x /usr/local/bin/multi-convert

CMD ["/bin/bash"]
