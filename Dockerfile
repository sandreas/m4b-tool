##############################
#
#   m4b-tool build image
#   alias m4b-tool='docker run -it --rm -u $(id -u):$(id -g) -v "$(pwd)":/mnt m4b-tool'
#   alias m4b-tool='docker run -it --entrypoint=m4b-tool-pre --rm -u $(id -u):$(id -g) -v "$(pwd)":/mnt m4b-tool'
#
##############################
FROM alpine:3.12 AS base

RUN echo "---- INSTALL BUILD DEPENDENCIES ----" && \
  buildDeps="autoconf \
  automake \
  boost-dev \
  build-base \
  libtool \
  tar \
  unzip \
  wget" && \
  apk add --no-cache ${buildDeps}

FROM base AS builder

WORKDIR /tmp/workdir

# Compile Sandreas mp4v2
RUN echo "---- COMPILE SANDREAS MP4V2 (for sort-title, sort-album and sort-author) ----" && \
  wget https://github.com/sandreas/mp4v2/archive/master.zip && \
  unzip master.zip && \
  cd mp4v2-master && \
  ./configure && \
  make -j4 && \
  make install && make distclean

ARG FDK_AAC_VERSION=2.0.1
ARG FDK_AAC_URL="https://github.com/mstorsjo/fdk-aac/archive/v$FDK_AAC_VERSION.tar.gz"

# Compile fdkaac
RUN echo "---- PREPARE FDKAAC-DEPENDENCIES ----" && \
  wget -O fdk-aac.tar.gz "$FDK_AAC_URL" && \
  tar xfz fdk-aac.tar.gz && \
  cd fdk-aac-* && ./autogen.sh && ./configure --enable-static --disable-shared && \
  make -j$(nproc) install && \
  echo "---- COMPILE FDKAAC ENCODER (executable binary for usage of --audio-profile) ----" && \
  cd /tmp/workdir && \
  wget https://github.com/nu774/fdkaac/archive/1.0.0.tar.gz && \
  tar xzf 1.0.0.tar.gz && \
  cd fdkaac-1.0.0 && \
  autoreconf -i && ./configure --enable-static --disable-shared && \
  make -j4 && make install

##############################
#
#   m4b-tool development image
#
##############################
FROM alpine:3.12
WORKDIR /mnt/
ENV M4BTOOL_TMP_DIR /tmp/m4b-tool/

RUN echo "---- INSTALL RUNTIME PACKAGES ----" && \
  apk add --no-cache --update --upgrade \
  # mp4v2: required libraries
  libstdc++ \
  # m4b-tool: php cli, required extensions and php settings
  php7-cli \
  php7-dom \
  php7-json \
  php7-xml \
  php7-mbstring \
  php7-phar \
  php7-tokenizer \
  php7-xmlwriter \
  php7-openssl && \
  echo "date.timezone = UTC" >> /etc/php7/php.ini

# copy ffmpeg static with libfdk from mwader docker image
COPY --from=mwader/static-ffmpeg:4.4 /ffmpeg /usr/local/bin/

# copy compiled mp4v2 binaries, libs and fdkaac encoder to runtime image
COPY --from=builder /usr/local/bin/mp4* /usr/local/bin/fdkaac /usr/local/bin/
COPY --from=builder /usr/local/lib/libmp4v2* /usr/local/lib/

ARG M4B_TOOL_DOWNLOAD_LINK="https://github.com/sandreas/m4b-tool/releases/latest/download/m4b-tool.tar.gz"

# workaround to copy a local m4b-tool.phar IF it exists
ADD ./Dockerfile ./dist/m4b-tool.phar* /tmp/

RUN echo "---- INSTALL M4B-TOOL ----" && \
  if [ ! -f /tmp/m4b-tool.phar ]; then \
    wget "${M4B_TOOL_DOWNLOAD_LINK}" -O /tmp/m4b-tool.tar.gz && \
    if [ ! -f /tmp/m4b-tool.phar ]; then \
        tar xzf /tmp/m4b-tool.tar.gz -C /tmp/ && rm /tmp/m4b-tool.tar.gz ;\
    fi \
  fi &&\
  mv /tmp/m4b-tool.phar /usr/local/bin/m4b-tool && \
  M4B_TOOL_PRE_RELEASE_LINK="$(wget -q -O - https://github.com/sandreas/m4b-tool/releases/tag/latest | grep -o 'M4B_TOOL_DOWNLOAD_LINK=[^ ]*' | head -1 | cut -d '=' -f 2)" && \
  wget "${M4B_TOOL_PRE_RELEASE_LINK}" -O /tmp/m4b-tool.tar.gz && \
  tar xzf /tmp/m4b-tool.tar.gz -C /tmp/ && rm /tmp/m4b-tool.tar.gz && \
  mv /tmp/m4b-tool.phar /usr/local/bin/m4b-tool-pre && \
  chmod +x /usr/local/bin/m4b-tool /usr/local/bin/m4b-tool-pre

CMD ["list"]
ENTRYPOINT ["m4b-tool"]