# Customer Security Issue Escalation

## Reporting paths

A customer can report a security issue via security@example.com or by replying to any support thread with `[security]` in the subject. Support categorizes such tickets at Sev-1 and forwards them to the security on-call within 1 hour.

## Triage roles

- **First-touch** — support engineer; confirms receipt within 1 hour, captures reporter contact details.
- **Security on-call** — security engineer; assesses severity within 4 hours, opens an internal ticket.
- **Engineering manager** — owns customer-facing communication if the issue extends beyond 24 hours.

## Severity tiers

- **S0** — confirmed exploit affecting one or more customer accounts. Engage legal + comms; status page update within 2 hours.
- **S1** — confirmed vulnerability without active exploit. Patch in days, not weeks.
- **S2** — reported issue requiring investigation. Triage timeline + technical owner within 5 business days.

## Out-of-scope

We do not engage with reports about third-party SDKs we use unless the configuration is something we control. Reports about end-user device compromise (phishing on the customer side, password reuse) get a standard support response, not a security workflow.
