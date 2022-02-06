##############################
#
#   m4b-tool build image
#   alias m4b-tool='docker run -it --rm -u $(id -u):$(id -g) -v "$(pwd)":/mnt m4b-tool'
#   alias m4b-tool='docker run -it --entrypoint=m4b-tool-pre --rm -u $(id -u):$(id -g) -v "$(pwd)":/mnt m4b-tool'
##############################
FROM alpine:3.14 as builder

ARG FDK_AAC_VERSION=2.0.1
ARG FDK_AAC_URL="https://github.com/mstorsjo/fdk-aac/archive/v$FDK_AAC_VERSION.tar.gz"
ARG FDK_AAC_SHA256=a4142815d8d52d0e798212a5adea54ecf42bcd4eec8092b37a8cb615ace91dc6

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
&& echo "---- COMPILE SANDREAS MP4V2 (for sort-title, sort-album and sort-author) ----" \
  && cd /tmp/ \
  && wget https://github.com/sandreas/mp4v2/archive/master.zip \
  && unzip master.zip \
  && cd mp4v2-master \
  && ./configure && \
  make -j4 && \
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
    && autoreconf -i && ./configure --enable-static --disable-shared && make -j4 && make install && rm -rf /tmp/* \
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


# copy ffmpeg static with libfdk from mwader docker image
COPY --from=mwader/static-ffmpeg:4.4.0 /ffmpeg /usr/local/bin/

# copy compiled mp4v2 binaries, libs and fdkaac encoder to runtime image
COPY --from=builder /usr/local/bin/mp4* /usr/local/bin/fdkaac /usr/local/bin/
COPY --from=builder /usr/local/lib/libmp4v2* /usr/local/lib/


ARG M4B_TOOL_DOWNLOAD_LINK="https://github.com/sandreas/m4b-tool/releases/latest/download/m4b-tool.tar.gz"

# workaround to copy a local m4b-tool.phar IF it exists
ADD ./Dockerfile ./dist/m4b-tool.phar* /tmp/
RUN echo "---- INSTALL M4B-TOOL ----" \
    && if [ ! -f /tmp/m4b-tool.phar ]; then \
            wget "${M4B_TOOL_DOWNLOAD_LINK}" -O /tmp/m4b-tool.tar.gz && \
            if [ ! -f /tmp/m4b-tool.phar ]; then \
                tar xzf /tmp/m4b-tool.tar.gz -C /tmp/ && rm /tmp/m4b-tool.tar.gz ;\
            fi \
       fi \
    && mv /tmp/m4b-tool.phar /usr/local/bin/m4b-tool \
    && M4B_TOOL_PRE_RELEASE_LINK=$(wget -q -O - https://github.com/sandreas/m4b-tool/releases/tag/latest | grep -o 'M4B_TOOL_DOWNLOAD_LINK=[^ ]*' | head -1 | cut -d '=' -f 2) \
    && wget "${M4B_TOOL_PRE_RELEASE_LINK}" -O /tmp/m4b-tool.tar.gz \
    && tar xzf /tmp/m4b-tool.tar.gz -C /tmp/ && rm /tmp/m4b-tool.tar.gz \
    && mv /tmp/m4b-tool.phar /usr/local/bin/m4b-tool-pre \
    && chmod +x /usr/local/bin/m4b-tool /usr/local/bin/m4b-tool-pre

WORKDIR ${WORKDIR}
CMD ["list"]
ENTRYPOINT ["m4b-tool"]

