# Cattr Clock-In Webhook Module

An independent Cattr `laravel-module` package that sends a server-side webhook
after an employee's first accepted, non-manual desktop time interval of each
`Asia/Colombo` day.

The module follows Cattr's native integration pattern. Composer installs it
under `/app/vendor/deviitor/cattr-clock-in-webhook-module`, Cattr discovers its
`module.json`, and `ModuleServiceProvider` subscribes `EventObserver` to
`event.after.action.intervals.create`.

Manual entries, offline-sync imports, and intervals created for another user
are ignored. The module records one attempt per user and Colombo calendar day.
The queued job has one attempt; a network exception is recorded as `unknown`
and is never retried automatically.

## Runtime configuration

Set these values only in the Cattr server's untracked environment:

```dotenv
CATTR_CLOCK_IN_WEBHOOK_ENABLED=true
CATTR_CLOCK_IN_WEBHOOK_URL=https://automata.example.com/webhooks/cattr/clock-in
CATTR_CLOCK_IN_WEBHOOK_TOKEN=replace-with-the-server-side-secret
CATTR_CLOCK_IN_WEBHOOK_TIMEZONE=Asia/Colombo
```

Optional timeouts:

```dotenv
CATTR_CLOCK_IN_WEBHOOK_CONNECT_TIMEOUT=2
CATTR_CLOCK_IN_WEBHOOK_TIMEOUT=5
```

Run Cattr's migrations and ensure its normal queue worker is running after the
package is installed. Missing URL or token configuration fails closed without
affecting time tracking.

## Releases

Push the exact branch `modules/clock-in-webhook`, then create a namespaced tag:

```bash
git tag clock-in-webhook-v1.0.0
git push origin refs/tags/clock-in-webhook-v1.0.0
```

The release workflow validates the package, lints every PHP file, and publishes
a ZIP plus its SHA-256 checksum. The release archive can then be registered as
a Composer `package` repository and included in Cattr's existing
`BACKEND_MODULES` build argument as
`deviitor/cattr-clock-in-webhook-module:1.0.0`.

No production webhook is called by the release workflow.
