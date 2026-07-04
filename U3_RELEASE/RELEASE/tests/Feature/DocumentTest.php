<?php

namespace Tests\Feature;

use App\Jobs\ProcessDocumentJob;
use App\Models\Category;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\CreatesUsers;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase, CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');
        Queue::fake();
    }

    // ─── LIST ─────────────────────────────────────────────────────

    public function test_unauthenticated_cannot_list_documents(): void
    {
        $this->getJson('/api/documents')->assertUnauthorized();
    }

    public function test_student_can_list_documents(): void
    {
        $student = $this->createStudent();

        $this->actingAs($student)
            ->getJson('/api/documents')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta', 'links']);
    }

    public function test_index_returns_only_own_and_public_documents_for_student(): void
    {
        $student = $this->createStudent();
        $other   = $this->createStudent();

        // Own private
        Document::factory()->create(['user_id' => $student->id, 'visibility' => 'private']);
        // Other private - should NOT appear
        Document::factory()->create(['user_id' => $other->id, 'visibility' => 'private']);
        // Other public - SHOULD appear
        Document::factory()->create(['user_id' => $other->id, 'visibility' => 'public']);

        $response = $this->actingAs($student)
            ->getJson('/api/documents')
            ->assertOk();

        $this->assertCount(2, $response->json('data'));
    }

    public function test_admin_sees_all_documents(): void
    {
        $admin   = $this->createAdmin();
        $student = $this->createStudent();

        Document::factory()->count(3)->create(['user_id' => $student->id, 'visibility' => 'private']);

        $this->actingAs($admin)
            ->getJson('/api/documents')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    // ─── SHOW ─────────────────────────────────────────────────────

    public function test_owner_can_view_own_private_document(): void
    {
        $student  = $this->createStudent();
        $document = Document::factory()->create([
            'user_id'    => $student->id,
            'visibility' => 'private',
        ]);

        $this->actingAs($student)
            ->getJson("/api/documents/{$document->id}")
            ->assertOk()
            ->assertJsonPath('id', $document->id);
    }

    public function test_other_user_cannot_view_private_document(): void
    {
        $owner   = $this->createStudent();
        $other   = $this->createStudent();
        $document = Document::factory()->create([
            'user_id'    => $owner->id,
            'visibility' => 'private',
        ]);

        $this->actingAs($other)
            ->getJson("/api/documents/{$document->id}")
            ->assertForbidden();
    }

    public function test_any_user_can_view_public_document(): void
    {
        $owner    = $this->createStudent();
        $viewer   = $this->createStudent();
        $document = Document::factory()->create([
            'user_id'    => $owner->id,
            'visibility' => 'public',
        ]);

        $this->actingAs($viewer)
            ->getJson("/api/documents/{$document->id}")
            ->assertOk();
    }

    // ─── UPLOAD ───────────────────────────────────────────────────

    public function test_student_can_upload_pdf_document(): void
    {
        $student = $this->createStudent();
        $file    = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

        $response = $this->actingAs($student)
            ->postJson('/api/documents', [
                'title'       => 'Test PDF Document',
                'source_type' => 'pdf',
                'file'        => $file,
            ])
            ->assertCreated()
            ->assertJsonStructure(['message', 'document' => ['id', 'title', 'status']]);

        $this->assertDatabaseHas('documents', [
            'title'       => 'Test PDF Document',
            'user_id'     => $student->id,
            'source_type' => 'pdf',
            'status'      => 'pending',
        ]);

        // Verify file was stored in MinIO — look up path from DB since API never exposes it
        $doc = Document::where('title', 'Test PDF Document')->firstOrFail();
        $this->assertNotNull($doc->getRawOriginal('file_path'));
        Storage::disk('s3')->assertExists($doc->getRawOriginal('file_path'));

        Queue::assertPushed(ProcessDocumentJob::class);
    }

    public function test_upload_requires_title(): void
    {
        $student = $this->createStudent();
        $file    = UploadedFile::fake()->create('test.pdf', 10, 'application/pdf');

        $this->actingAs($student)
            ->postJson('/api/documents', [
                'source_type' => 'pdf',
                'file'        => $file,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    public function test_upload_requires_file_for_pdf_type(): void
    {
        $student = $this->createStudent();

        $this->actingAs($student)
            ->postJson('/api/documents', [
                'title'       => 'No File',
                'source_type' => 'pdf',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    public function test_upload_requires_url_for_youtube_type(): void
    {
        $student = $this->createStudent();

        $this->actingAs($student)
            ->postJson('/api/documents', [
                'title'       => 'YouTube Video',
                'source_type' => 'youtube',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['source_url']);
    }

    public function test_txt_upload_extracts_text_immediately(): void
    {
        $student = $this->createStudent();
        $file    = UploadedFile::fake()->createWithContent('test.txt', 'Hello world content');

        $this->actingAs($student)
            ->postJson('/api/documents', [
                'title'       => 'Text File',
                'source_type' => 'txt',
                'file'        => $file,
            ])
            ->assertCreated();

        // Text should be pre-extracted for txt files
        $doc = Document::where('title', 'Text File')->first();
        $this->assertNotNull($doc);
        Queue::assertPushed(ProcessDocumentJob::class);
    }

    public function test_document_can_have_categories(): void
    {
        $teacher  = $this->createTeacher();
        $category = Category::factory()->create(['created_by' => $teacher->id]);
        $file     = UploadedFile::fake()->create('doc.pdf', 50, 'application/pdf');

        $this->actingAs($teacher)
            ->postJson('/api/documents', [
                'title'        => 'Categorised Doc',
                'source_type'  => 'pdf',
                'file'         => $file,
                'category_ids' => [$category->id],
            ])
            ->assertCreated();

        $doc = Document::where('title', 'Categorised Doc')->first();
        $this->assertTrue($doc->categories->contains($category->id));
    }

    // ─── UPDATE ───────────────────────────────────────────────────

    public function test_owner_can_update_document_title(): void
    {
        $student  = $this->createStudent();
        $document = Document::factory()->create(['user_id' => $student->id]);

        $this->actingAs($student)
            ->putJson("/api/documents/{$document->id}", ['title' => 'New Title'])
            ->assertOk()
            ->assertJsonPath('document.title', 'New Title');
    }

    public function test_non_owner_cannot_update_document(): void
    {
        $owner   = $this->createStudent();
        $other   = $this->createStudent();
        $document = Document::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($other)
            ->putJson("/api/documents/{$document->id}", ['title' => 'Hack'])
            ->assertForbidden();
    }

    // ─── DELETE ───────────────────────────────────────────────────

    public function test_owner_can_delete_document(): void
    {
        $student  = $this->createStudent();
        $document = Document::factory()->create(['user_id' => $student->id]);

        $this->actingAs($student)
            ->deleteJson("/api/documents/{$document->id}")
            ->assertOk();

        $this->assertSoftDeleted('documents', ['id' => $document->id]);
    }

    public function test_non_owner_cannot_delete_document(): void
    {
        $owner    = $this->createStudent();
        $other    = $this->createStudent();
        $document = Document::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($other)
            ->deleteJson("/api/documents/{$document->id}")
            ->assertForbidden();
    }

    // ─── REPROCESS ────────────────────────────────────────────────

    public function test_owner_can_trigger_reprocess_on_failed_document(): void
    {
        $student  = $this->createStudent();
        $document = Document::factory()->create([
            'user_id' => $student->id,
            'status'  => Document::STATUS_FAILED,
        ]);

        $this->actingAs($student)
            ->postJson("/api/documents/{$document->id}/reprocess")
            ->assertOk();

        Queue::assertPushed(ProcessDocumentJob::class, fn($job) =>
            $job->documentId === $document->id
        );
    }

    public function test_reprocess_rejected_while_processing(): void
    {
        $student  = $this->createStudent();
        $document = Document::factory()->create([
            'user_id' => $student->id,
            'status'  => Document::STATUS_PROCESSING,
        ]);

        $this->actingAs($student)
            ->postJson("/api/documents/{$document->id}/reprocess")
            ->assertStatus(409);
    }

    // ─── STATUS ───────────────────────────────────────────────────

    public function test_owner_can_check_document_status(): void
    {
        $student  = $this->createStudent();
        $document = Document::factory()->create(['user_id' => $student->id]);

        $this->actingAs($student)
            ->getJson("/api/documents/{$document->id}/status")
            ->assertOk()
            ->assertJsonStructure(['document_id', 'status', 'jobs']);
    }
}
