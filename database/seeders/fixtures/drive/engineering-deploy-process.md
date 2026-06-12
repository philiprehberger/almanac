# Engineering Deploy Process

## Cadence

We deploy main to production multiple times per day via atomic releases. Hotfixes can be cut from any commit on main provided CI is green.

## §1 — Pre-deploy checks

- CI must be green on the target SHA.
- The migration plan must be reviewed if the diff touches `database/migrations/`.
- The deployer announces the deploy in `#deploys` Slack with the SHA and a one-line description.

## §2 — Deploy command

```bash
npm run deploy -- --sha <sha>
```

The command builds locally, ships an archive to the EC2 host, extracts to `releases/<timestamp>/`, swaps the `current` symlink, and runs `systemctl reload php8.3-fpm` to invalidate OPcache against the new symlink target.

## §3 — Rollback

```bash
npm run deploy:rollback
```

This re-swaps the `current` symlink to the previous release. Rollback does not undo migrations — migration rollback is a separate, deliberate step.

## §4 — Post-deploy

- `/v1/healthz` must return 200.
- Sentry release tag should match the SHA.
- The deployer monitors error rate and queue depth for ten minutes.
