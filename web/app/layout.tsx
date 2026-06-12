import './globals.css';
import Link from 'next/link';
import type { ReactNode } from 'react';

export const metadata = {
  title: 'Almanac — internal-docs RAG chatbot with citations, ACL, and a gap report',
  description:
    'Your company almanac, indexed and queryable. Drive + Notion + Slack ingest with cited sources, ACL-aware retrieval, prompt-injection defenses, and a no-answer gap report. The portfolio answer to "build us an AI assistant on our company docs."',
  metadataBase: new URL('https://almanac.philiprehberger.com'),
};

export default function RootLayout({ children }: { children: ReactNode }) {
  return (
    <html lang="en">
      <body>
        <header className="border-b border-(--color-paper-dim) bg-(--color-paper)">
          <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-5">
            <Link href="/" className="text-lg font-bold tracking-tight text-(--color-ink) no-underline">
              Almanac
              <span className="ml-2 rounded-full bg-(--color-accent-soft) px-2 py-0.5 text-xs font-medium text-(--color-accent) align-middle">
                portfolio demo
              </span>
            </Link>
            <nav className="flex items-center gap-6 text-sm text-(--color-ink-dim)">
              <Link href="/demo">Live demo</Link>
              <Link href="/docs/wedge">Wedge</Link>
              <Link href="/docs/permissioning">Permissioning</Link>
              <Link href="/docs/prompt-injection">Prompt injection</Link>
              <Link href="/api">API</Link>
              <Link href="/about">About</Link>
            </nav>
          </div>
        </header>
        <main className="mx-auto max-w-6xl px-6 py-10">{children}</main>
        <footer className="border-t border-(--color-paper-dim) mt-20">
          <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-8 text-sm text-(--color-ink-dim)">
            <div>
              Almanac is a portfolio demonstration by{' '}
              <a href="https://philiprehberger.com">Philip Rehberger</a>.
            </div>
            <div className="flex gap-4">
              <a href="https://github.com/philiprehberger/almanac">GitHub</a>
              <Link href="/docs/privacy">Privacy</Link>
              <Link href="/about">About</Link>
            </div>
          </div>
        </footer>
      </body>
    </html>
  );
}
