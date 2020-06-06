##############################
#
#   m4b-tool build image
#
##############################
FROM alpine:3.9.2 as builder

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
FROM alpine:3.9.2
ENV WORKDIR /mnt/
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
  && echo "date.timezone = UTC" >> /etc/php7/php.ini



# copy ffmpeg static with libfdk from mwader docker image
COPY --from=mwader/static-ffmpeg:4.2.2 /ffmpeg /usr/local/bin/

# copy compiled mp4v2 binaries, libs and fdkaac encoder to runtime image
COPY --from=builder /usr/local/bin/mp4* /usr/local/bin/fdkaac /usr/local/bin/
COPY --from=builder /usr/local/lib/libmp4v2* /usr/local/lib/


ARG M4B_TOOL_DOWNLOAD_LINK="https://github.com/sandreas/m4b-tool/releases/latest/download/m4b-tool.tar.gz"

# workaround to copy a local m4b-tool.phar IF it exists
ADD ./Dockerfile ./dist/m4b-tool.phar* /tmp/
RUN echo "---- INSTALL M4B-TOOL ----" \
    && if [ ! -f /tmp/m4b-tool.phar ]; then \
            cd /tmp/ && \
            wget "${M4B_TOOL_DOWNLOAD_LINK}" && \
            if [ ! -f /tmp/m4b-tool.phar ]; then \
                tar xzf m4b-tool*.tar.gz && rm m4b-tool*.tar.gz ;\
            fi && \
            cd - ; \
       fi \
    && mv /tmp/m4b-tool.phar /usr/local/bin/m4b-tool \
    && chmod +x /usr/local/bin/m4b-tool

WORKDIR ${WORKDIR}
CMD ["list"]
ENTRYPOINT ["m4b-tool"]

