<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\ProcessingJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesUsers;
use Tests\TestCase;

class ProcessingJobTest extends TestCase
{
    use RefreshDatabase, CreatesUsers;

    public function test_owner_can_view_document_jobs(): void
    {
        $student  = $this->createStudent();
        $document = Document::factory()->create(['user_id' => $student->id]);

        ProcessingJob::factory()->count(2)->create(['document_id' => $document->id]);

        $this->actingAs($student)
            ->getJson("/api/documents/{$document->id}/jobs")
            ->assertOk()
            ->assertJsonStructure(['document_id', 'status', 'jobs']);
    }

    public function test_non_owner_cannot_view_private_document_jobs(): void
    {
        $owner    = $this->createStudent();
        $intruder = $this->createStudent();
        $document = Document::factory()->create([
            'user_id'    => $owner->id,
            'visibility' => 'private',
        ]);

        ProcessingJob::factory()->create(['document_id' => $document->id]);

        $this->actingAs($intruder)
            ->getJson("/api/documents/{$document->id}/jobs")
            ->assertForbidden();
    }

    public function test_admin_can_list_all_failed_jobs(): void
    {
        $admin    = $this->createAdmin();
        $student  = $this->createStudent();
        $document = Document::factory()->create(['user_id' => $student->id]);

        ProcessingJob::factory()->count(3)->create([
            'document_id' => $document->id,
            'status'      => 'failed',
        ]);

        $this->actingAs($admin)
            ->getJson('/api/admin/jobs?status=failed')
            ->assertOk();
    }

    public function test_non_admin_cannot_access_admin_jobs(): void
    {
        $student = $this->createStudent();

        $this->actingAs($student)
            ->getJson('/api/admin/jobs')
            ->assertForbidden();
    }
}
