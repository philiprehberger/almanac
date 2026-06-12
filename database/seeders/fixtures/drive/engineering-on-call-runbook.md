# Engineering On-Call Runbook

## Rotation

Two engineers per shift: primary and secondary. Shifts are one week, Monday at 10am Pacific to Monday at 10am Pacific the following week. The on-call calendar is maintained in PagerDuty.

## Alert tiers

- **P0** — customer-facing outage, data loss, security incident. Page primary and secondary; declare an incident channel within 5 minutes; runbook in the Incident Template.
- **P1** — degraded service, increased error rates, latency above SLO. Page primary; 15-minute response.
- **P2** — internal-only impact. Email or Slack, business-hours response.

## Acknowledgement and handoff

Primary acknowledges within 5 minutes for P0 / P1. If primary is unreachable for 10 minutes, the alert escalates to secondary, then to the on-call manager.

## Common runbooks

- Database failover — see `docs/runbooks/db-failover.md`.
- Deploy rollback — see Engineering Deploy Process §3.
- Secret rotation — see Secrets Rotation Schedule.
