# Safe Production Build and Deployment

A custom Cattr module requires a derived image because the application and its
Composer autoloader are baked into the upstream image. This is not a fork of
Cattr: the upstream image remains the base layer, while the independently
released module is added during a repeatable image build.

Do not copy module files into a running container. They disappear on recreation,
and the Composer classmap will not reliably include them.

## Inputs to record

Before building, record:

- exact upstream Cattr image digest;
- exact module release tag and artifact checksum, or reviewed branch commit;
- candidate image tag;
- rollback image tag;
- Compose file location;
- environment-file location;
- backup directory.

Never use `latest` as the only production rollback reference.

## Pre-deployment safeguards

Inspect the current container and mounts:

```bash
docker inspect cattr_app --format \
  'image={{.Config.Image}} image_id={{.Image}} status={{.State.Status}}'
docker inspect cattr_app --format \
  '{{range .Mounts}}{{println .Type .Source "->" .Destination}}{{end}}'
```

Tag the exact running image:

```bash
docker image tag CURRENT_IMAGE_ID cattr-local:rollback-before-MODULE-DATE
docker image inspect cattr-local:rollback-before-MODULE-DATE --format '{{.Id}}'
```

Back up the Compose file and database before a migration. Use a dated,
permission-restricted directory and verify the dump is non-empty and ends with a
completion marker. Never paste database passwords into documentation or shell
history.

## Derived image pattern

Use Cattr's compatible development image to regenerate the complete application
autoload map, then copy the module and generated `vendor` directory into the
unchanged runtime image. Pin both images by digest in a long-lived build file.

The following pattern assumes the verified release ZIP has already been
downloaded into the build context as `module.zip`:

```dockerfile
ARG CATTR_RUNTIME_IMAGE
ARG CATTR_BUILDER_IMAGE

FROM ${CATTR_RUNTIME_IMAGE} AS cattr_base

FROM ${CATTR_BUILDER_IMAGE} AS module_builder
USER root
COPY --from=cattr_base --chown=www:www /app /app
RUN install -d -o www -g www /app/modules/ExampleModule
COPY --chown=www:www module.zip /tmp/module.zip
RUN unzip -q /tmp/module.zip -d /app/modules/ExampleModule \
 && rm /tmp/module.zip
WORKDIR /app
RUN php /usr/bin/composer.phar dump-autoload \
      --no-interaction --no-ansi --optimize --apcu \
      --classmap-authoritative --no-scripts \
 && php -r 'require "/app/vendor/autoload.php"; exit(class_exists("Modules\\ExampleModule\\Providers\\ModuleServiceProvider") ? 0 : 1);' \
 && test -f /app/modules/ExampleModule/module.json

FROM cattr_base
COPY --from=module_builder --chown=www:www \
  /app/modules/ExampleModule /app/modules/ExampleModule
COPY --from=module_builder --chown=www:www /app/vendor /app/vendor
```

Verify the release checksum outside the Docker build before building. Avoid
installing Composer in the runtime image. Cattr's runtime may expose PHP as
`/usr/bin/php82`, while its official builder uses `php` and includes Composer.

## Candidate verification

Before changing Compose, run the candidate without its normal entrypoint:

```bash
docker run --rm --entrypoint /usr/bin/php82 CANDIDATE_IMAGE \
  -r 'require "/app/vendor/autoload.php"; echo class_exists("Modules\\ExampleModule\\Providers\\ModuleServiceProvider") ? "OK\n" : "MISSING\n";'
```

Then validate module discovery with side effects disabled:

```bash
docker run --rm --entrypoint /usr/bin/php82 \
  --env-file /path/to/module.env CANDIDATE_IMAGE \
  /app/artisan module:list
```

The module must appear as enabled. Fix every PSR-4 warning before deployment.

## Compose configuration

Use an untracked root-owned environment file:

```dotenv
EXAMPLE_MODULE_ENABLED=false
EXAMPLE_MODULE_TOKEN=replace-with-the-real-server-side-secret
```

Set mode `0600`. In Compose, pin the candidate image and attach the environment
file to the `app` service:

```yaml
services:
  app:
    image: cattr-local:example-module-1.0.0
    env_file:
      - ./example-module.env
```

Run `docker compose config --quiet` and inspect a redacted diff before applying
it.

## Two-stage rollout

First recreate only the app with the integration disabled:

```bash
cd /path/to/cattr-compose
docker compose up -d --no-deps --force-recreate app
```

Verify:

- container is running with the expected image ID and restart count zero;
- startup logs complete normally;
- migrations succeed;
- `/status` returns HTTP 200;
- `artisan module:list` shows the module enabled;
- the module's audit table or equivalent is initially empty;
- no external side-effect log entries exist.

Then change only the module enable flag to `true`, recreate only `app`, and
repeat the same checks. A real functional test must use the actual supported
Cattr action and an approved employee/destination. Do not substitute a direct
webhook call when testing whether the Cattr event integration works.

## Rollback

Restore the prior image in Compose and recreate only `app`:

```bash
docker compose up -d --no-deps --force-recreate app
```

Do not reverse or delete a database migration automatically. A new unused module
table can normally remain during an application rollback. Restore the database
only when the migration changed existing data and the recovery plan explicitly
requires it.

After rollback, verify the image ID, startup logs, `/status`, and normal desktop
clock-in behavior.
