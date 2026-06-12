<?php

namespace App\Services\Chat;

use Symfony\Component\HttpFoundation\StreamedResponse;

class SseChatStreamer
{
    public function stream(ChatPipelineResult $result): StreamedResponse
    {
        $tokenStream = $result->tokenStream;

        $response = new StreamedResponse(function () use ($result, $tokenStream) {
            $cited = [];

            if ($tokenStream !== null) {
                $marker = 1;
                foreach ($tokenStream as $frame) {
                    if (($frame['kind'] ?? '') === 'cite') {
                        $pos = (int) ($frame['chunk_id'] ?? 0);
                        $citation = $this->findCitation($result, $pos);
                        if ($citation !== null) {
                            $citation['marker'] = "[{$pos}]";
                            $cited[$pos] = true;
                            $this->emit('citation', $citation);
                        }
                    } elseif (($frame['kind'] ?? '') === 'token') {
                        $this->emit('token', ['text' => $frame['text'] ?? '']);
                    }
                }
            } else {
                // No streamed token feed → emit answer in one frame.
                $this->emit('token', ['text' => $result->answer]);
                foreach ($result->citations as $c) {
                    $this->emit('citation', $c);
                }
            }

            $this->emit('meta', [
                'confidence' => $result->confidence,
                'confidence_reason' => $result->confidenceReason,
                'query_id' => $result->queryId,
                'conversation_id' => $result->conversationId,
                'cost_usd' => round($result->costUsd, 6),
                'tokens_in' => $result->tokensIn,
                'tokens_out' => $result->tokensOut,
                'latency_ms' => $result->latencyMs,
            ]);
            $this->emit('done', new \stdClass());
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no');
        $response->headers->set('Connection', 'keep-alive');

        return $response;
    }

    private function emit(string $event, mixed $data): void
    {
        echo "event: {$event}\n";
        echo 'data: '.json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n\n";
        if (function_exists('ob_flush')) {
            @ob_flush();
        }
        @flush();
    }

    private function findCitation(ChatPipelineResult $result, int $pos): ?array
    {
        foreach ($result->citations as $c) {
            if (($c['marker'] ?? '') === "[{$pos}]") {
                return $c;
            }
        }
        // citations snapshot only has emitted (matched) chunks; for inline
        // <cite/> markers that wouldn't otherwise appear in the snapshot,
        // synthesize from the chunkMap if possible.
        $ulid = $result->chunkMap[$pos] ?? null;
        if ($ulid === null) {
            return null;
        }
        foreach ($result->retrieval->chunks as $chunk) {
            if ($chunk->id === $ulid) {
                return $chunk->toCitationArray($pos);
            }
        }
        return null;
    }
}
