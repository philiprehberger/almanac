import Link from 'next/link';

const sampleQueries = [
  "What's our PTO policy?",
  'What\'s our deploy process?',
  'Who do I escalate a customer security issue to?',
  'What does the contractor onboarding look like?',
];

const pillars: Array<[string, string]> = [
  [
    'Citations that stream inline',
    'Inline <cite id="…"/> tokens translate into SSE event: citation frames emitted next to the supporting text span. Copy-with-citations produces a pre-rendered markdown block — the footnotes survive paste.',
  ],
  [
    'ACL is propagated, not faked',
    'Drive file permissions, Notion page-share rules, Slack channel membership → doc_acls. Caller identity → principal set → ACL-aware retrieval using pgvector hnsw.iterative_scan = strict_order. Demo includes a role toggle so prospects watch the boundary hold.',
  ],
  [
    'The no-answer gap report',
    'Queries Almanac couldn\'t answer land in unanswered_questions. A nightly clustering job groups them. The admin sees "23 people asked variants of \'PTO policy\' — there\'s no doc on PTO." The signal nobody else captures because it requires owning the retrieval layer.',
  ],
  [
    'Prompt injection is a primary threat',
    'Retrieved chunks wrapped in <retrieved_chunk> tags treated as data, not instructions. Structured-output schema validation per provider. Output filter rejects URLs outside source domains, image tags, hallucinated citations. Trips land in prompt_injection_signals visible in admin.',
  ],
  [
    'Re-index is observable and atomic',
    'Manual reindex per source. Full reindex enters degraded mode; chat serves from a snapshot until alias-swap. No mixed stale/fresh results mid-flight. Per-source health on the admin: last sync, doc count, embed-queue depth, DLQ depth, last error with fix hint.',
  ],
  [
    'pgvector only, no managed sidecar',
    'Per-workspace partial HNSW indexes. The 0.8.0 floor is load-bearing — earlier versions can\'t do the iterative-scan pre-filter without destroying recall. The "no managed vector DB dependency" line is the docs-site selling point for the regulated-buyer audience.',
  ],
];

export default function Home() {
  return (
    <>
      <section className="pt-10 pb-16">
        <p className="text-sm font-semibold uppercase tracking-widest text-(--color-accent)">
          Internal-docs RAG · citations · ACL · gap report · injection defenses
        </p>
        <h1 className="mt-3 text-5xl font-bold tracking-tight">
          Your company almanac, indexed and queryable.
        </h1>
        <p className="mt-6 max-w-3xl text-lg text-(--color-ink-dim)">
          The single most-listed AI project on Upwork right now reads "build us an AI assistant on our
          company docs." Almanac is the version that doesn't punt on the load-bearing parts — citations
          that stream inline, ACL propagated from the source, a content-gap report on what the system
          couldn't answer, and prompt-injection defenses on every retrieved chunk.
        </p>
        <div className="mt-8 flex flex-wrap items-center gap-4">
          <Link
            href="/demo"
            className="rounded-md bg-(--color-ink) px-5 py-3 text-sm font-semibold text-white no-underline hover:bg-(--color-accent)"
          >
            Open the live demo
          </Link>
          <Link
            href="/docs/wedge"
            className="rounded-md border border-(--color-ink) px-5 py-3 text-sm font-semibold text-(--color-ink) no-underline hover:bg-(--color-paper-dim)"
          >
            Why your RAG demo isn't production-ready
          </Link>
          <a
            href="https://github.com/philiprehberger/almanac"
            className="rounded-md px-5 py-3 text-sm font-semibold text-(--color-ink-dim) no-underline hover:bg-(--color-paper-dim)"
          >
            Source on GitHub
          </a>
        </div>
      </section>

      <section className="border-y border-(--color-paper-dim) py-12">
        <h2 className="text-2xl font-bold tracking-tight">Try it on the fixture corpus</h2>
        <p className="mt-2 max-w-3xl text-(--color-ink-dim)">
          The public demo runs against a fictional company's docs across Drive, Notion, and Slack —
          ~22 documents on PTO, deploy process, on-call, security escalation, contractor onboarding,
          and so on. Role toggle changes ACL (Engineer can see deploy docs, Contractor cannot).
          Injection-bait docs sit in the corpus — the defenses are demonstrably exercised.
        </p>
        <ul className="mt-6 grid gap-2 md:grid-cols-2">
          {sampleQueries.map((q) => (
            <li key={q} className="rounded-md border border-(--color-paper-dim) bg-white p-3 text-sm">
              <Link href={`/demo?q=${encodeURIComponent(q)}`}>{q}</Link>
            </li>
          ))}
        </ul>
      </section>

      <section className="py-16">
        <h2 className="text-2xl font-bold tracking-tight">What's different from a ChatGPT-with-file-uploads demo</h2>
        <div className="mt-8 grid gap-8 md:grid-cols-2">
          {pillars.map(([title, body]) => (
            <div key={title}>
              <h3 className="text-lg font-semibold">{title}</h3>
              <div className="mt-2 leading-relaxed text-(--color-ink-dim)">{body}</div>
            </div>
          ))}
        </div>
      </section>

      <section className="border-t border-(--color-paper-dim) py-16">
        <h2 className="text-2xl font-bold tracking-tight">Stack</h2>
        <ul className="mt-6 grid gap-3 md:grid-cols-2">
          <li className="rounded-lg border border-(--color-paper-dim) bg-white p-4">
            <div className="font-semibold">Laravel 13 + Filament 5</div>
            <div className="mt-1 text-sm text-(--color-ink-dim)">
              Chat API, admin, connector OAuth, ACL model. Per-workspace partial HNSW index lifecycle.
            </div>
          </li>
          <li className="rounded-lg border border-(--color-paper-dim) bg-white p-4">
            <div className="font-semibold">FastAPI (Python 3.12)</div>
            <div className="mt-1 text-sm text-(--color-ink-dim)">
              Embed + chunker + LLM-judge sidecar. supervisord-managed, numprocs=2 from day one.
            </div>
          </li>
          <li className="rounded-lg border border-(--color-paper-dim) bg-white p-4">
            <div className="font-semibold">PostgreSQL 16 + pgvector ≥ 0.8</div>
            <div className="mt-1 text-sm text-(--color-ink-dim)">
              Per-workspace partial HNSW + iterative-scan ACL pre-filter. No Pinecone, no Weaviate.
            </div>
          </li>
          <li className="rounded-lg border border-(--color-paper-dim) bg-white p-4">
            <div className="font-semibold">Next.js 16 + Scalar</div>
            <div className="mt-1 text-sm text-(--color-ink-dim)">
              Docs / marketing / live chat demo, OpenAPI-driven API reference.
            </div>
          </li>
        </ul>
      </section>
    </>
  );
}
