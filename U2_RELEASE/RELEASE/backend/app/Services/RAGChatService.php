<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\AI\AIManager;
use App\Services\AI\DTOs\ChatMessage;
use App\Services\AI\DTOs\ChatResponse;

/**
 * RAG (Retrieval-Augmented Generation) Chatbot Service — Module 9.
 *
 * Enforces the project requirement: "Chatbot MUST answer ONLY from uploaded
 * documents." This is achieved by:
 *   1. Embedding the user query via EmbeddingService
 *   2. Retrieving top-K relevant chunks from ChromaDB (injected as $retriever)
 *   3. Building a grounded prompt with retrieved context
 *   4. Calling Claude with a strict system prompt that forbids general knowledge
 *
 * Called exclusively from Queue Jobs — never inline in HTTP requests.
 */
final class RAGChatService
{
    public function __construct(
        private readonly AIManager $ai,
    ) {}

    /**
     * Answer a user question grounded in the provided document context chunks.
     *
     * @param  string    $question        The user's question
     * @param  string[]  $contextChunks   Retrieved document excerpts (from ChromaDB)
     * @param  array<array{role: string, content: string}>  $history  Prior conversation turns
     * @param  string    $language        Response language hint ('th'|'en'|'auto')
     * @param  int|null  $userId
     * @return ChatResponse
     */
    public function answer(
        string $question,
        array $contextChunks,
        array $history = [],
        string $language = 'auto',
        ?int $userId = null,
    ): ChatResponse {
        $languageInstruction = match ($language) {
            'th'    => 'Always respond in Thai.',
            'en'    => 'Always respond in English.',
            default => 'Respond in the same language as the question.',
        };

        $systemPrompt = config('ai.prompts.rag_system') . "\n" . $languageInstruction;

        $messages = [ChatMessage::system($systemPrompt)];

        // Inject prior conversation turns for multi-turn context
        foreach ($history as $turn) {
            $role    = $turn['role'] ?? 'user';
            $content = $turn['content'] ?? '';
            if ($role === 'assistant') {
                $messages[] = ChatMessage::assistant($content);
            } else {
                $messages[] = ChatMessage::user($content);
            }
        }

        // Build the grounded user turn: context excerpts + the actual question
        $contextBlock = $this->buildContextBlock($contextChunks);
        $userContent  = <<<PROMPT
DOCUMENT EXCERPTS:
{$contextBlock}

---
QUESTION: {$question}
PROMPT;

        $messages[] = ChatMessage::user($userContent);

        return $this->ai->chat($messages, [
            'operation'   => 'rag_chat',
            'user_id'     => $userId,
            'temperature' => 0.2,   // low temperature for factual grounding
        ]);
    }

    /**
     * Quick single-turn answer — no conversation history.
     * Used by the Quick Answer Mode (Module 10).
     *
     * @param  string[]  $contextChunks
     */
    public function quickAnswer(
        string $question,
        array $contextChunks,
        ?int $userId = null,
    ): ChatResponse {
        return $this->answer($question, $contextChunks, [], 'auto', $userId);
    }

    // ── Internal ─────────────────────────────────────────────────────────

    /**
     * Format retrieved chunks into a numbered excerpt block.
     *
     * @param  string[]  $chunks
     */
    private function buildContextBlock(array $chunks): string
    {
        if (empty($chunks)) {
            return '(No document excerpts retrieved.)';
        }

        return implode("\n\n", array_map(
            fn (string $chunk, int $i) => "[Excerpt " . ($i + 1) . "]\n{$chunk}",
            $chunks,
            array_keys($chunks),
        ));
    }
}
