export const metadata = { title: 'About — Almanac' };

export default function AboutPage() {
  return (
    <article className="prose">
      <h1>About Almanac</h1>
      <p>
        Almanac is a portfolio demonstration by{' '}
        <a href="https://philiprehberger.com">Philip Rehberger</a>. The whole stack — Laravel API +
        Filament admin + FastAPI embed worker + pgvector retrieval + Next.js docs &amp; live demo +
        atomic-release deploy + supervisord workers + Sentry instrumentation + a cross-channel
        portfolio bundle — is the work of one person, end-to-end.
      </p>
      <p>
        Almanac sells against ChatGPT-with-file-uploads (no permissioning, no citations, no re-index)
        and against Glean / Guru (real budgets, requires a sales call, takes months). The wedge:{' '}
        <em>here is a working version, owned end-to-end, that you can self-host and that handles
        re-index, ACL, citations, and prompt-injection correctly.</em>
      </p>
      <h2>License</h2>
      <p>MIT.</p>
      <h2>Get in touch</h2>
      <p>
        For self-hosted deployments, integration questions, or engagements that involve any of this
        stack, contact via{' '}
        <a href="https://www.linkedin.com/in/philiprehberger/">LinkedIn</a> or{' '}
        <a href="https://www.upwork.com/freelancers/philiprehberger">Upwork</a>.
      </p>
    </article>
  );
}
