<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceNotFoundTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_role_returns_404(): void
    {
        Role::query()->firstOrCreate(['id' => Role::ADMIN], ['name' => 'Administrator', 'slug' => 'administrator']);
        Role::query()->firstOrCreate(['id' => Role::SELLER], ['name' => 'Seller', 'slug' => 'seller']);
        Role::query()->firstOrCreate(['id' => Role::USER], ['name' => 'User', 'slug' => 'user']);

        $admin = User::factory()->create([
            'role_id' => Role::ADMIN,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/getRole/999999')
            ->assertStatus(404);
    }

    public function test_missing_property_type_returns_404(): void
    {
        Role::query()->firstOrCreate(['id' => Role::ADMIN], ['name' => 'Administrator', 'slug' => 'administrator']);
        Role::query()->firstOrCreate(['id' => Role::SELLER], ['name' => 'Seller', 'slug' => 'seller']);
        Role::query()->firstOrCreate(['id' => Role::USER], ['name' => 'User', 'slug' => 'user']);

        $admin = User::factory()->create([
            'role_id' => Role::ADMIN,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/getPropertyType/999999')
            ->assertStatus(404);
    }
}
