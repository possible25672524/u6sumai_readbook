<?php

declare(strict_types=1);

namespace App\Services\AI\DTOs;

/**
 * Immutable value object representing a single message in a chat conversation.
 *
 * Roles follow the OpenAI / Anthropic convention:
 *   "system"    – Instructions that shape the model's behaviour
 *   "user"      – The human's turn
 *   "assistant" – The model's previous response (for multi-turn contexts)
 */
final class ChatMessage
{
    public function __construct(
        public readonly string $role,
        public readonly string $content,
        public readonly ?string $name = null,   // optional speaker name
    ) {
        if (! in_array($role, ['system', 'user', 'assistant'], true)) {
            throw new \InvalidArgumentException(
                "Invalid role '{$role}'. Must be system, user, or assistant."
            );
        }
    }

    // ── Factory helpers ─────────────────────────────────────────────────────

    public static function system(string $content): self
    {
        return new self('system', $content);
    }

    public static function user(string $content, ?string $name = null): self
    {
        return new self('user', $content, $name);
    }

    public static function assistant(string $content): self
    {
        return new self('assistant', $content);
    }

    // ── Serialization ────────────────────────────────────────────────────────

    /**
     * Convert to the OpenAI messages array format.
     *
     * @return array<string, string>
     */
    public function toOpenAIArray(): array
    {
        $arr = ['role' => $this->role, 'content' => $this->content];

        if ($this->name !== null) {
            $arr['name'] = $this->name;
        }

        return $arr;
    }

    /**
     * Convert to the Anthropic messages array format.
     * Note: Anthropic separates system prompt from the messages array.
     *
     * @return array<string, string>
     */
    public function toAnthropicArray(): array
    {
        // Anthropic only accepts 'user' and 'assistant' roles in the messages array;
        // 'system' is passed separately via the system parameter.
        return ['role' => $this->role, 'content' => $this->content];
    }
}
