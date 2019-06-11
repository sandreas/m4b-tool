FROM d2dyno/ffmpeg-docker AS ffmpeg

FROM ubuntu:bionic

ARG M4B_TOOL_DOWNLOAD_LINK="https://github.com/sandreas/m4b-tool/releases/latest/download/m4b-tool.phar"

RUN apt-get update && \
  apt-get install -y \
  mp4v2-utils \
  php-cli \
  php7.2-common \
  php7.2-mbstring \
  wget && \
  rm -rf /var/lib/apt/lists/*

RUN wget "$M4B_TOOL_DOWNLOAD_LINK" -O /usr/local/bin/m4b-tool && chmod +x /usr/local/bin/m4b-tool

RUN apt-get remove -y wget

COPY --from=ffmpeg /app/ffmpeg /usr/bin
COPY --from=ffmpeg /app/ffprobe /usr/bin

WORKDIR ${WORKDIR}
CMD ["list"]
ENTRYPOINT ["m4b-tool"]
