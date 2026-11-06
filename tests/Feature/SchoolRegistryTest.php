<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Diocese;
use App\Models\District;
use App\Models\JurisdictionAssignment;
use App\Models\Parish;
use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Services\SchoolProfileContactService;
use Database\Seeders\DiocesanStructureSeeder;
use Database\Seeders\IdentityAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_captures_school_profile_headteacher_and_contacts(): void
    {
        $admin = $this->superAdmin();
        $payload = $this->schoolPayload();

        $response = $this->actingAs($admin)->post('/administration/schools', $payload);

        $school = School::query()->where('emis_number', 'EMIS-001')->firstOrFail();
        $response->assertRedirect('/administration/schools/'.$school->id);

        $this->assertDatabaseHas('school_contacts', ['school_id' => $school->id, 'email' => 'school@example.test']);
        $this->assertSame('Mary School Head', $school->currentHeadteacher->person_name);
        $this->actingAs($admin)->get(route('schools.index'))->assertOk()->assertSee('School head')->assertSee('Mary School Head')->assertSee('data-telephone="+256700100100"', false)->assertSee('school-contact-popup');
        $this->assertStringStartsWith('SYS-', $school->diocesan_code);
        $this->assertDatabaseHas('school_status_histories', ['school_id' => $school->id, 'new_status' => School::STATUS_ACTIVE]);
        $this->assertDatabaseHas('audit_events', ['auditable_type' => School::class, 'auditable_id' => $school->id, 'action' => 'school.created']);
    }

    public function test_school_head_is_profile_data_and_changes_preserve_leadership_history(): void
    {
        $this->superAdmin();
        $school = $this->makeSchool();
        $old = $school->leadershipHistories()->create(['position' => 'headteacher', 'person_name' => 'Original Head', 'telephone' => '0700111222', 'email' => 'head@example.test', 'effective_from' => now()->subYear(), 'is_current' => true]);
        $school->leadershipHistories()->create(['position' => 'deputy', 'person_name' => 'Deputy Head', 'effective_from' => now(), 'is_current' => true]);
        $this->assertSame('Original Head', $school->currentHeadteacher->person_name);
        app(SchoolProfileContactService::class)->sync($school, ['headteacher_name' => 'Original Head']);
        $this->assertSame('0700111222', $old->fresh()->telephone);
        app(SchoolProfileContactService::class)->sync($school, ['headteacher_name' => 'New Head', 'headteacher_phone' => '0700999888', 'headteacher_email' => 'newhead@example.test']);
        $this->assertFalse($old->fresh()->is_current);
        $this->assertSame('New Head', $school->fresh()->currentHeadteacher->person_name);
        $this->assertDatabaseCount('school_returns', 0);
    }

    public function test_school_identifiers_are_unique(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin)->post('/administration/schools', $this->schoolPayload())->assertSessionDoesntHaveErrors();

        $this->actingAs($admin)
            ->from('/administration/schools/create')
            ->post('/administration/schools', [
                ...$this->schoolPayload(),
                'official_name' => 'duplicate school',
            ])
            ->assertRedirect('/administration/schools/create')
            ->assertSessionHasErrors('emis_number');
    }

    public function test_parish_coordinator_can_only_view_assigned_parish_school(): void
    {
        $this->seed([IdentityAccessSeeder::class, DiocesanStructureSeeder::class]);

        $role = tap(Role::firstOrCreate(['slug' => 'parish-education-coordinator'], ['name' => 'Scoped test reviewer']), fn ($role) => $role->permissions()->sync(Permission::whereIn('slug', ['schools.view.assigned', 'returns.view', 'returns.review'])->pluck('id')));
        $coordinator = User::factory()->create();
        $coordinator->roles()->attach($role);

        $assignedParish = Parish::query()->where('code', 'KIT')->firstOrFail();
        $otherParish = Parish::query()->where('code', 'BWA')->firstOrFail();

        JurisdictionAssignment::query()->create([
            'user_id' => $coordinator->id,
            'parish_id' => $assignedParish->id,
            'assignment_type' => JurisdictionAssignment::TYPE_PARISH_COORDINATOR,
            'effective_from' => now()->subDay()->toDateString(),
            'is_active' => true,
        ]);

        $visibleSchool = $this->makeSchool(['parish_id' => $assignedParish->id, 'diocesan_code' => 'VISIBLE']);
        $hiddenSchool = $this->makeSchool(['parish_id' => $otherParish->id, 'diocesan_code' => 'HIDDEN']);

        $this->actingAs($coordinator)->get('/administration/schools/'.$visibleSchool->id)->assertOk();
        $this->actingAs($coordinator)->get('/administration/schools/'.$hiddenSchool->id)->assertForbidden();
    }

    public function test_removed_status_field_cannot_be_changed_by_submitting_legacy_payload(): void
    {
        $admin = $this->superAdmin();
        $school = $this->makeSchool();

        $this->actingAs($admin)
            ->put('/administration/schools/'.$school->id, [
                ...$this->schoolPayload(),
                'diocesan_code' => $school->diocesan_code,
                'operational_status' => School::STATUS_UNDER_REVIEW,
                'status_reason' => 'verification required',
            ])
            ->assertRedirect('/administration/schools/'.$school->id);

        $this->assertSame(School::STATUS_ACTIVE, $school->fresh()->operational_status);
        $this->assertDatabaseMissing('school_status_histories', ['school_id' => $school->id, 'new_status' => School::STATUS_UNDER_REVIEW]);
        $this->assertSame(1, AuditEvent::query()->where('action', 'school.updated')->count());
    }

    public function test_registry_renders_filters_and_searches_emis_and_registration(): void
    {
        $admin = $this->superAdmin();
        $school = $this->makeSchool(['emis_number' => 'SEARCH-EMIS', 'ministry_registration_number' => 'SEARCH-REG']);
        $this->actingAs($admin)->get(route('schools.index'))->assertOk()->assertDontSee('Parishes represented')->assertSee('Search')->assertSee($school->official_name);
        foreach (['SEARCH-EMIS', 'SEARCH-REG'] as $term) {
            $this->get(route('schools.index', ['search' => $term]))->assertOk()->assertViewHas('schools', fn ($schools) => $schools->total() === 1);
        }
        $this->get(route('schools.index', ['search' => 'NO-MATCH']))->assertOk()->assertSee('No schools found');
    }

    private function superAdmin(): User
    {
        $this->seed([IdentityAccessSeeder::class, DiocesanStructureSeeder::class]);

        $role = Role::query()->where('slug', Role::SUPER_ADMINISTRATOR)->firstOrFail();
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    private function makeSchool(array $overrides = []): School
    {
        $diocese = Diocese::query()->firstOrFail();
        $parish = Parish::query()->firstOrFail();
        $district = District::query()->firstOrFail();

        return School::query()->create([
            'diocese_id' => $diocese->id,
            'parish_id' => $parish->id,
            'district_id' => $district->id,
            'official_name' => 'test school',
            'diocesan_code' => 'TEST-'.fake()->unique()->numberBetween(1000, 9999),
            'school_level' => 'primary',
            'ownership' => 'diocesan',
            'operational_status' => School::STATUS_ACTIVE,
            ...$overrides,
        ]);
    }

    private function schoolPayload(): array
    {
        $diocese = Diocese::query()->firstOrFail();
        $parish = Parish::query()->firstOrFail();
        $district = District::query()->firstOrFail();

        return [
            'diocese_id' => $diocese->id,
            'parish_id' => $parish->id,
            'district_id' => $district->id,
            'official_name' => 'st mary primary school',
            'headteacher_name' => 'Mary School Head',
            'diocesan_code' => 'SCH-001',
            'emis_number' => 'EMIS-001',
            'ministry_registration_number' => 'MOE-001',
            'school_level' => 'primary',
            'ownership' => 'diocesan',
            'operational_status' => School::STATUS_ACTIVE,
            'profile_verified' => 1,
            'contact' => [
                'telephone' => '+256700100100',
                'email' => 'school@example.test',
                'postal_address' => 'p.o. box 1 masaka',
            ],
        ];
    }
}
