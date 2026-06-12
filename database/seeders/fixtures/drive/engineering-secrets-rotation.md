# Secrets Rotation Schedule

## Cadence

| Secret kind | Frequency | Owner |
|---|---|---|
| Production database password | Every 90 days | Platform team |
| Customer-facing API signing keys | Every 60 days | Security on-call |
| Third-party vendor tokens (Stripe, Twilio) | At vendor recommended cadence | Service owner |
| Internal CI tokens | Every 180 days | Platform team |
| Personal SSH keys for prod access | Every 365 days | Each engineer |

## Process

Rotate secrets via the SecretManager CLI. The tool issues new credentials, dual-writes to the live store, validates the new credential, and retires the old one after 24 hours.

## Emergency rotation

If a secret may be exposed, rotate immediately and revoke the old credential without the dual-write grace window. Open an incident channel and include the rotation in the timeline.
