FROM alpine:latest

ARG M4B_TOOL_VERSION=0.3.3
ARG FFMPEG_VERSION=4.1
ARG PREFIX=/ffmpeg_build

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
    zlib-dev \
    && echo http://dl-cdn.alpinelinux.org/alpine/edge/testing >> /etc/apk/repositories \
    && apk add --update fdk-aac-dev \
    && rm -rf /var/cache/apk/* \
    && echo "date.timezone = UTC" >> /etc/php7/php.ini

RUN cd /tmp/ \
  && wget http://ffmpeg.org/releases/ffmpeg-${FFMPEG_VERSION}.tar.gz \
  && tar zxf ffmpeg-${FFMPEG_VERSION}.tar.gz \
  && rm ffmpeg-${FFMPEG_VERSION}.tar.gz \
  && cd /tmp/ffmpeg-${FFMPEG_VERSION} \
  && ./configure \
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
  --prefix="/tmp${PREFIX}"  \
  --extra-cflags="-I/tmp${PREFIX}/include" \
  --extra-ldflags="-L/tmp${PREFIX}/lib" \
  --extra-libs="-lpthread -lm" \
  --bindir="/usr/local/bin/"  \
  && make && make install && make distclean && hash -r && rm -rf /tmp/*

RUN cd /tmp/ \
   && wget https://github.com/nkari82/mp4v2/archive/master.zip \
   && unzip master.zip \
   && cd mp4v2-master \
   && ./configure && make && make install && make distclean && rm -rf /tmp/*

RUN cd /tmp/ \
   && wget https://github.com/nu774/fdkaac/archive/1.0.0.tar.gz \
   && tar xzf 1.0.0.tar.gz \
   && cd fdkaac-1.0.0 \
   && autoreconf -i && ./configure && make && make install && rm -rf /tmp/*

RUN wget https://github.com/sandreas/m4b-tool/releases/download/v.${M4B_TOOL_VERSION}/m4b-tool.phar -O /usr/local/bin/m4b-tool && chmod +x /usr/local/bin/m4b-tool

CMD ["/bin/sh"]
