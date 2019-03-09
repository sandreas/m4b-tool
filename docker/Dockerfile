FROM alpine:latest

# WORKDIR /tmp/ffmpeg

# todo
# https://github.com/Ultraschall/mp4v2/archive/v4.0.0-prerelease1.tar.gz
# ./configure && make && make install

# RUN apk add --update build-base curl nasm tar bzip2 zlib-dev openssl-dev yasm-dev lame-dev libogg-dev x264-dev libvpx-dev libvorbis-dev x265-dev freetype-dev libass-dev libwebp-dev rtmpdump-dev libtheora-dev opus-dev


# https://github.com/nu774/fdkaac/archive/1.0.0.tar.gz
# autoreconf -i && ./configure && make && make install


# geht nicht
# https://github.com/mstorsjo/fdk-aac/archive/v2.0.0.tar.gz
# ./autogen.sh && ./configure && make


ARG FFMPEG_VERSION=4.1
# ARG PREFIX=/opt/ffmpeg
ARG PREFIX=/ffmpeg_build
# ARG LD_LIBRARY_PATH=/opt/ffmpeg/lib
ARG MAKEFLAGS="-j4"

# FFmpeg build dependencies.
RUN apk add --update --upgrade \
    autoconf \
    automake \
    boost-dev \
    build-base \
    bzip2 \
    ca-certificates \
    curl \
    file \
    gcc \
    git \
    libtool \
    #fdk-aac \
    #fdk-aac-dev \
    freetype \
    freetype-dev \
    lame \
    lame-dev \
    libass \
    libass-dev \
    libogg \
    libvpx \
    libogg-dev \
    libvorbis \
    libogg-dev \
    libtheora \
    libtheora-dev \
    libvpx \
    libvpx-dev \
    libvorbis \
    libvorbis-dev \
    libwebp \
    libwebp-dev \
    nasm \
    openssl \
    openssl-dev \
    opus \
    opus-dev \
    pcre \
    php7-cli \
    php7-dom \
    php7-intl \
    php7-json \
    php7-xml \
    php7-curl \
    php7-mbstring \
    php7-simplexml \
    php7-phar \
    pkgconf \
    pkgconfig \
    rtmpdump \
    rtmpdump-dev \
    tar \
    wget \
    x264-dev \
    x265-dev \
    yasm \
    yasm-dev \
    zlib-dev



RUN echo "date.timezone = UTC" >> /etc/php7/php.ini

# Get fdk-aac from testing.
RUN echo http://dl-cdn.alpinelinux.org/alpine/edge/testing >> /etc/apk/repositories && \
  apk add --update fdk-aac-dev


RUN cd /tmp/ \
   && wget https://github.com/nkari82/mp4v2/archive/master.zip \
   && unzip master.zip \
   && cd mp4v2-master \
   && ./configure && make && make install


RUN cd /tmp/ \
   && wget https://github.com/nu774/fdkaac/archive/1.0.0.tar.gz \
   && tar xzf 1.0.0.tar.gz \
   && cd fdkaac-1.0.0 \
   && autoreconf -i && ./configure && make && make install
#
# autoreconf -i && ./configure && make && make install


# Get ffmpeg source.
RUN cd /tmp/ && \
  wget http://ffmpeg.org/releases/ffmpeg-${FFMPEG_VERSION}.tar.gz && \
  tar zxf ffmpeg-${FFMPEG_VERSION}.tar.gz && rm ffmpeg-${FFMPEG_VERSION}.tar.gz



# Compile ffmpeg.
RUN cd /tmp/ffmpeg-${FFMPEG_VERSION} && \
  ./configure \
  --enable-version3 \
  --enable-gpl \
  --enable-nonfree \
  --enable-small \
  --enable-libmp3lame \
  --enable-libx264 \
  --enable-libx265 \
  --enable-libvpx \
  --enable-libtheora \
  --enable-libvorbis \
  --enable-libopus \
  --enable-libfdk_aac \
  --enable-libass \
  --enable-libwebp \
  --enable-librtmp \
  --enable-postproc \
  --enable-avresample \
  --enable-libfreetype \
  --enable-openssl \
  --disable-debug \
  --disable-doc \
  --disable-ffplay \
  --prefix="${HOME}${PREFIX}"  \
#  --pkg-config-flags="--static" \
# somethings wrong here... is home and prefix correct?
  --extra-cflags="-I${HOME}${PREFIX}/include" \
  --extra-ldflags="-L${HOME}${PREFIX}/lib" \
  --extra-libs="-lpthread -lm" \
  --bindir="/usr/local/bin/"  \
  && make && make install
  # && make distclean
  # && hash -r

RUN wget https://github.com/sandreas/m4b-tool/releases/download/v.0.3.3/m4b-tool.phar -O /usr/local/bin/m4b-tool && chmod +x /usr/local/bin/m4b-tool


# Cleanup.
RUN rm -rf /var/cache/apk/* /tmp/*

# ENTRYPOINT  ["/bin/sh", "-c"]
CMD ["/bin/sh"]
