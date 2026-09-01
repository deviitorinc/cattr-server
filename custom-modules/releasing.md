# Releasing a Module

Each module branch owns its releases. Use a namespaced tag so tags from several
module branches remain unambiguous in the same GitHub repository.

## Tag convention

For branch `modules/example-module`, use:

```text
example-module-vMAJOR.MINOR.PATCH
```

Example:

```bash
git switch 'modules/example-module'
git status --short --branch
git tag -a 'example-module-v1.0.0' -m 'Example module 1.0.0'
git push origin 'refs/tags/example-module-v1.0.0'
```

Tag only a clean, reviewed commit that has already been pushed to its exact
module branch.

## Release archive

The module workflow should:

1. Run Composer metadata validation.
2. Lint all PHP files.
3. Run module tests where the required Cattr test harness is available.
4. Create a ZIP whose root contains `composer.json`, `module.json`, and the
   module directories.
5. Publish a SHA-256 checksum alongside the ZIP.

Tests, GitHub workflow files, local environment files, and build output should
not be included in the runtime archive.

Example artifact names:

```text
cattr-example-module-1.0.0.zip
cattr-example-module-1.0.0.zip.sha256
```

## Verify the published release

Download both files and verify before using the artifact in an image build:

```bash
sha256sum -c cattr-example-module-1.0.0.zip.sha256
unzip -l cattr-example-module-1.0.0.zip
```

Record all four identifiers in the deployment change:

- module branch;
- release tag;
- Git commit SHA;
- artifact SHA-256.

The Git tag and branch should resolve to the reviewed commit:

```bash
git ls-remote https://github.com/deviitorinc/cattr-server.git \
  'refs/heads/modules/example-module' \
  'refs/tags/example-module-v1.0.0^{}'
```

## Updating a module

Create a new semantic version and rebuild the derived Cattr image. Never
overwrite an old release artifact in place. Keep the old runtime image tag until
the new deployment has passed health, log, module-discovery, and functional
checks.
