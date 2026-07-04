<?php

declare(strict_types=1);

namespace Tests\Unit\AI;

use App\Services\AI\DTOs\EmbeddingResponse;
use App\Services\AI\Exceptions\AIProviderException;
use App\Services\AI\Exceptions\AIRateLimitException;
use App\Services\AI\Providers\OpenAIEmbeddingProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Unit tests for OpenAIEmbeddingProvider.
 *
 * Verifies embed(), embedBatch(), model metadata, and error paths.
 * No real API calls are made — Http::fake() intercepts all requests.
 */
class OpenAIEmbeddingProviderTest extends TestCase
{
    private OpenAIEmbeddingProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = new OpenAIEmbeddingProvider(
            apiKey:         'test-openai-key',
            model:          'text-embedding-3-small',
            timeoutSeconds: 30,
            maxRetries:     2,
        );
    }

    // ── embed() ───────────────────────────────────────────────────────────

    /** @test */
    public function it_returns_an_embedding_response_for_a_single_text(): void
    {
        $vector = $this->makeVector(1536);

        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response(
                $this->openAIEmbeddingPayload([$vector]),
                200
            ),
        ]);

        $response = $this->provider->embed('ระบบประสาทส่วนกลาง');

        $this->assertInstanceOf(EmbeddingResponse::class, $response);
        $this->assertCount(1536, $response->vector);
        $this->assertSame('text-embedding-3-small', $response->model);
        $this->assertSame(1536, $response->dimensions);
        $this->assertSame('ระบบประสาทส่วนกลาง', $response->inputText);
    }

    /** @test */
    public function it_sends_the_correct_model_and_encoding_format(): void
    {
        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response(
                $this->openAIEmbeddingPayload([$this->makeVector(1536)]),
                200
            ),
        ]);

        $this->provider->embed('test text');

        Http::assertSent(function (Request $request) {
            $body = $request->data();
            $this->assertSame('text-embedding-3-small', $body['model']);
            $this->assertSame('float', $body['encoding_format']);
            $this->assertSame(['test text'], $body['input']);
            return true;
        });
    }

    /** @test */
    public function it_sends_bearer_token_auth_header(): void
    {
        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response(
                $this->openAIEmbeddingPayload([$this->makeVector(1536)]),
                200
            ),
        ]);

        $this->provider->embed('test');

        Http::assertSent(function (Request $request) {
            $this->assertStringStartsWith('Bearer ', $request->header('Authorization')[0]);
            $this->assertStringContainsString('test-openai-key', $request->header('Authorization')[0]);
            return true;
        });
    }

    // ── embedBatch() ──────────────────────────────────────────────────────

    /** @test */
    public function it_returns_multiple_embedding_responses_for_batch(): void
    {
        $vectors = [
            $this->makeVector(1536, seed: 1),
            $this->makeVector(1536, seed: 2),
            $this->makeVector(1536, seed: 3),
        ];

        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response(
                $this->openAIEmbeddingPayload($vectors, promptTokens: 15),
                200
            ),
        ]);

        $texts = ['text one', 'text two', 'text three'];
        $responses = $this->provider->embedBatch($texts);

        $this->assertCount(3, $responses);
        foreach ($responses as $i => $response) {
            $this->assertInstanceOf(EmbeddingResponse::class, $response);
            $this->assertCount(1536, $response->vector);
            $this->assertSame($texts[$i], $response->inputText);
        }
    }

    /** @test */
    public function it_returns_empty_array_for_empty_batch(): void
    {
        $responses = $this->provider->embedBatch([]);

        $this->assertSame([], $responses);
        Http::assertNothingSent();
    }

    /** @test */
    public function it_throws_invalid_argument_exception_when_batch_exceeds_limit(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/exceeds maximum/');

        // 2049 texts exceeds the 2048 limit
        $this->provider->embedBatch(array_fill(0, 2049, 'text'));
    }

    /** @test */
    public function it_sends_all_texts_in_a_single_api_call(): void
    {
        $texts = ['alpha', 'beta', 'gamma'];

        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response(
                $this->openAIEmbeddingPayload(array_fill(0, 3, $this->makeVector(1536))),
                200
            ),
        ]);

        $this->provider->embedBatch($texts);

        // Exactly one API call made for all 3 texts
        Http::assertSentCount(1);

        Http::assertSent(function (Request $request) use ($texts) {
            $this->assertSame($texts, $request->data()['input']);
            return true;
        });
    }

    // ── Error handling ────────────────────────────────────────────────────

    /** @test */
    public function it_throws_rate_limit_exception_on_429(): void
    {
        Http::fake([
            'api.openai.com/v1/embeddings' => Http::sequence()
                ->push(['error' => ['message' => 'Rate limit']], 429, ['retry-after' => '20'])
                ->push(['error' => ['message' => 'Rate limit']], 429, ['retry-after' => '20']),
        ]);

        $this->expectException(AIRateLimitException::class);

        $this->provider->embed('test');
    }

    /** @test */
    public function it_throws_provider_exception_on_401_without_retry(): void
    {
        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response(
                ['error' => ['message' => 'Invalid API key']],
                401
            ),
        ]);

        try {
            $this->provider->embed('test');
            $this->fail('Expected AIProviderException');
        } catch (AIProviderException $e) {
            $this->assertSame(401, $e->statusCode);
        }

        // Only 1 request made — no retry on client errors
        Http::assertSentCount(1);
    }

    // ── Model metadata ────────────────────────────────────────────────────

    /** @test */
    public function it_returns_correct_embedding_model_name(): void
    {
        $this->assertSame('text-embedding-3-small', $this->provider->getEmbeddingModel());
    }

    /** @test */
    public function it_returns_correct_dimension_count(): void
    {
        $this->assertSame(1536, $this->provider->getDimensions());
    }

    // ── EmbeddingResponse helpers ─────────────────────────────────────────

    /** @test */
    public function embedding_response_norm_is_approximately_one_for_unit_vector(): void
    {
        // OpenAI returns normalised vectors (unit vectors) by default
        // A unit vector has norm ≈ 1.0
        $dim    = 1536;
        $value  = 1.0 / sqrt($dim);
        $vector = array_fill(0, $dim, $value);

        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response(
                $this->openAIEmbeddingPayload([$vector]),
                200
            ),
        ]);

        $response = $this->provider->embed('test');

        $this->assertEqualsWithDelta(1.0, $response->norm(), delta: 0.001);
    }

    /** @test */
    public function embedding_response_cosine_similarity_with_itself_is_one(): void
    {
        $vector = $this->makeVector(1536, seed: 42);

        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response(
                $this->openAIEmbeddingPayload([$vector]),
                200
            ),
        ]);

        $response = $this->provider->embed('test');

        $this->assertEqualsWithDelta(1.0, $response->cosineSimilarity($vector), delta: 0.0001);
    }

    /** @test */
    public function cosine_similarity_throws_on_dimension_mismatch(): void
    {
        $vector = $this->makeVector(1536);

        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response(
                $this->openAIEmbeddingPayload([$vector]),
                200
            ),
        ]);

        $response = $this->provider->embed('test');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Dimension mismatch/');

        $response->cosineSimilarity(array_fill(0, 512, 0.1)); // wrong dimensions
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * @param  float[][]  $vectors
     */
    private function openAIEmbeddingPayload(array $vectors, int $promptTokens = 10): array
    {
        $data = [];
        foreach ($vectors as $i => $vector) {
            $data[] = ['object' => 'embedding', 'index' => $i, 'embedding' => $vector];
        }

        return [
            'object' => 'list',
            'data'   => $data,
            'model'  => 'text-embedding-3-small',
            'usage'  => ['prompt_tokens' => $promptTokens, 'total_tokens' => $promptTokens],
        ];
    }

    /**
     * Generate a deterministic float vector of given size.
     *
     * @return float[]
     */
    private function makeVector(int $dimensions, int $seed = 1): array
    {
        $vector = [];
        $norm   = 0.0;

        for ($i = 0; $i < $dimensions; $i++) {
            $v        = sin($seed * 1000 + $i) * 0.1;
            $vector[] = $v;
            $norm    += $v * $v;
        }

        // Normalise to unit length (matches OpenAI output)
        $norm = sqrt($norm);
        return $norm > 0 ? array_map(fn ($v) => $v / $norm, $vector) : $vector;
    }
}
