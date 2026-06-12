import { Chat } from './Chat';

export const metadata = {
  title: 'Almanac — live demo',
  description:
    'Chat against the fixture-corpus workspace. Role toggle changes ACL; injection-bait docs in the corpus demonstrate the prompt-injection defenses.',
};

const SAMPLE_QUERIES = [
  "What's our PTO policy?",
  "What's our deploy process?",
  'Who do I escalate a customer security issue to?',
  'What does the contractor onboarding look like?',
  'What is the secrets rotation cadence?',
  'How do I file an expense report?',
];

const ROLES = [
  {
    key: 'hr',
    label: 'HR Manager',
    summary: 'Sees workspace-wide docs + HR-only escalation doc. Cannot see Engineering-private docs.',
    principals: [{ kind: 'group', id: 'hr@example.com' }],
  },
  {
    key: 'engineer',
    label: 'Engineer',
    summary: 'Sees engineering-group docs (deploy process, on-call, secrets rotation, security escalation).',
    principals: [{ kind: 'group', id: 'engineering@example.com' }],
  },
  {
    key: 'contractor',
    label: 'Contractor',
    summary: 'Sees only workspace-wide + contractor-scoped docs. Cannot see Engineering or HR-only docs.',
    principals: [{ kind: 'group', id: 'contractors@example.com' }],
  },
  {
    key: 'anonymous',
    label: 'Anonymous (public only)',
    summary: 'Sees only docs explicitly marked public. The Code of Conduct is the only one.',
    principals: [],
  },
];

export default function DemoPage() {
  return (
    <Chat sampleQueries={SAMPLE_QUERIES} roles={ROLES} />
  );
}
