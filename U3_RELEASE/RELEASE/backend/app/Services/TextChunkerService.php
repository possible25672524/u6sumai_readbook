<?php

namespace App\Services;

/**
 * Splits a long text into overlapping chunks suitable for embedding.
 *
 * Strategy:
 *   - Max chunk size: ~500 tokens (~2000 chars)
 *   - Overlap: ~50 tokens (~200 chars)
 *   - Split on sentence/paragraph boundaries when possible
 */
class TextChunkerService
{
    private int $maxChars;
    private int $overlapChars;

    public function __construct(int $maxChars = 2000, int $overlapChars = 200)
    {
        $this->maxChars    = $maxChars;
        $this->overlapChars = $overlapChars;
    }

    /**
     * @param  string $text  Full extracted text
     * @return array<int, array{index: int, content: string, char_start: int, char_end: int, token_count: int}>
     */
    public function chunk(string $text): array
    {
        $text   = $this->normalizeText($text);
        $chunks = [];
        $index  = 0;
        $start  = 0;
        $len    = mb_strlen($text);

        while ($start < $len) {
            $end = min($start + $this->maxChars, $len);

            // Try to find a natural break point (paragraph > sentence > word)
            if ($end < $len) {
                $end = $this->findBreakPoint($text, $start, $end);
            }

            $content = mb_substr($text, $start, $end - $start);
            $content = trim($content);

            if ($content !== '') {
                $chunks[] = [
                    'index'       => $index,
                    'content'     => $content,
                    'char_start'  => $start,
                    'char_end'    => $end,
                    'token_count' => $this->estimateTokens($content),
                ];
                $index++;
            }

            // Move start with overlap
            $start = max($start + 1, $end - $this->overlapChars);
        }

        return $chunks;
    }

    // ─── Private Helpers ──────────────────────────────────────────

    private function normalizeText(string $text): string
    {
        // Normalize line endings
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        // Collapse excessive blank lines (keep max 2)
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return $text;
    }

    private function findBreakPoint(string $text, int $start, int $end): int
    {
        // Paragraph break
        $pos = mb_strrpos(mb_substr($text, $start, $end - $start), "\n\n");
        if ($pos !== false && $pos > $this->maxChars * 0.5) {
            return $start + $pos + 2;
        }

        // Sentence end (.  !  ?)
        $segment = mb_substr($text, $start, $end - $start);
        if (preg_match('/[.!?]["\']?\s+(?=[A-Zกขคงจฉชซ])/u', $segment, $m, PREG_OFFSET_CAPTURE)) {
            $matchEnd = $m[0][1] + mb_strlen($m[0][0]);
            if ($matchEnd > $this->maxChars * 0.5) {
                return $start + $matchEnd;
            }
        }

        // Word boundary
        $pos = mb_strrpos(mb_substr($text, $start, $end - $start), ' ');
        if ($pos !== false && $pos > $this->maxChars * 0.5) {
            return $start + $pos + 1;
        }

        return $end;
    }

    /**
     * Rough estimate: 1 token ≈ 4 chars (EN), 1.5 chars (TH).
     * We use 3 chars/token as a conservative middle ground.
     */
    private function estimateTokens(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 3);
    }
}
