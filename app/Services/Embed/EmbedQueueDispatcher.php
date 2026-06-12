<?php

namespace App\Services\Embed;

use App\Jobs\EmbedDocumentJob;
use App\Models\Document;
use App\Models\Workspace;

class EmbedQueueDispatcher
{
    public function dispatch(Workspace $workspace, Document $document, string $body): void
    {
        EmbedDocumentJob::dispatch($workspace->id, $document->id, $body)
            ->onQueue('embed');
    }
}
