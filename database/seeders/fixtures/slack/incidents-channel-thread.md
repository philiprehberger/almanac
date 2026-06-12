# #incidents — recent thread

**@on-call** 7:30 PM
P1: API p99 latency above 2s for the last 4 minutes. Investigating.

**@on-call** 7:33 PM
Root cause: deploy at 7:20 PM introduced a regression in the chunk-retrieval path. Rolling back to release `20260524195800`.

**@on-call** 7:35 PM
Rollback complete. p99 back under 600ms.

**@on-call** 7:40 PM
Post-mortem owner: jordan. Will be published by EOD Friday.
