# Authoring a Cattr Module

This guide follows Cattr's native `laravel-module` integration pattern. Existing
Cattr integrations use the same core pieces: `composer.json`, `module.json`, a
service provider, and optional event subscribers, jobs, services, configuration,
migrations, and tests.

## Minimum layout

```text
Config/
  config.php
Providers/
  ModuleServiceProvider.php
Subscribers/
  EventObserver.php
Tests/
composer.json
module.json
README.md
```

Add `Database/Migrations`, `Jobs`, and `Services` only when the module needs
them. Keep PHP namespaces aligned with paths. If the module namespace is
`Modules\\ExampleModule\\`, then the provider must be located at
`Providers/ModuleServiceProvider.php`, not at the repository root.

## Composer metadata

Use package type `laravel-module` and map the module namespace from the branch
root:

```json
{
    "name": "deviitor/cattr-example-module",
    "type": "laravel-module",
    "autoload": {
        "psr-4": {
            "Modules\\ExampleModule\\": ""
        }
    },
    "require": {
        "php": "~8.2"
    }
}
```

Run `composer validate --strict --no-check-publish` before committing.

## Cattr module metadata

`module.json` tells Cattr how to discover the module:

```json
{
    "name": "ExampleModule",
    "alias": "example_module",
    "description": "One sentence describing the module.",
    "active": 1,
    "priority": 10,
    "providers": [
        "Modules\\ExampleModule\\Providers\\ModuleServiceProvider"
    ],
    "aliases": {},
    "files": [],
    "requires": [],
    "minimumCoreVersion": "5.0"
}
```

Confirm the minimum core version against the target Cattr installation rather
than copying it blindly.

## Provider and events

The service provider normally does three things:

1. Loads migrations with `loadMigrationsFrom(module_path(...))`.
2. Merges module configuration with `mergeConfigFrom(module_path(...))`.
3. Registers a subscriber from static `registerEvents()` using
   `CatEvent::subscribe(...)`.

An event subscriber returns Cattr event-to-handler mappings from `subscribe()`.
Before choosing an event, trace where the core emits it and inspect the payload.
Do not infer an event contract from its name alone.

Useful discovery commands inside a source checkout or development image:

```bash
rg -n "CatEvent|event\.(before|after)\.action" app modules vendor/cattr
rg -n "intervals\.create|intervals\.edit" app modules vendor/cattr
```

Verify whether desktop online tracking, manual intervals, offline sync, admin
actions, and API-created records emit the same event. Filter explicitly when
only one source is allowed.

## External side-effect checklist

For webhooks, email, chat messages, or third-party writes:

- Default the feature to disabled.
- Read URLs and tokens only from server-side environment variables.
- Validate the event belongs to the authenticated actor when required.
- Queue network work so clock-in or another Cattr action is not blocked.
- Persist an idempotency key before dispatching the side effect.
- Set short connect and total timeouts.
- Distinguish confirmed success, confirmed failure, and ambiguous outcome.
- Do not automatically retry an ambiguous outcome.
- Never accept a destination, message, date, or timezone from an untrusted
  caller when those values are policy-controlled.

## Validation before release

At minimum:

```bash
composer validate --strict --no-check-publish
find Config Database Jobs Providers Services Subscribers Tests \
  -name '*.php' -print0 | xargs -0 -n1 php -l
```

Then build against the same Cattr base image used in production and assert each
important class after regenerating the application autoloader:

```bash
php -r 'require "/app/vendor/autoload.php"; exit(class_exists(
    "Modules\\ExampleModule\\Providers\\ModuleServiceProvider"
) ? 0 : 1);'
```

Finally, use Cattr's own discovery command:

```bash
/usr/bin/php82 /app/artisan module:list
```

Do not deploy if Composer reports that a module class was skipped for PSR-4
non-compliance.
