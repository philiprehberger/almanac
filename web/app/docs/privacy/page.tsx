export const metadata = { title: 'Privacy & data handling — Almanac' };

export default function PrivacyPage() {
  return (
    <article className="prose">
      <h1>Privacy &amp; data handling</h1>
      <h2>Retention</h2>
      <p>
        Conversations expire 30 days after creation by default. A daily{' '}
        <code>PruneExpiredConversations</code> job deletes rows past <code>expires_at</code> and
        cascades to <code>queries</code>, <code>feedback</code>, <code>unanswered_questions</code>,
        and <code>prompt_injection_signals</code> tied to those queries.
      </p>

      <h2>Right to be forgotten</h2>
      <p>
        <code>POST /v1/deletion-requests</code> with a <code>subject_user_external_id</code> queues a
        deletion job. The processor sweeps <code>queries</code>, <code>feedback</code>,{' '}
        <code>unanswered_questions</code>, and <code>conversations</code> belonging to that subject,
        recomputes affected gap clusters on the next nightly run, and writes the affected query IDs
        to <code>deletion_requests.affected_query_ids</code> for audit.
      </p>

      <h2>Encryption at rest</h2>
      <p>
        Connector OAuth tokens are stored with Laravel <code>encrypted</code> cast — the encryption
        key derives from <code>APP_KEY</code> at portfolio scale. The production upgrade path is
        envelope encryption with KMS or Vault. Documented as a self-hosted upgrade; not implemented
        in this demo.
      </p>

      <h2>Audit</h2>
      <p>
        <code>audit_events</code> records connector + admin + token-use mutations. The plan
        partitions by <code>created_at</code> monthly; the public demo runs unpartitioned. Token-use
        events are sampled at 1:100 for high-frequency sync calls, always-on for write/admin
        endpoints.
      </p>

      <h2>What the public demo stores</h2>
      <p>
        The public demo at <code>almanac.philiprehberger.com/demo</code> writes your queries and the
        chosen role into the public fixture workspace. Queries inherit the 30-day retention. Pasting
        sensitive data into the public demo is discouraged; the audit log is visible to the
        portfolio operator.
      </p>
    </article>
  );
}
