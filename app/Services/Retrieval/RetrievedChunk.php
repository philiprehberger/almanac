<?php

namespace App\Services\Retrieval;

final readonly class RetrievedChunk
{
    public function __construct(
        public string $id,
        public string $documentId,
        public string $text,
        public float $score,
        public string $sourceUrl,
        public string $title,
        public string $kind,
    ) {
    }

    /**
     * Snippet for citation rendering (first ~180 chars, one paragraph).
     */
    public function snippet(int $max = 180): string
    {
        $clean = preg_replace('/\s+/', ' ', $this->text) ?? $this->text;
        if (mb_strlen($clean) <= $max) {
            return $clean;
        }
        return mb_substr($clean, 0, $max - 1).'…';
    }

    public function toCitationArray(int $marker): array
    {
        return [
            'marker' => "[{$marker}]",
            'chunk_id' => $this->id,
            'document_id' => $this->documentId,
            'source_url' => $this->sourceUrl,
            'title' => $this->title,
            'snippet' => $this->snippet(),
        ];
    }

    public function toFrozenSnapshot(int $marker): array
    {
        return [
            'marker' => "[{$marker}]",
            'chunk_id' => $this->id,
            'document_id' => $this->documentId,
            'text' => $this->text,
            'source_url' => $this->sourceUrl,
            'title' => $this->title,
            'snippet' => $this->snippet(),
        ];
    }
}
