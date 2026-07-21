<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected User $acmeUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->acmeUser = User::where('email', 'acme@example.com')->firstOrFail();
    }

    public function test_a_user_may_edit_their_own_organizations_records(): void
    {
        $supplier = Organization::where('slug', 'acme')->firstOrFail()->suppliers()->firstOrFail();

        $this->assertTrue($this->acmeUser->can('view', $supplier));
        $this->assertTrue($this->acmeUser->can('update', $supplier));
        $this->assertTrue($this->acmeUser->can('delete', $supplier));
        $this->assertTrue($this->acmeUser->can('create', Supplier::class));
    }

    public function test_a_user_may_not_touch_another_organizations_records(): void
    {
        $foreign = Organization::where('slug', 'beta')->firstOrFail()->suppliers()->firstOrFail();

        $this->assertFalse($this->acmeUser->can('view', $foreign));
        $this->assertFalse($this->acmeUser->can('update', $foreign));
        $this->assertFalse($this->acmeUser->can('delete', $foreign));
        $this->assertFalse($this->acmeUser->can('attach', $foreign));
        $this->assertFalse($this->acmeUser->can('detach', $foreign));
    }

    public function test_a_user_without_an_organization_can_do_nothing(): void
    {
        $orphan = User::create(['name' => 'Orphan', 'email' => 'orphan@example.com', 'password' => 'secret']);
        $supplier = Organization::where('slug', 'acme')->firstOrFail()->suppliers()->firstOrFail();

        $this->assertFalse($orphan->can('viewAny', Supplier::class));
        $this->assertFalse($orphan->can('create', Supplier::class));
        $this->assertFalse($orphan->can('update', $supplier));
    }
}
