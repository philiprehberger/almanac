'use client';

import { ApiReferenceReact } from '@scalar/api-reference-react';

export default function ApiReferencePage() {
  const config = {
    spec: { url: '/openapi.yaml' },
    layout: 'modern',
    theme: 'default',
    hideDownloadButton: false,
    searchHotKey: 'k',
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
  } as any;
  return <ApiReferenceReact configuration={config} />;
}
