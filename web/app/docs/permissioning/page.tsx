export const metadata = { title: 'Permissioning — Almanac' };

export default function PermissioningPage() {
  return (
    <article className="prose">
      <h1>Permissioning</h1>
      <p>
        Almanac mirrors source-side permissions per connector at ingest time. The mapping shape is the
        same regardless of source.
      </p>

      <h2>Principal kinds</h2>
      <p>
        <code>user</code>, <code>group</code>, <code>workspace</code>, <code>public</code>.{' '}
        <code>workspace</code> and <code>public</code> short-circuit the ACL function and always
        match a caller in the workspace.
      </p>

      <h2>Identity mappings</h2>
      <p>
        At OAuth-completion time, the connector populates{' '}
        <code>identity_mappings (almanac_user_id, source_kind, source_principal_id,
        source_principal_kind)</code>. Drive bootstraps user primary email + group memberships via the
        Admin SDK (cached 24h). Notion reads the workspace user ID. Slack reads user ID + channel
        memberships, refreshed by the <code>member_joined_channel</code> / <code>member_left_channel</code>
        Events API webhooks where wired.
      </p>

      <h2>Retrieval</h2>
      <p>
        <code>AclAwareRetriever</code> estimates selectivity, over-fetches by{' '}
        <code>target_K / max(selectivity, 0.01)</code> clamped to [50, 500], sets{' '}
        <code>hnsw.iterative_scan = strict_order</code> and{' '}
        <code>hnsw.max_scan_tuples = k_request * 4</code>, then runs a SQL query that applies the
        ACL filter via <code>user_can_read(principal_set, document_id)</code> inside the iterative
        scan — not post-filtered on top.
      </p>
      <p>
        If the post-filter result count falls below <code>min_results</code>, a second pass runs with
        a hard cap of 2000 tuples before declaring <code>acl_thin</code>. Acl-thin queries surface as
        confidence: low with reason <code>acl_thin</code> in the audit log and count toward the gap
        report.
      </p>

      <h2>ACL drift</h2>
      <p>
        Source-side permission changes are picked up by the 10-minute ingest cron. ACL rows naming a
        principal that no Almanac user is mapped to are stored (so the drift is auditable) but never
        match — fail-closed. Slack channel membership changes are also picked up live via the Events
        API where wired.
      </p>
    </article>
  );
}
