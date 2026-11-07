<?php

namespace Tests\Feature;

use App\Models\DocumentCategory;
use App\Models\ManagedDocument;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PhaseNineToFourteenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_upload_and_download_private_document(): void
    {
        Storage::fake('local');
        $this->seed(PhaseNineToFourteenSeeder::class);
        $user = $this->userWithDocumentPermissions();

        $this->actingAs($user)
            ->post(route('documents.store'), [
                'document_category_id' => DocumentCategory::query()->firstOrFail()->id,
                'title' => 'school licence',
                'sensitivity' => 'internal',
                'file' => UploadedFile::fake()->create('licence.pdf', 12, 'application/pdf'),
            ])
            ->assertRedirect(route('documents.index'));

        $document = ManagedDocument::query()->with('currentVersion')->firstOrFail();
        Storage::disk('local')->assertExists($document->currentVersion->path);

        $this->actingAs($user)
            ->get(route('documents.download', $document))
            ->assertOk();
    }

    public function test_unauthorized_user_cannot_download_document_by_guessing_url(): void
    {
        Storage::fake('local');
        $this->seed(PhaseNineToFourteenSeeder::class);
        $owner = $this->userWithDocumentPermissions();
        $this->actingAs($owner)->post(route('documents.store'), [
            'document_category_id' => DocumentCategory::query()->firstOrFail()->id,
            'title' => 'confidential policy',
            'sensitivity' => 'confidential',
            'file' => UploadedFile::fake()->create('policy.pdf', 12, 'application/pdf'),
        ]);
        $document = ManagedDocument::query()->firstOrFail();

        $this->actingAs(User::factory()->create())
            ->get(route('documents.download', $document))
            ->assertForbidden();
    }

    private function userWithDocumentPermissions(): User
    {
        $role = Role::firstOrCreate(['slug' => Role::SUPER_ADMINISTRATOR], ['name' => 'Admin']);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
