FROM alpine:3.9.2

ENV WORKDIR /mnt
ARG M4B_TOOL_DOWNLOAD_LINK="https://github.com/sandreas/m4b-tool/releases/latest/download/m4b-tool.phar"
ARG FFMPEG_VERSION=4.1
ARG PREFIX=/ffmpeg_build

RUN \
echo "---- INSTALL BUILD DEPENDENCIES ----" \
    && apk add --no-cache --update --upgrade --virtual=build-dependencies \
    autoconf \
    automake \
    boost-dev \
    build-base \
    gcc \
    lame-dev \
    libogg-dev \
    yasm \
    nasm \
    yasm-dev \
    zlib-dev \
    freetype-dev \
    libogg-dev \
    libtheora-dev \
    libvorbis-dev \
    openssl-dev \
    opus-dev \
    git \
    tar \
    wget && \
echo "---- INSTALL RUNTIME PACKAGES ----" \
    && apk add --no-cache --update --upgrade bzip2 \
    ca-certificates \
    coreutils \
    curl \
    file \
    libtool \
    freetype \
    lame \
    libogg \
    libvpx \
    libvorbis \
    libtheora \
    libvorbis \
    openssl \
    opus \
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
    && echo http://dl-cdn.alpinelinux.org/alpine/edge/testing >> /etc/apk/repositories \
    && apk add --update fdk-aac-dev \
    && echo "date.timezone = UTC" >> /etc/php7/php.ini && \
echo "---- COMPILE FFMPEG ----" \
    && cd /tmp/ \
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
    --enable-libtheora \
    --enable-libvorbis \
    --enable-libopus \
    --enable-libfdk_aac \
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
    && make && make install && make distclean && hash -r && rm -rf /tmp/* && \
echo "---- COMPILE SANDREAS MP4V2 ----" \
    && cd /tmp/ \
    && wget https://github.com/sandreas/mp4v2/archive/master.zip \
    && unzip master.zip \
    && rm master.zip \
    && cd mp4v2-master \
    && ./configure && make && make install && make distclean && rm -rf /tmp/* && \
echo "---- COMPILE FDKAAC ----" \
    && cd /tmp/ \
    && wget https://github.com/nu774/fdkaac/archive/1.0.0.tar.gz \
    && tar xzf 1.0.0.tar.gz \
    && rm 1.0.0.tar.gz \
    && cd fdkaac-1.0.0 \
    && autoreconf -i && ./configure && make && make install && rm -rf /tmp/* && \
echo "---- REMOVE BUILD DEPENDENCIES ----" \
    && apk del --purge build-dependencies

# workaround to copy a local m4b-tool.phar IF it exists
ADD ./Dockerfile ./dist/m4b-tool.phar* /tmp/
RUN echo "---- INSTALL M4B-TOOL ----" \
    && if [ ! -f /tmp/m4b-tool.phar ]; then wget "${M4B_TOOL_DOWNLOAD_LINK}" -O /tmp/m4b-tool.phar ; fi \
    && mv /tmp/m4b-tool.phar /usr/local/bin/m4b-tool \
    && chmod +x /usr/local/bin/m4b-tool

WORKDIR ${WORKDIR}
CMD ["list"]
ENTRYPOINT ["m4b-tool"]

