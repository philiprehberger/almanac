# Engineering Incident Template

## Timeline

Open a `#incident-<ts>` channel in Slack and invite primary, secondary, and the on-call manager. The first responder posts a 1-line description in `#incidents`.

## Roles

- **Incident commander** — coordinates response, drives the timeline.
- **Comms lead** — owns external + internal updates.
- **Subject-matter expert** — closest to the affected system.

The IC is not necessarily the most senior person. It is whoever can think clearly and drive decisions in the moment.

## Communication cadence

Post a status update in the incident channel every 15 minutes during a P0, every 30 minutes during a P1.

## Post-mortem

Within 5 business days of resolution, the IC publishes a blameless post-mortem in the Engineering Post-Mortems folder. Use the post-mortem template. Required sections: timeline, what went well, what we'd do differently, action items.
