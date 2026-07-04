<?php

declare(strict_types=1);

namespace App\Services\AI\DTOs;

/**
 * Token-usage statistics returned by every AI API call.
 * Used for cost tracking / budget alerts (see AIUsageLog model).
 */
final class AIUsage
{
    public function __construct(
        public readonly int $promptTokens,
        public readonly int $completionTokens,
        public readonly int $totalTokens,
        /** Approximate USD cost; null when provider doesn't expose pricing */
        public readonly ?float $estimatedCostUsd = null,
    ) {}

    public static function zero(): self
    {
        return new self(0, 0, 0, 0.0);
    }

    /**
     * Build from an OpenAI usage object (stdClass or array).
     *
     * @param  array<string, int>|object  $usage
     */
    public static function fromOpenAI(array|object $usage): self
    {
        $u = (array) $usage;

        return new self(
            promptTokens: (int) ($u['prompt_tokens'] ?? 0),
            completionTokens: (int) ($u['completion_tokens'] ?? 0),
            totalTokens: (int) ($u['total_tokens'] ?? 0),
        );
    }

    /**
     * Build from an Anthropic usage object.
     *
     * @param  array<string, int>|object  $usage
     */
    public static function fromAnthropic(array|object $usage): self
    {
        $u = (array) $usage;

        $input  = (int) ($u['input_tokens'] ?? 0);
        $output = (int) ($u['output_tokens'] ?? 0);

        return new self(
            promptTokens: $input,
            completionTokens: $output,
            totalTokens: $input + $output,
        );
    }

    public function toArray(): array
    {
        return [
            'prompt_tokens'      => $this->promptTokens,
            'completion_tokens'  => $this->completionTokens,
            'total_tokens'       => $this->totalTokens,
            'estimated_cost_usd' => $this->estimatedCostUsd,
        ];
    }
}
