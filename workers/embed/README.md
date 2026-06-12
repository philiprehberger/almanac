# Almanac Embed Worker

FastAPI sidecar that handles chunking, embedding, LLM-judge re-scoring, and PII redaction. Runs under supervisord on the production host (`numprocs=2` from day one); locally via `uvicorn` or `uv run`.

## Endpoints

| Method | Path | Purpose |
|---|---|---|
| GET | `/healthz` | Liveness + provider info. |
| POST | `/embed` | Chunk a document body, return `{ seq, text, token_count, embedding }` per chunk. |
| POST | `/llm-judge` | Cheap second-pass confidence rescoring. |
| POST | `/redact` | Centroid-text PII redaction for the gap-report clustering job. |

All non-healthz endpoints require `X-Almanac-Worker-Secret` matching `ALMANAC_EMBED_WORKER_SHARED_SECRET`.

## Local dev

```bash
cd workers/embed
uv sync                   # or: python -m venv .venv && .venv/bin/pip install -e .
ALMANAC_EMBED_WORKER_SHARED_SECRET=local-dev-secret \
  uv run uvicorn app:app --port 8001 --reload
```

## Embed provider

Default `ALMANAC_EMBED_PROVIDER=mock` — deterministic 1536-dim vectors via SHA-256 projection. Swap to `openai` and supply `OPENAI_API_KEY` to use `text-embedding-3-small` (1536 dim, matches the schema).

The Laravel `EmbedDocumentJob` currently runs the chunker + mock embedder in-process. The worker exists for parity with the FastAPI stack-gap pickup story; flipping `ALMANAC_EMBED_PROVIDER=openai` and routing the Laravel job through HTTP is the self-hosted upgrade path.

## supervisord config

See `infra/supervisor/almanac-embed.conf` for the production config (`numprocs=2`, autostart, logs to `/var/log/almanac/embed-*.log`).
