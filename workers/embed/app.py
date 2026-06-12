"""Almanac FastAPI embed worker.

Endpoints:
- POST /embed          chunk + embed a document, return vectors
- POST /llm-judge      LLM-as-judge re-scoring of a low-confidence answer
- POST /redact         Centroid-text PII redaction pass for the gap-report job
- GET  /healthz        liveness probe

Default provider is `mock` — deterministic 1536-dim vectors via SHA-256
projection. Self-hosted deploys can wire OpenAI by setting
ALMANAC_EMBED_PROVIDER=openai + OPENAI_API_KEY.

Authentication: shared-secret header ALMANAC-WORKER-SECRET must match
the env var. Laravel's job dispatcher sets it; cron sets it.
"""
from __future__ import annotations

import hashlib
import os
import re
from typing import Iterable

import httpx
from fastapi import Depends, FastAPI, Header, HTTPException
from pydantic import BaseModel, Field

EMBED_DIM = 1536
CHUNKER_VERSION = "v1"

PROVIDER = os.environ.get("ALMANAC_EMBED_PROVIDER", "mock")
OPENAI_KEY = os.environ.get("OPENAI_API_KEY", "")
SHARED_SECRET = os.environ.get(
    "ALMANAC_EMBED_WORKER_SHARED_SECRET", "local-dev-secret"
)

app = FastAPI(
    title="Almanac Embed Worker",
    description="Chunker + embedder + LLM-judge sidecar.",
    version="0.1.0",
)


def require_secret(x_almanac_worker_secret: str = Header(default="")) -> None:
    if x_almanac_worker_secret != SHARED_SECRET:
        raise HTTPException(status_code=401, detail="invalid worker secret")


class EmbedRequest(BaseModel):
    document_id: str = Field(..., min_length=1, max_length=64)
    body: str = Field(..., min_length=1)
    chunker_version: str = CHUNKER_VERSION


class EmbedChunk(BaseModel):
    seq: int
    text: str
    token_count: int
    embedding: list[float]


class EmbedResponse(BaseModel):
    document_id: str
    embedder_version: str
    chunker_version: str
    chunks: list[EmbedChunk]


class RedactRequest(BaseModel):
    text: str


class RedactResponse(BaseModel):
    text: str


class JudgeRequest(BaseModel):
    query: str
    answer: str
    chunks: list[str]


class JudgeResponse(BaseModel):
    confidence: str
    rationale: str


@app.get("/healthz")
def healthz() -> dict[str, str]:
    return {
        "status": "healthy",
        "provider": PROVIDER,
        "embed_dim": str(EMBED_DIM),
    }


@app.post("/embed", response_model=EmbedResponse, dependencies=[Depends(require_secret)])
async def embed(req: EmbedRequest) -> EmbedResponse:
    chunks = list(_chunk(req.body))
    vectors = await _embed_texts([c["text"] for c in chunks])
    return EmbedResponse(
        document_id=req.document_id,
        embedder_version=_embedder_version(),
        chunker_version=req.chunker_version,
        chunks=[
            EmbedChunk(
                seq=c["seq"],
                text=c["text"],
                token_count=c["token_count"],
                embedding=v,
            )
            for c, v in zip(chunks, vectors)
        ],
    )


@app.post("/redact", response_model=RedactResponse, dependencies=[Depends(require_secret)])
def redact(req: RedactRequest) -> RedactResponse:
    """Strip PII-ish patterns from cluster centroid text.

    Mock-mode redaction is regex-based: emails, phone numbers, long digit
    runs. Self-hosted deploys with an LLM API key can swap this for a
    structured-output rewrite call.
    """
    out = re.sub(r"\b[\w.+-]+@[\w.-]+\b", "[email]", req.text)
    out = re.sub(r"\b(?:\+?\d[\s\-()]*){7,}\d\b", "[phone]", out)
    out = re.sub(r"\b\d{9,}\b", "[number]", out)
    return RedactResponse(text=out)


@app.post("/llm-judge", response_model=JudgeResponse, dependencies=[Depends(require_secret)])
def llm_judge(req: JudgeRequest) -> JudgeResponse:
    """Cheap LLM-as-judge confidence rescoring.

    Mock-mode: returns 'high' if the answer references any chunk text via
    substring overlap of 12+ characters; else 'low'.
    """
    for chunk in req.chunks:
        for window in (chunk[i : i + 12] for i in range(0, max(1, len(chunk) - 12), 12)):
            if window and window in req.answer:
                return JudgeResponse(confidence="high", rationale="chunk_overlap")
    return JudgeResponse(confidence="low", rationale="no_chunk_overlap")


# ---------- chunker ----------


def _chunk(body: str) -> Iterable[dict]:
    body = body.replace("\r\n", "\n").strip()
    if not body:
        return
    paragraphs = re.split(r"\n{2,}", body)
    buf = ""
    seq = 0
    target = 1600
    overlap = 320
    for p in paragraphs:
        if len(buf) + len(p) <= target:
            buf = (buf + "\n\n" + p) if buf else p
            continue
        if buf:
            yield {"seq": seq, "text": buf, "token_count": max(1, len(buf) // 4)}
            seq += 1
            tail = buf[-overlap:]
            buf = tail + "\n\n" + p
        else:
            buf = p
    if buf:
        yield {"seq": seq, "text": buf, "token_count": max(1, len(buf) // 4)}


# ---------- embedders ----------


def _embedder_version() -> str:
    if PROVIDER == "openai":
        return "text-embedding-3-small"
    return "mock-1536"


async def _embed_texts(texts: list[str]) -> list[list[float]]:
    if PROVIDER == "openai":
        return await _embed_openai(texts)
    return [_embed_mock(t) for t in texts]


def _embed_mock(text: str) -> list[float]:
    tokens = [t for t in re.split(r"[^\w]+", text.lower()) if t]
    if not tokens:
        tokens = ["empty"]
    vec = [0.0] * EMBED_DIM
    for tok in tokens:
        h = hashlib.sha256(tok.encode("utf-8")).digest()
        crc = sum(h) & 0xFFFFFFFF
        for i, b in enumerate(h[:64]):
            pos = ((b * 257) + crc) % EMBED_DIM
            vec[pos] += 1.0 if (b & 1) else -1.0
    mag = sum(v * v for v in vec) ** 0.5
    if mag == 0:
        return vec
    return [v / mag for v in vec]


async def _embed_openai(texts: list[str]) -> list[list[float]]:
    if not OPENAI_KEY:
        raise HTTPException(status_code=500, detail="OPENAI_API_KEY not set")
    async with httpx.AsyncClient(timeout=60) as client:
        r = await client.post(
            "https://api.openai.com/v1/embeddings",
            headers={"Authorization": f"Bearer {OPENAI_KEY}"},
            json={"model": "text-embedding-3-small", "input": texts},
        )
        r.raise_for_status()
        data = r.json()
        return [d["embedding"] for d in data["data"]]
