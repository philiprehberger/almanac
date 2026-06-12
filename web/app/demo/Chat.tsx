'use client';

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

type Principal = { kind: string; id: string };

type Role = {
  key: string;
  label: string;
  summary: string;
  principals: Principal[];
};

type Citation = {
  marker: string;
  chunk_id: string;
  document_id?: string;
  source_url?: string;
  title?: string;
  snippet?: string;
};

type Turn = {
  role: 'user' | 'assistant';
  content: string;
  citations?: Citation[];
  confidence?: string;
  confidenceReason?: string | null;
  costUsd?: number;
  queryId?: string;
  promptInjectionTriggered?: boolean;
};

type Props = {
  sampleQueries: string[];
  roles: Role[];
};

const API_BASE = process.env.NEXT_PUBLIC_ALMANAC_API ?? 'https://api.almanac.philiprehberger.com';
const PUBLIC_KEY = process.env.NEXT_PUBLIC_ALMANAC_DEMO_KEY ?? '';

export function Chat({ sampleQueries, roles }: Props) {
  const [roleKey, setRoleKey] = useState<string>(roles[1]?.key ?? roles[0]?.key);
  const [turns, setTurns] = useState<Turn[]>([]);
  const [pending, setPending] = useState(false);
  const [input, setInput] = useState('');
  const [conversationId, setConversationId] = useState<string | undefined>();
  const [copied, setCopied] = useState(false);
  const streamingRef = useRef<{ text: string; citations: Citation[] } | null>(null);
  const role = useMemo(() => roles.find((r) => r.key === roleKey) ?? roles[0], [roles, roleKey]);

  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const q = params.get('q');
    if (q) {
      void send(q);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const send = useCallback(
    async (query: string) => {
      if (!query.trim() || pending) return;
      setPending(true);
      setCopied(false);
      const newTurns: Turn[] = [...turns, { role: 'user', content: query }];
      setTurns(newTurns);
      streamingRef.current = { text: '', citations: [] };

      try {
        const headers: Record<string, string> = {
          'Content-Type': 'application/json',
          Accept: 'text/event-stream',
        };
        if (PUBLIC_KEY) headers.Authorization = `Bearer ${PUBLIC_KEY}`;

        const res = await fetch(`${API_BASE}/v1/chat`, {
          method: 'POST',
          headers,
          body: JSON.stringify({
            query,
            conversation_id: conversationId,
            as_principal: role?.principals ?? [],
            caller_label: role?.label,
          }),
        });

        if (!res.body) {
          const text = await res.text();
          appendAssistant({ text, citations: [], confidence: 'low' });
          return;
        }

        // Parse SSE frames manually to preserve inline citation ordering.
        const reader = res.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';
        let assistantText = '';
        let assistantCitations: Citation[] = [];
        let confidence: string | undefined;
        let confidenceReason: string | undefined | null;
        let queryId: string | undefined;
        let promptInjectionTriggered = false;
        setTurns((t) => [...t, { role: 'assistant', content: '' }]);

        while (true) {
          const { value, done } = await reader.read();
          if (done) break;
          buffer += decoder.decode(value, { stream: true });
          let idx: number;
          while ((idx = buffer.indexOf('\n\n')) !== -1) {
            const frame = buffer.slice(0, idx);
            buffer = buffer.slice(idx + 2);
            const event = /^event:\s*(.+)$/m.exec(frame)?.[1]?.trim();
            const data = /^data:\s*(.+)$/m.exec(frame)?.[1] ?? '';
            if (!event) continue;
            if (event === 'token') {
              try {
                const j = JSON.parse(data);
                assistantText += j.text ?? '';
                setTurns((t) => {
                  const copy = t.slice();
                  const last = copy[copy.length - 1];
                  if (last && last.role === 'assistant') {
                    copy[copy.length - 1] = { ...last, content: assistantText };
                  }
                  return copy;
                });
              } catch {
                /* ignore */
              }
            } else if (event === 'citation') {
              try {
                const c: Citation = JSON.parse(data);
                assistantCitations = [...assistantCitations, c];
                // Inline marker rendering: append [n] right after the
                // most-recent text frame.
                assistantText += ` ${c.marker}`;
                setTurns((t) => {
                  const copy = t.slice();
                  const last = copy[copy.length - 1];
                  if (last && last.role === 'assistant') {
                    copy[copy.length - 1] = {
                      ...last,
                      content: assistantText,
                      citations: assistantCitations,
                    };
                  }
                  return copy;
                });
              } catch {
                /* ignore */
              }
            } else if (event === 'meta') {
              try {
                const m = JSON.parse(data);
                confidence = m.confidence;
                confidenceReason = m.confidence_reason ?? null;
                queryId = m.query_id;
                if (m.conversation_id) setConversationId(m.conversation_id);
              } catch {
                /* ignore */
              }
            } else if (event === 'done') {
              // finalize
            } else if (event === 'error') {
              try {
                const e = JSON.parse(data);
                assistantText += `\n\n[error: ${e.message ?? 'unknown'}]`;
              } catch {
                /* ignore */
              }
            }
          }
        }

        setTurns((t) => {
          const copy = t.slice();
          const last = copy[copy.length - 1];
          if (last && last.role === 'assistant') {
            copy[copy.length - 1] = {
              ...last,
              content: assistantText,
              citations: assistantCitations,
              confidence,
              confidenceReason,
              queryId,
              promptInjectionTriggered,
            };
          }
          return copy;
        });
      } catch (err) {
        appendAssistant({
          text: 'Request failed against the demo API. The portfolio demo runs in mock-mode against a fixture corpus; if the API host is unreachable, try again or check the deploy status.',
          citations: [],
          confidence: 'low',
        });
      } finally {
        setPending(false);
      }
    },
    [pending, role, conversationId, turns]
  );

  const appendAssistant = (a: { text: string; citations: Citation[]; confidence: string }) => {
    setTurns((t) => [
      ...t,
      { role: 'assistant', content: a.text, citations: a.citations, confidence: a.confidence },
    ]);
  };

  const copyMarkdown = (turn: Turn) => {
    let md = turn.content + '\n\n';
    if ((turn.citations ?? []).length) {
      md += '---\n';
      for (const c of turn.citations ?? []) {
        const title = c.title ?? c.chunk_id;
        const url = c.source_url ?? '';
        md += `${c.marker} **${title}** — ${url}\n`;
      }
    }
    navigator.clipboard.writeText(md);
    setCopied(true);
    setTimeout(() => setCopied(false), 1500);
  };

  return (
    <div className="grid gap-8 lg:grid-cols-[2fr_1fr]">
      <div>
        <h1 className="text-3xl font-bold tracking-tight">Live demo</h1>
        <p className="mt-2 text-sm text-(--color-ink-dim)">
          Mock-mode. Answers cite the fixture corpus. Role toggle changes ACL; the injection-bait docs in the corpus are handled by the output filter.
        </p>

        <div className="mt-6 rounded-lg border border-(--color-paper-dim) bg-white">
          <div className="border-b border-(--color-paper-dim) px-4 py-3 text-xs uppercase tracking-wider text-(--color-ink-dim)">
            View as: {role?.label}
          </div>
          <div className="flex flex-wrap gap-2 px-4 py-3">
            {roles.map((r) => (
              <button
                key={r.key}
                onClick={() => setRoleKey(r.key)}
                className={`rounded-md px-3 py-1.5 text-xs font-medium ${
                  r.key === roleKey
                    ? 'bg-(--color-ink) text-white'
                    : 'border border-(--color-paper-dim) text-(--color-ink-dim) hover:bg-(--color-paper-dim)'
                }`}
              >
                {r.label}
              </button>
            ))}
          </div>
          <div className="border-t border-(--color-paper-dim) px-4 py-3 text-xs text-(--color-ink-dim)">
            {role?.summary}
          </div>

          <div className="max-h-[60vh] min-h-[40vh] overflow-y-auto p-4">
            {turns.length === 0 ? (
              <div className="text-sm text-(--color-ink-dim)">
                Pick a sample query or type one. Try toggling roles between asks — same question, different answer scope.
              </div>
            ) : (
              <div className="space-y-4">
                {turns.map((t, i) => (
                  <div
                    key={i}
                    className={`rounded-md border px-3 py-2 text-sm ${
                      t.role === 'user'
                        ? 'border-(--color-paper-dim) bg-(--color-paper)'
                        : 'border-(--color-accent-soft) bg-white'
                    }`}
                  >
                    <div className="text-xs font-semibold uppercase tracking-wider text-(--color-ink-dim)">
                      {t.role === 'user' ? 'You' : 'Almanac'}
                      {t.confidence ? ` · confidence: ${t.confidence}` : ''}
                      {t.confidenceReason ? ` (${t.confidenceReason})` : ''}
                    </div>
                    <div className="mt-1 whitespace-pre-wrap leading-relaxed">{t.content}</div>
                    {t.role === 'assistant' && (t.citations ?? []).length > 0 && (
                      <div className="mt-3 border-t border-(--color-paper-dim) pt-2 text-xs text-(--color-ink-dim)">
                        Sources:
                        <ul className="mt-1 space-y-1">
                          {t.citations!.map((c) => (
                            <li key={`${c.marker}-${c.chunk_id}`}>
                              <span className="font-mono">{c.marker}</span>{' '}
                              <a href={c.source_url} target="_blank" rel="noreferrer">
                                {c.title || c.chunk_id}
                              </a>
                              {c.snippet ? ` — ${c.snippet}` : null}
                            </li>
                          ))}
                        </ul>
                        <button
                          onClick={() => copyMarkdown(t)}
                          className="mt-2 rounded border border-(--color-paper-dim) px-2 py-1 text-xs hover:bg-(--color-paper-dim)"
                        >
                          {copied ? 'Copied' : '[Copy with citations]'}
                        </button>
                      </div>
                    )}
                  </div>
                ))}
              </div>
            )}
          </div>

          <form
            onSubmit={(e) => {
              e.preventDefault();
              const q = input;
              setInput('');
              void send(q);
            }}
            className="flex gap-2 border-t border-(--color-paper-dim) p-3"
          >
            <input
              value={input}
              onChange={(e) => setInput(e.target.value)}
              placeholder="Ask a question…"
              disabled={pending}
              className="flex-1 rounded-md border border-(--color-paper-dim) bg-white px-3 py-2 text-sm focus:border-(--color-accent) focus:outline-none"
            />
            <button
              type="submit"
              disabled={pending || !input.trim()}
              className="rounded-md bg-(--color-ink) px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
            >
              {pending ? 'Asking…' : 'Ask'}
            </button>
          </form>
        </div>
      </div>

      <aside className="space-y-6">
        <div className="rounded-lg border border-(--color-paper-dim) bg-white p-4">
          <h2 className="text-sm font-semibold uppercase tracking-wider text-(--color-ink-dim)">
            Sample queries
          </h2>
          <ul className="mt-3 space-y-2 text-sm">
            {sampleQueries.map((q) => (
              <li key={q}>
                <button
                  onClick={() => void send(q)}
                  disabled={pending}
                  className="text-left hover:underline disabled:opacity-50"
                >
                  {q}
                </button>
              </li>
            ))}
          </ul>
        </div>

        <div className="rounded-lg border border-(--color-paper-dim) bg-white p-4">
          <h2 className="text-sm font-semibold uppercase tracking-wider text-(--color-ink-dim)">
            What's actually happening
          </h2>
          <ol className="mt-3 space-y-2 text-sm text-(--color-ink-dim)">
            <li>1. Your query is embedded against the same 1536-dim space as the chunks.</li>
            <li>2. Selectivity is estimated from the role's principal-set. Iterative-scan over-fetches, then the ACL filter runs inside the scan.</li>
            <li>3. Top chunks are wrapped in <code>&lt;retrieved_chunk&gt;</code> tags. The LLM returns structured JSON.</li>
            <li>4. The output filter rejects URLs outside source domains, image tags, and hallucinated citations. Trips land in <code>prompt_injection_signals</code>.</li>
            <li>5. Inline <code>&lt;cite id="N"/&gt;</code> tokens turn into SSE <code>event: citation</code> frames you see appear next to the text.</li>
          </ol>
        </div>
      </aside>
    </div>
  );
}
