# Cattr Custom Module Documentation

This branch contains documentation only. It is intentionally independent from
Cattr's `main` branch and from every module branch.

## Repository model

The repository uses orphan branches so upstream Cattr updates and custom module
development do not share history:

| Branch | Purpose |
| --- | --- |
| `main` | Upstream Cattr server source; do not add custom modules here. |
| `modules/<module-name>` | One independent, installable Cattr module per branch. |
| `docs` | Shared module authoring, release, deployment, and operations guides. |

Start here:

- [Author a module](custom-modules/authoring.md)
- [Release a module](custom-modules/releasing.md)
- [Build and deploy safely](custom-modules/deployment.md)
- [Clock-in webhook runbook](custom-modules/clock-in-webhook.md)

## Non-negotiable rules

- Never commit a production token, password, `.env`, database dump, or customer
  data.
- Preserve the complete slash branch name. For example,
  `modules/clock-in-webhook` is one ref, not `modules` plus a subdirectory.
- A module branch contains only that module. It must not merge from `main`.
- Pin releases or reviewed commit SHAs in production. Do not rebuild from a
  moving branch without recording the resolved SHA.
- Build a derived image; do not edit files inside a running container.
- Back up the database and tag the current image before deploying a migration.
- Deploy with the webhook or other external side effect disabled first.
- Never send a real external message merely to verify installation unless an
  authorized person has approved the destination and test.

## Creating another module branch

Resolve the remote state first:

```bash
git ls-remote --heads https://github.com/deviitorinc/cattr-server.git \
  'modules/example-module'
```

If no ref is returned, create an orphan branch in a separate worktree. Replace
the paths with your own checkout locations:

```bash
git -C /path/to/cattr-server worktree add --detach \
  /path/to/cattr-example-module main
git -C /path/to/cattr-example-module switch --orphan \
  'modules/example-module'
```

After the first commit, push and verify the exact ref:

```bash
git push -u origin 'modules/example-module'
git ls-remote --heads origin 'modules/example-module'
```

The remote SHA must equal `git rev-parse HEAD` in the module worktree.
