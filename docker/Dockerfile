ARG CATTR_RUNTIME_IMAGE
ARG CATTR_BUILDER_IMAGE

FROM ${CATTR_RUNTIME_IMAGE} AS cattr_base

FROM ${CATTR_BUILDER_IMAGE} AS module_builder
USER root
COPY --from=cattr_base --chown=www:www /app /app
COPY --chown=www:www modules/ /app/modules/
COPY provider-classes.txt /tmp/provider-classes.txt
COPY assert-classes.php /tmp/assert-classes.php
WORKDIR /app
RUN php /usr/bin/composer.phar dump-autoload \
      --no-interaction --no-ansi --optimize --apcu \
      --classmap-authoritative --no-scripts \
 && php /tmp/assert-classes.php /tmp/provider-classes.txt

FROM cattr_base
COPY --from=module_builder --chown=www:www /app/modules /app/modules
COPY --from=module_builder --chown=www:www /app/vendor /app/vendor
