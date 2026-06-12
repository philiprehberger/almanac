# #deploys — release announcement thread

**@alex** 12:00 PM
Cutting a deploy of `main@a7c4e1d` — touches the billing webhook handler + a migration adding `subscriptions.next_charge_at`. CI green. ETA 5 minutes.

**@jordan** 12:02 PM
Reviewed the migration — backfill is null-safe, applies in <1s on prod-scale. LGTM.

**@alex** 12:06 PM
Deploy complete. healthz green. Going to watch Sentry for the next 10 minutes.

**@alex** 12:17 PM
Sentry flat. Marking deploy successful.
