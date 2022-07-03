ARG FFMPEG_IMAGE="mwader/static-ffmpeg:5.0.1-3"
ARG FFMPEG_PATH="/ffmpeg"

# this may work for arm32 and other more exotic platforms
# see https://hub.docker.com/r/collelog/ffmpeg/tags
# ARG FFMPEG_IMAGE=collelog/ffmpeg
# ARG FFMPEG_PATH=/usr/local/bin/ffmpeg

FROM ${FFMPEG_IMAGE} as ffmpeg_binary
FROM sandreas/tone:v0.0.5 as tone_005

##############################
#
#   m4b-tool build image
#   alias m4b-tool='docker run -it --rm -u $(id -u):$(id -g) -v "$(pwd)":/mnt m4b-tool'
#   alias m4b-tool='docker run -it --entrypoint=m4b-tool-pre --rm -u $(id -u):$(id -g) -v "$(pwd)":/mnt m4b-tool'
##############################
FROM alpine:3.14 as builder

ARG MP4V2_URL="https://github.com/enzo1982/mp4v2/archive/refs/tags/v2.1.1.zip"
ARG FDK_AAC_VERSION=2.0.1
ARG FDK_AAC_URL="https://github.com/mstorsjo/fdk-aac/archive/v$FDK_AAC_VERSION.tar.gz"
ARG FDK_AAC_SHA256="a4142815d8d52d0e798212a5adea54ecf42bcd4eec8092b37a8cb615ace91dc6"

RUN echo "---- INSTALL BUILD DEPENDENCIES ----" \
    && apk add --no-cache --update --upgrade --virtual=build-dependencies \
    autoconf \
    libtool \
    automake \
    boost-dev \
    build-base \
    gcc \
    git \
    tar \
    wget \
&& echo "---- COMPILE MP4V2 ----" \
  && cd /tmp/ \
  && wget "${MP4V2_URL}" -O mp4v2.zip \
  && unzip mp4v2.zip \
  && cd mp4v2* \
  && autoreconf -fiv \
  && ./configure && \
  make -j$(nproc) && \
  make install && make distclean \
&& echo "---- PREPARE FDKAAC-DEPENDENCIES ----" \
  && cd /tmp/ \
  && wget -O fdk-aac.tar.gz "$FDK_AAC_URL" \
  && tar xfz fdk-aac.tar.gz \
  && cd fdk-aac-* && ./autogen.sh && ./configure --enable-static --disable-shared && make -j$(nproc) install \
&& echo "---- COMPILE FDKAAC ENCODER (executable binary for usage of --audio-profile) ----" \
    && cd /tmp/ \
    && wget https://github.com/nu774/fdkaac/archive/1.0.0.tar.gz \
    && tar xzf 1.0.0.tar.gz \
    && cd fdkaac-1.0.0 \
    && autoreconf -i && ./configure --enable-static --disable-shared && make -j$(nproc) && make install && rm -rf /tmp/* \
&& echo "---- REMOVE BUILD DEPENDENCIES (to keep image small) ----" \
    && apk del --purge build-dependencies && rm -rf /tmp/*

##############################
#
#   m4b-tool development image
#
##############################
FROM alpine:3.14
ENV WORKDIR /mnt/
ENV M4BTOOL_TMP_DIR /tmp/m4b-tool/


RUN echo "---- INSTALL RUNTIME PACKAGES ----" && \
  apk add --no-cache --update --upgrade \
  # mp4v2: required libraries
  libstdc++ \
  # m4b-tool: php cli, required extensions and php settings
  php8-cli \
  php8-dom \
  php8-json \
  php8-xml \
  php8-mbstring \
  php8-phar \
  php8-tokenizer \
  php8-xmlwriter \
  php8-openssl \
  && echo "date.timezone = UTC" >> /etc/php8/php.ini \
  && ln -s /usr/bin/php8 /bin/php



# copy ffmpeg static with libfdk from configured docker image
COPY --from=ffmpeg_binary "$FFMPEG_PATH" /usr/local/bin/
COPY --from=tone_005 "/usr/local/bin/tone" /usr/local/bin/

# copy compiled mp4v2 binaries, libs and fdkaac encoder to runtime image
COPY --from=builder /usr/local/bin/mp4* /usr/local/bin/fdkaac /usr/local/bin/
COPY --from=builder /usr/local/lib/libmp4v2* /usr/local/lib/


ARG M4B_TOOL_DOWNLOAD_LINK="https://github.com/sandreas/m4b-tool/releases/latest/download/m4b-tool.tar.gz"

# workaround to copy a local m4b-tool.phar IF it exists
ADD ./Dockerfile ./dist/m4b-tool.phar* /tmp/
RUN echo "---- INSTALL M4B-TOOL ----" \
    && if [ ! -f /tmp/m4b-tool.phar ]; then \
            echo "!!! DOWNLOADING ${M4B_TOOL_DOWNLOAD_LINK} !!!" && wget "${M4B_TOOL_DOWNLOAD_LINK}" -O /tmp/m4b-tool.tar.gz && \
            if [ ! -f /tmp/m4b-tool.phar ]; then \
                tar xzf /tmp/m4b-tool.tar.gz -C /tmp/ && rm /tmp/m4b-tool.tar.gz ;\
            fi \
       fi \
    && mv /tmp/m4b-tool.phar /usr/local/bin/m4b-tool \
    && M4B_TOOL_PRE_RELEASE_LINK=$(wget -q -O - https://github.com/sandreas/m4b-tool/releases/tag/latest | grep -o 'M4B_TOOL_DOWNLOAD_LINK=[^ ]*' | head -1 | cut -d '=' -f 2) \
    && echo "!!! DOWNLOADING PRE_RELEASE ${M4B_TOOL_DOWNLOAD_LINK} !!!" && wget "${M4B_TOOL_PRE_RELEASE_LINK}" -O /tmp/m4b-tool.tar.gz \
    && tar xzf /tmp/m4b-tool.tar.gz -C /tmp/ && rm /tmp/m4b-tool.tar.gz \
    && mv /tmp/m4b-tool.phar /usr/local/bin/m4b-tool-pre \
    && chmod +x /usr/local/bin/m4b-tool /usr/local/bin/m4b-tool-pre

WORKDIR ${WORKDIR}
CMD ["list"]
ENTRYPOINT ["m4b-tool"]

