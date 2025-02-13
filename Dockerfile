FROM sandreas/ffmpeg:5.0.1-3 as ffmpeg
FROM sandreas/tone:v0.2.5 as tone
FROM sandreas/mp4v2:2.1.1 as mp4v2
FROM sandreas/fdkaac:2.0.1 as fdkaac
FROM alpine:3.20.3
ENV WORKDIR /mnt/
ENV M4BTOOL_TMP_DIR /tmp/m4b-tool/


RUN echo "---- INSTALL RUNTIME PACKAGES ----" && \
  apk add --no-cache --update --upgrade \
  # mp4v2: required libraries
  libstdc++ \
  # m4b-tool: php cli, required extensions and php settings
  php83-cli \
  php83-curl \
  php83-dom \
  php83-xml \
  php83-mbstring \
  php83-openssl \
  php83-phar \
  php83-simplexml \
  php83-tokenizer \
  php83-xmlwriter \
  php83-zip \
  && echo "date.timezone = UTC" >> /etc/php83/php.ini \
  && ln -s /usr/bin/php83 /bin/php



COPY --from=ffmpeg /usr/local/bin/ffmpeg /usr/local/bin/
COPY --from=tone /usr/local/bin/tone /usr/local/bin/
COPY --from=mp4v2 /usr/local/bin/mp4* /usr/local/bin/
COPY --from=mp4v2 /usr/local/lib/libmp4v2* /usr/local/lib/
COPY --from=fdkaac /usr/local/bin/fdkaac /usr/local/bin/

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
    && chmod +x /usr/local/bin/m4b-tool

WORKDIR ${WORKDIR}
CMD ["list"]
ENTRYPOINT ["m4b-tool"]
