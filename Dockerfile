FROM d2dyno/ffmpeg-docker:alpine AS ffmpeg

FROM alpine:3.9 AS build

RUN echo "---- INSTALL BUILD DEPENDENCIES ----" \
    && apk add --no-cache --update --upgrade --virtual=build-dependencies \
    autoconf \
    automake \
    boost-dev \
    build-base \
    gcc \
    git \
    tar \
    wget
    
RUN echo "---- COMPILE SANDREAS MP4V2 ----" \
  && cd /tmp/ \
  && wget https://github.com/sandreas/mp4v2/archive/master.zip \
  && unzip master.zip \
  && rm master.zip \
  && cd mp4v2-master \
  && ./configure && \
  make && \
  make install

FROM alpine:3.9

ARG M4B_TOOL_DOWNLOAD_LINK="https://github.com/sandreas/m4b-tool/releases/latest/download/m4b-tool.phar"

RUN apk add --update \
  php7-cli \
  php7-json \
  php7-mbstring \
  php7-phar \
  wget
  
RUN echo http://dl-cdn.alpinelinux.org/alpine/edge/testing >> /etc/apk/repositories && \
  apk add --update fdk-aac-dev && \
  rm -rf /var/cache/apk/* /tmp/*

RUN wget "$M4B_TOOL_DOWNLOAD_LINK" -O /usr/local/bin/m4b-tool && chmod +x /usr/local/bin/m4b-tool

RUN apk del wget

COPY --from=build /usr/local/bin/mp4* /usr/local/bin/

COPY --from=ffmpeg /app/ffmpeg /usr/local/bin

COPY --from=ffmpeg /app/ffprobe /usr/local/bin

WORKDIR ${WORKDIR}
CMD ["list"]
ENTRYPOINT ["m4b-tool"]
