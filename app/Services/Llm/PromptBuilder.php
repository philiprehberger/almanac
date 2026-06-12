<?php

namespace App\Services\Llm;

use App\Models\PromptTemplate;

/**
 * Wraps retrieved chunks in <retrieved_chunk id="N" source="..."> tags and
 * prepends the safety preamble. The tags are the safety boundary — the
 * system prompt tells the model to treat their contents as data, not
 * instructions.
 *
 * The chunk IDs emitted in the prompt are 1-indexed *positional* IDs
 * (1..K), not the ULID `doc_chunks.id`. The retriever returns the chunks
 * in order; the caller's `chunkMap` records which positional ID maps to
 * which ULID. This is the layer where citation_id ↔ chunk_id translation
 * happens.
 */
class PromptBuilder
{
    /**
     * @param  array<int, array{id:string, text:string, source_url:string, title:string}>  $chunks
     * @return array{messages:array<int, array{role:string, content:string}>, chunkMap:array<int, string>}
     */
    public function build(string $userQuery, array $chunks, ?PromptTemplate $template = null, ?string $conversationSummary = null): array
    {
        $system = $template?->system_prompt ?? PromptTemplate::defaultSystemPrompt();
        $wrapper = $template?->chunk_wrapper_template ?? PromptTemplate::defaultChunkWrapper();

        $chunkMap = [];
        $rendered = [];
        $i = 1;
        foreach ($chunks as $c) {
            $chunkMap[$i] = $c['id'];
            $rendered[] = strtr($wrapper, [
                '{id}' => (string) $i,
                '{source}' => $this->safeAttr($c['source_url']),
                '{title}' => $this->safeAttr($c['title']),
                '{text}' => $c['text'],
            ]);
            $i++;
        }

        $contextBlock = $rendered === []
            ? "No relevant chunks were retrieved."
            : "Sources:\n\n".implode("\n\n", $rendered);

        $messages = [
            ['role' => 'system', 'content' => $system],
        ];

        if ($conversationSummary !== null && $conversationSummary !== '') {
            $messages[] = ['role' => 'system', 'content' => "<conversation_summary>\n{$conversationSummary}\n</conversation_summary>"];
        }

        $messages[] = ['role' => 'user', 'content' => $contextBlock."\n\nQuestion: ".$userQuery];

        return ['messages' => $messages, 'chunkMap' => $chunkMap];
    }

    private function safeAttr(string $v): string
    {
        return str_replace(['"', '<', '>'], ['&quot;', '&lt;', '&gt;'], $v);
    }
}
