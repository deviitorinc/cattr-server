# Cattr Custom Module Deployer

This orphan branch contains a small production deployment toolkit for building
one Cattr image with every registered custom module.

It does not modify Cattr's `main` branch and does not store production secrets.

## What it automates

`bin/deploy` performs the following sequence:

1. validates the host, configuration, module locks, environment-file
   permissions, disk space, Docker, Cattr, and database;
2. resolves every module source and verifies its pinned commit or release
   checksum;
3. tags the current application image for rollback;
4. creates permission-restricted Compose and MySQL backups with checksums;
5. builds a derived Cattr image containing all registered custom modules;
6. verifies Composer class loading and Cattr module discovery;
7. recreates only the Cattr app with all module side effects forced off;
8. checks startup, migrations, restart count, HTTP health, logs, and all expected
   modules;
9. asks before restoring the enable values from the protected server
   environment files;
10. checks the application again and writes a deployment report.

`bin/rollback` restores the prior image/override and checks Cattr health. It does
not reverse database migrations automatically.

## Files

```text
bin/deploy                         main deployment command
bin/activate                       finish a paused disabled-stage deployment
bin/rollback                       restore the preceding deployment
bin/verify                         read-only production checks
config/production.conf.example    host-specific configuration template
modules/*.conf                    locked custom-module registry
docker/Dockerfile                 reusable multi-module image build
docker/assert-classes.php         build-time autoload assertion
lib/common.sh                     shared guarded operations
```

## Initial server setup

Clone the exact tooling branch and copy the example host configuration outside
Git:

```bash
git clone --single-branch --branch 'tools/module-deployer' \
  https://github.com/deviitorinc/cattr-server.git \
  /root/apps/com.deviitor.cattr/module-deployer
install -m 600 \
  /root/apps/com.deviitor.cattr/module-deployer/config/production.conf.example \
  /root/apps/com.deviitor.cattr/module-deployer.conf
```

Edit `/root/apps/com.deviitor.cattr/module-deployer.conf` and pin the clean
upstream runtime image. The builder image is already pinned in the example.

Keep module secrets only in each module's existing root-owned environment file.
The deployer checks for mode `0600` or stricter and never prints file contents.

## Deploy

Interactive deployment:

```bash
cd /root/apps/com.deviitor.cattr/module-deployer
./bin/deploy --config /root/apps/com.deviitor.cattr/module-deployer.conf
```

After reviewing the disabled-stage checks, answer `yes` to activate the values
already present in the protected module environment files.

For attended automation where activation has already been approved:

```bash
./bin/deploy \
  --config /root/apps/com.deviitor.cattr/module-deployer.conf \
  --yes
```

The `--yes` option does not bypass backups, source verification, disabled-stage
deployment, or health checks.

## Verify and rollback

```bash
./bin/verify --config /root/apps/com.deviitor.cattr/module-deployer.conf
./bin/rollback --config /root/apps/com.deviitor.cattr/module-deployer.conf
```

If an interactive deployment was intentionally left disabled, activate it later
without rebuilding or taking another backup:

```bash
./bin/activate --config /root/apps/com.deviitor.cattr/module-deployer.conf
```

Rollback asks for confirmation unless `--yes` is supplied. Database migrations
remain in place because deleting or reversing production data is a separate,
explicit recovery decision.

## Add another module

Copy `modules/clock-in-webhook.conf`, change every field, and pin either:

- `MODULE_SOURCE_KIND=git` with exact branch and commit SHA; or
- `MODULE_SOURCE_KIND=release` with an immutable ZIP URL and SHA-256.

Every module config also declares the installation directory, provider class,
Cattr module name, environment file, enable variable, and any database tables
that must exist after migration. The deployer includes all `modules/*.conf`
entries in the same derived image.

Prefer release artifacts. Git-commit mode exists for the initial clock-in module
deployment and emergency reviewed builds before a release is available.

## Important boundary

The automated tests prove installation and Cattr health. They do not generate a
real desktop clock-in or WhatsApp message. That functional test remains an
explicit, approved production action after deployment.
