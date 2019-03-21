FROM alpine:3.8 AS base

ENV WORKDIR /mnt
ARG M4B_TOOL_VERSION=0.3.3
ARG FFMPEG_VERSION=4.1
ARG PREFIX=/ffmpeg_build

RUN apk  add --no-cache --update libgcc libstdc++ ca-certificates libssl1.0 libcrypto1.0 libgomp expat git


FROM base AS build
WORKDIR     /tmp/workdir

ARG        PKG_CONFIG_PATH=/opt/ffmpeg/lib/pkgconfig
ARG        LD_LIBRARY_PATH=/opt/ffmpeg/lib
ARG        PREFIX=/opt/ffmpeg
ARG        MAKEFLAGS="-j2"

ENV         FFMPEG_VERSION=4.1     \
            FDKAAC_VERSION=0.1.5      \
            LAME_VERSION=3.99.5       \
            OGG_VERSION=1.3.2         \
            OPENCOREAMR_VERSION=0.1.5 \
            OPUS_VERSION=1.2          \
            THEORA_VERSION=1.1.1      \
            VORBIS_VERSION=1.3.5      \
            FREETYPE_VERSION=2.5.5    \
            FRIBIDI_VERSION=0.19.7    \
            FONTCONFIG_VERSION=2.12.4 \
            SRC=/usr/local

ARG         OGG_SHA256SUM="e19ee34711d7af328cb26287f4137e70630e7261b17cbe3cd41011d73a654692  libogg-1.3.2.tar.gz"
ARG         OPUS_SHA256SUM="77db45a87b51578fbc49555ef1b10926179861d854eb2613207dc79d9ec0a9a9  opus-1.2.tar.gz"
ARG         VORBIS_SHA256SUM="6efbcecdd3e5dfbf090341b485da9d176eb250d893e3eb378c428a2db38301ce  libvorbis-1.3.5.tar.gz"
ARG         THEORA_SHA256SUM="40952956c47811928d1e7922cda3bc1f427eb75680c3c37249c91e949054916b  libtheora-1.1.1.tar.gz"
ARG         FREETYPE_SHA256SUM="5d03dd76c2171a7601e9ce10551d52d4471cf92cd205948e60289251daddffa8  freetype-2.5.5.tar.gz"
ARG         FRIBIDI_SHA256SUM="3fc96fa9473bd31dcb5500bdf1aa78b337ba13eb8c301e7c28923fea982453a8  0.19.7.tar.gz"



RUN     buildDeps="autoconf \
                   automake \
                   bash \
                   binutils \
                   bzip2 \
                   cmake \
                   curl \
                   coreutils \
                   diffutils \
                   file \
                   g++ \
                   gcc \
                   gperf \
                   libtool \
                   make \
                   python \
                   openssl-dev \
                   tar \
                   yasm \
                   zlib-dev \
                   expat-dev" && \
        apk  add --no-cache --update ${buildDeps}
## opencore-amr https://sourceforge.net/projects/opencore-amr/
RUN \
        DIR=/tmp/opencore-amr && \
        mkdir -p ${DIR} && \
        cd ${DIR} && \
        curl -sL https://kent.dl.sourceforge.net/project/opencore-amr/opencore-amr/opencore-amr-${OPENCOREAMR_VERSION}.tar.gz | \
        tar -zx --strip-components=1 && \
        ./configure --prefix="${PREFIX}" --enable-shared  && \
        make && \
        make install && \
        rm -rf ${DIR}

### libogg https://www.xiph.org/ogg/
RUN \
        DIR=/tmp/ogg && \
        mkdir -p ${DIR} && \
        cd ${DIR} && \
        curl -sLO http://downloads.xiph.org/releases/ogg/libogg-${OGG_VERSION}.tar.gz && \
        echo ${OGG_SHA256SUM} | sha256sum --check && \
        tar -zx --strip-components=1 -f libogg-${OGG_VERSION}.tar.gz && \
        ./configure --prefix="${PREFIX}" --enable-shared  && \
        make && \
        make install && \
        rm -rf ${DIR}
### libopus https://www.opus-codec.org/
RUN \
        DIR=/tmp/opus && \
        mkdir -p ${DIR} && \
        cd ${DIR} && \
        curl -sLO https://archive.mozilla.org/pub/opus/opus-${OPUS_VERSION}.tar.gz && \
        echo ${OPUS_SHA256SUM} | sha256sum --check && \
        tar -zx --strip-components=1 -f opus-${OPUS_VERSION}.tar.gz && \
        autoreconf -fiv && \
        ./configure --prefix="${PREFIX}" --enable-shared && \
        make && \
        make install && \
        rm -rf ${DIR}
### libvorbis https://xiph.org/vorbis/
RUN \
        DIR=/tmp/vorbis && \
        mkdir -p ${DIR} && \
        cd ${DIR} && \
        curl -sLO http://downloads.xiph.org/releases/vorbis/libvorbis-${VORBIS_VERSION}.tar.gz && \
        echo ${VORBIS_SHA256SUM} | sha256sum --check && \
        tar -zx --strip-components=1 -f libvorbis-${VORBIS_VERSION}.tar.gz && \
        ./configure --prefix="${PREFIX}" --with-ogg="${PREFIX}" --enable-shared && \
        make && \
        make install && \
        rm -rf ${DIR}
### libtheora http://www.theora.org/
RUN \
        DIR=/tmp/theora && \
        mkdir -p ${DIR} && \
        cd ${DIR} && \
        curl -sLO http://downloads.xiph.org/releases/theora/libtheora-${THEORA_VERSION}.tar.gz && \
        echo ${THEORA_SHA256SUM} | sha256sum --check && \
        tar -zx --strip-components=1 -f libtheora-${THEORA_VERSION}.tar.gz && \
        ./configure --prefix="${PREFIX}" --with-ogg="${PREFIX}" --enable-shared && \
        make && \
        make install && \
        rm -rf ${DIR}
### libmp3lame http://lame.sourceforge.net/
RUN \
        DIR=/tmp/lame && \
        mkdir -p ${DIR} && \
        cd ${DIR} && \
        curl -sL https://kent.dl.sourceforge.net/project/lame/lame/$(echo ${LAME_VERSION} | sed -e 's/[^0-9]*\([0-9]*\)[.]\([0-9]*\)[.]\([0-9]*\)\([0-9A-Za-z-]*\)/\1.\2/')/lame-${LAME_VERSION}.tar.gz | \
        tar -zx --strip-components=1 && \
        ./configure --prefix="${PREFIX}" --bindir="${PREFIX}/bin" --enable-shared --enable-nasm --enable-pic --disable-frontend && \
        make && \
        make install && \
        rm -rf ${DIR}
### fdk-aac https://github.com/mstorsjo/fdk-aac
RUN \
        DIR=/tmp/fdk-aac && \
        mkdir -p ${DIR} && \
        cd ${DIR} && \
        curl -sL https://github.com/mstorsjo/fdk-aac/archive/v${FDKAAC_VERSION}.tar.gz | \
        tar -zx --strip-components=1 && \
        autoreconf -fiv && \
        ./configure --prefix="${PREFIX}" --enable-shared --datadir="${DIR}" && \
        make && \
        make install && \
        rm -rf ${DIR}

## freetype https://www.freetype.org/
RUN  \
        DIR=/tmp/freetype && \
        mkdir -p ${DIR} && \
        cd ${DIR} && \
        curl -sLO https://download.savannah.gnu.org/releases/freetype/freetype-${FREETYPE_VERSION}.tar.gz && \
        echo ${FREETYPE_SHA256SUM} | sha256sum --check && \
        tar -zx --strip-components=1 -f freetype-${FREETYPE_VERSION}.tar.gz && \
        ./configure --prefix="${PREFIX}" --disable-static --enable-shared && \
        make && \
        make install && \
        rm -rf ${DIR}
## fridibi https://www.fribidi.org/
# + https://github.com/fribidi/fribidi/issues/8
RUN  \
        DIR=/tmp/fribidi && \
        mkdir -p ${DIR} && \
        cd ${DIR} && \
        curl -sLO https://github.com/fribidi/fribidi/archive/${FRIBIDI_VERSION}.tar.gz && \
        echo ${FRIBIDI_SHA256SUM} | sha256sum --check && \
        tar -zx --strip-components=1 -f ${FRIBIDI_VERSION}.tar.gz && \
        sed -i 's/^SUBDIRS =.*/SUBDIRS=gen.tab charset lib/' Makefile.am && \
        ./bootstrap --no-config && \
        ./configure -prefix="${PREFIX}" --disable-static --enable-shared && \
        make -j 1 && \
        make install && \
        rm -rf ${DIR}
## fontconfig https://www.freedesktop.org/wiki/Software/fontconfig/
RUN  \
        DIR=/tmp/fontconfig && \
        mkdir -p ${DIR} && \
        cd ${DIR} && \
        curl -sLO https://www.freedesktop.org/software/fontconfig/release/fontconfig-${FONTCONFIG_VERSION}.tar.bz2 &&\
        tar -jx --strip-components=1 -f fontconfig-${FONTCONFIG_VERSION}.tar.bz2 && \
        ./configure -prefix="${PREFIX}" --disable-static --enable-shared && \
        make && \
        make install && \
        rm -rf ${DIR}


## ffmpeg https://ffmpeg.org/
RUN  \
        DIR=/tmp/ffmpeg && mkdir -p ${DIR} && cd ${DIR} && \
        curl -sLO https://ffmpeg.org/releases/ffmpeg-${FFMPEG_VERSION}.tar.bz2 && \
        tar -jx --strip-components=1 -f ffmpeg-${FFMPEG_VERSION}.tar.bz2



RUN \
        DIR=/tmp/ffmpeg && mkdir -p ${DIR} && cd ${DIR} && \
        ./configure \
        --disable-debug \
        --disable-doc \
        --disable-ffplay \
        --enable-shared \
        --enable-avresample \
        --enable-libopencore-amrnb \
        --enable-libopencore-amrwb \
        --enable-gpl \
        --enable-libfreetype \
        --enable-libmp3lame \
        --enable-libopus \
        --enable-libtheora \
        --enable-libvorbis \
        --enable-nonfree \
        --enable-openssl \
        --enable-libfdk_aac \
        --enable-libaom --extra-libs=-lpthread \
        --enable-small \
        --enable-version3 \
        --extra-cflags="-I${PREFIX}/include" \
        --extra-ldflags="-L${PREFIX}/lib" \
        --extra-libs=-ldl \
        --prefix="${PREFIX}" && \
        make && \
        make install && \
        make distclean && \
        hash -r


RUN \
    ldd ${PREFIX}/bin/ffmpeg | grep opt/ffmpeg | cut -d ' ' -f 3 | xargs -i cp {} /usr/local/lib/ && \
    cp ${PREFIX}/bin/* /usr/local/bin/ && \
    cp -r ${PREFIX}/share/ffmpeg /usr/local/share/ && \
    LD_LIBRARY_PATH=/usr/local/lib ffmpeg -buildconf


### Release Stage
FROM        base AS release
#WORKDIR /mnt
#CMD ["--help"]
#ENTRYPOINT ["m4b-tool"]

CMD         ["--help"]
ENTRYPOINT  ["ffmpeg"]

COPY --from=build /usr/local /usr/local















#RUN apk add --no-cache --update --upgrade \
#    autoconf \
#    automake \
#    boost-dev \
#    build-base \
#    bzip2 \
#    coreutils \
#    curl \
#    file \
#    gcc \
#    libtool \
#    freetype \
#    freetype-dev \
#    lame \
#    lame-dev \
#    libogg \
#    libvpx \
#    libogg-dev \
#    libvorbis \
#    libogg-dev \
#    libtheora \
#    libtheora-dev \
#    libvorbis \
#    libvorbis-dev \
#    nasm \
#    openssl \
#    openssl-dev \
#    opus \
#    opus-dev \
#    pcre \
#    php7-cli \
#    php7-dom \
#    php7-intl \
#    php7-json \
#    php7-xml \
#    php7-curl \
#    php7-mbstring \
#    php7-simplexml \
#    php7-phar \
#    pkgconf \
#    pkgconfig \
#    tar \
#    wget \
#    yasm \
#    yasm-dev \
#    zlib-dev \
#    && echo http://dl-cdn.alpinelinux.org/alpine/edge/testing >> /etc/apk/repositories \
#    && apk add --update fdk-aac-dev \
#    && echo "date.timezone = UTC" >> /etc/php7/php.ini
#
#RUN cd /tmp/ \
#  && wget http://ffmpeg.org/releases/ffmpeg-${FFMPEG_VERSION}.tar.gz \
#  && tar zxf ffmpeg-${FFMPEG_VERSION}.tar.gz \
#  && rm ffmpeg-${FFMPEG_VERSION}.tar.gz \
#  && cd /tmp/ffmpeg-${FFMPEG_VERSION} \
#  && ./configure \
#  --enable-version3 \
#  --enable-gpl \
#  --enable-nonfree \
#  --enable-small \
#  --enable-libmp3lame \
#  --enable-libtheora \
#  --enable-libvorbis \
#  --enable-libopus \
#  --enable-libfdk_aac \
#  --enable-avresample \
#  --enable-libfreetype \
#  --enable-openssl \
#  --disable-debug \
#  --disable-doc \
#  --disable-ffplay \
#  --prefix="/tmp${PREFIX}"  \
#  --extra-cflags="-I/tmp${PREFIX}/include" \
#  --extra-ldflags="-L/tmp${PREFIX}/lib" \
#  --extra-libs="-lpthread -lm" \
#  --bindir="/usr/local/bin/"  \
#  && make && make install && make distclean && hash -r && rm -rf /tmp/*
#
#RUN cd /tmp/ \
#   && wget https://github.com/nkari82/mp4v2/archive/master.zip \
#   && unzip master.zip \
#   && rm master.zip \
#   && cd mp4v2-master \
#   && ./configure && make && make install && make distclean && rm -rf /tmp/*
#
#RUN cd /tmp/ \
#   && wget https://github.com/nu774/fdkaac/archive/1.0.0.tar.gz \
#   && tar xzf 1.0.0.tar.gz \
#   && rm 1.0.0.tar.gz \
#   && cd fdkaac-1.0.0 \
#   && autoreconf -i && ./configure && make && make install && rm -rf /tmp/*
#
#RUN wget https://github.com/sandreas/m4b-tool/releases/download/v.${M4B_TOOL_VERSION}/m4b-tool.phar -O /usr/local/bin/m4b-tool \
#    && chmod +x /usr/local/bin/m4b-tool
#
#
#WORKDIR ${WORKDIR}
#CMD ["--help"]
#ENTRYPOINT ["m4b-tool"]

