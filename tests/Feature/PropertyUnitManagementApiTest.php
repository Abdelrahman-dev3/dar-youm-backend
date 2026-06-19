<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyUnitManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_property_manager_can_update_and_delete_owned_property(): void
    {
        $manager = User::factory()->create(['role' => 'property_manager']);
        $property = $this->propertyFor($manager);

        $this->actingAs($manager, 'sanctum')
            ->putJson("/api/properties/{$property->id}", [
                'name' => 'Updated Property',
                'address' => 'Updated address',
                'city' => 'Riyadh',
                'property_type' => 'villa',
                'status' => 'maintenance',
                'total_units' => 8,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Updated Property')
            ->assertJsonPath('data.status', 'maintenance');

        $this->assertDatabaseHas('properties', [
            'id' => $property->id,
            'name' => 'Updated Property',
            'status' => 'maintenance',
        ]);

        $this->actingAs($manager, 'sanctum')
            ->deleteJson("/api/properties/{$property->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('properties', ['id' => $property->id]);
    }

    public function test_property_manager_can_update_and_delete_unit_for_owned_property(): void
    {
        $manager = User::factory()->create(['role' => 'property_manager']);
        $property = $this->propertyFor($manager);
        $unit = Unit::create([
            'property_id' => $property->id,
            'unit_number' => 'A-101',
            'unit_name' => 'Original Suite',
            'unit_type' => 'studio',
            'bedrooms' => 1,
            'bathrooms' => 1,
            'max_guests' => 2,
            'base_price' => 250,
            'currency' => 'SAR',
            'status' => 'available',
        ]);

        $this->actingAs($manager, 'sanctum')
            ->putJson("/api/units/{$unit->id}", [
                'unit_number' => 'A-102',
                'unit_name' => 'Updated Suite',
                'unit_type' => '1br',
                'status' => 'cleaning',
                'base_price' => 325,
                'currency' => 'SAR',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.unit_number', 'A-102')
            ->assertJsonPath('data.status', 'cleaning');

        $this->assertDatabaseHas('units', [
            'id' => $unit->id,
            'unit_number' => 'A-102',
            'status' => 'cleaning',
        ]);

        $this->actingAs($manager, 'sanctum')
            ->deleteJson("/api/units/{$unit->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('units', ['id' => $unit->id]);
    }

    private function propertyFor(User $manager): Property
    {
        return Property::create([
            'user_id' => $manager->id,
            'name' => 'Test Property',
            'address' => 'Test address',
            'city' => 'Riyadh',
            'country' => 'Saudi Arabia',
            'property_type' => 'apartment',
            'total_units' => 5,
            'status' => 'active',
            'is_listed' => true,
        ]);
    }
}
