# Clock-In Webhook Module Runbook

## Source and identity

- Repository: `https://github.com/deviitorinc/cattr-server`
- Independent branch: `modules/clock-in-webhook`
- Initial reviewed commit: `c769f7fe2ff155aa2fe5ed0ead141c686c623bd3`
- Composer package: `deviitor/cattr-clock-in-webhook-module`
- Cattr module name: `CattrClockInWebhook`
- Runtime path: `/app/modules/CattrClockInWebhook`

The initial production deployment was built from the reviewed commit because no
versioned module release existed yet. Future deployments should use a
`clock-in-webhook-v*` release and record its artifact checksum.

## Behavior contract

The module subscribes to `event.after.action.intervals.create` and accepts only
the authenticated user's first accepted, non-manual desktop interval for each
`Asia/Colombo` calendar day.

It ignores manual entries, offline-sync imports, and intervals created for
another user. It queues the webhook and stores one attempt per user and local
calendar date. Its database uniqueness rule is `(user_id, clock_in_date)`.

The queued job runs once. Outcomes are:

- `sent`: the receiver confirmed delivery;
- `failed`: a definite HTTP failure;
- `unknown`: the network outcome is ambiguous and must not be retried
  automatically.

Missing or disabled configuration fails closed and must not prevent time
tracking.

## Runtime configuration

Production configuration belongs only in the server-side, untracked file
`/root/apps/com.deviitor.cattr/clock-in-webhook.env` with mode `0600`:

```dotenv
CATTR_CLOCK_IN_WEBHOOK_ENABLED=false
CATTR_CLOCK_IN_WEBHOOK_URL=https://automata.deviitor.com/webhooks/cattr/clock-in
CATTR_CLOCK_IN_WEBHOOK_TOKEN=replace-with-the-production-secret
CATTR_CLOCK_IN_WEBHOOK_TIMEZONE=Asia/Colombo
CATTR_CLOCK_IN_WEBHOOK_CONNECT_TIMEOUT=2
CATTR_CLOCK_IN_WEBHOOK_TIMEOUT=5
```

Never place the real token in Git, Dockerfile layers, Compose YAML, screenshots,
logs, support tickets, or shell commands that will be retained. Rotate the token
if it is exposed.

## Initial production deployment record

On 2026-09-01, the module was deployed using:

- upstream rollback image ID
  `sha256:42c03e9b776ae8425118a48f74265285a57f928fcff23a2f0008129890f7c762`;
- rollback tag `cattr-local:rollback-before-clock-in-20260901`;
- candidate image `cattr-local:clock-in-c769f7f`;
- candidate image ID
  `sha256:bb71d23bb2aabfc355514cd152db914e527409a0276bfc798acc41c91a4a4b36`;
- Compose file `/root/apps/com.deviitor.cattr/docker-compose.yml`;
- database backup
  `/root/backups/com.deviitor.cattr/20260901-clock-in-module/cattr.sql`;
- database backup SHA-256
  `126fb7dbea60dd677be1088c1e7c625cc34fcf8485c4aa2999cdfb1e429e3e5e`.

The rollout first ran disabled, applied migration
`2026_09_01_000000_create_cattr_clock_in_webhook_attempts_table`, and then ran
enabled. Cattr reported the module enabled, `/status` returned HTTP 200, the
container had no restarts, the audit table remained empty, and no synthetic
WhatsApp test was sent.

This record documents the initial rollout; always inspect current production
state rather than assuming these image IDs are still active.

## Operational checks

Confirm the running image and health:

```bash
docker inspect cattr_app --format \
  'status={{.State.Status}} image={{.Config.Image}} image_id={{.Image}} restart_count={{.RestartCount}}'
curl -sS -o /tmp/cattr-status-body \
  -w 'status=%{http_code} time=%{time_total}s\n' \
  http://127.0.0.1:9180/status
```

Confirm module discovery and non-secret configuration:

```bash
docker exec cattr_app sh -lc '
  /usr/bin/php82 /app/artisan module:list
  printf "enabled=%s\n" "$CATTR_CLOCK_IN_WEBHOOK_ENABLED"
  printf "timezone=%s\n" "$CATTR_CLOCK_IN_WEBHOOK_TIMEZONE"
  test -n "$CATTR_CLOCK_IN_WEBHOOK_TOKEN" && echo token=present || echo token=missing
'
```

Inspect recent module records without printing the authorization token:

```sql
SELECT user_id, clock_in_date, status, response_status, created_at, updated_at
FROM cattr_clock_in_webhook_attempts
ORDER BY id DESC
LIMIT 20;
```

Use the current schema description if column names differ after a module update.

## Functional test

A valid end-to-end test is a real Cattr Desktop clock-in by an approved employee
who has no attempt for the current Colombo date. Observe all three layers:

1. Cattr accepts the interval.
2. One audit row reaches the expected terminal state.
3. Automata records a confirmed request and the existing employee WhatsApp group
   receives exactly one policy-controlled message.

Clocking in again on the same Colombo date must not send another message. Do not
delete a production audit row merely to repeat a test.

## Incident handling

- If Cattr clock-in fails, disable the module and recreate only `app` first.
- If the outcome is `unknown`, inspect Automata and WhatsApp delivery evidence;
  do not retry automatically.
- If repeated messages appear, disable the module, preserve the audit rows and
  logs, and inspect uniqueness and transaction handling before changing data.
- If the module disappears after a Cattr upgrade, verify the derived image and
  Composer autoload map; do not patch the running container.
- If the endpoint returns unauthorized, confirm only that the runtime token is
  present, then rotate and update it securely. Never print it for comparison.
