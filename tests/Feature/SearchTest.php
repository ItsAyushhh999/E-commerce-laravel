<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_finds_a_product_by_name_prefix(): void
    {
        Product::factory()->create(['name' => 'Running Shoes']);
        Product::factory()->create(['name' => 'Winter Jacket']);

        $response = $this->getJson('/api/v1/search?type=products&search=Running');

        $response->assertOk();
        $response->assertJsonFragment(['name' => 'Running Shoes']);
        $response->assertJsonMissing(['name' => 'Winter Jacket']);
    }

    public function test_it_finds_a_product_by_exact_id(): void
    {
        $product = Product::factory()->create(['name' => 'Trail Backpack']);

        $response = $this->getJson('/api/v1/search?type=products&search='.$product->id);

        $response->assertOk();
        $response->assertJsonFragment(['id' => $product->id]);
    }

    public function test_search_field_is_required(): void
    {
        $response = $this->getJson('/api/v1/search?type=products');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('search');
    }

    public function test_type_field_is_required(): void
    {
        $response = $this->getJson('/api/v1/search?search=shoes');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('type');
    }

    public function test_it_finds_a_variant_by_sku_prefix_and_loads_its_product(): void
    {
        $product = Product::factory()->create(['name' => 'Trail Runner']);
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'SKU-001',
        ]);

        $response = $this->getJson('/api/v1/search?type=variants&search=SKU-001');

        $response->assertOk();
        $response->assertJsonFragment(['sku' => 'SKU-001']);
        $response->assertJsonFragment(['name' => 'Trail Runner']); // eager-loaded product
    }

    public function test_it_finds_an_attribute_value_or_its_attribute_name(): void
    {
        $colorAttribute = Attribute::factory()->create(['name' => 'Color']);
        AttributeValue::factory()->create([
            'attribute_id' => $colorAttribute->id,
            'value' => 'Red',
        ]);

        // Matches directly on the value itself
        $byValue = $this->getJson('/api/v1/search?type=attributes&search=Red');
        $byValue->assertOk();
        $byValue->assertJsonFragment(['value' => 'Red']);

        // Also matches via the parent attribute's name
        $byAttributeName = $this->getJson('/api/v1/search?type=attributes&search=Color');
        $byAttributeName->assertOk();
        $byAttributeName->assertJsonFragment(['value' => 'Red']);
    }

    public function test_it_finds_an_order_by_shipping_address_prefix(): void
    {
        $user = User::factory()->create();
        Order::factory()->create([
            'user_id' => $user->id,
            'shipping_address' => 'Lainchaur, Kathmandu, Nepal',
        ]);

        $response = $this->getJson('/api/v1/search?type=orders&search=Lainchaur');

        $response->assertOk();
        $response->assertJsonFragment(['shipping_address' => 'Lainchaur, Kathmandu, Nepal']);
    }

    public function test_it_finds_an_order_via_the_related_users_name(): void
    {
        $user = User::factory()->create(['name' => 'Ayushh MDR']);
        Order::factory()->create(['user_id' => $user->id]);

        $response = $this->getJson('/api/v1/search?type=orders&search=Ayushh');

        $response->assertOk();
        $response->assertJsonFragment(['name' => 'Ayushh MDR']); // via with('user')
    }

    public function test_orders_are_filtered_by_from_and_to_date_range(): void
    {
        $user = User::factory()->create();

        $inRange = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'created_at' => '2026-03-15 00:00:00',
        ]);

        $outOfRange = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'created_at' => '2025-12-01 00:00:00',
        ]);

        $response = $this->getJson(
            '/api/v1/search?type=orders&search=completed&from=2026-01-01&to=2026-06-30'
        );

        $response->assertOk();
        $response->assertJsonFragment(['id' => $inRange->id]);
        $response->assertJsonMissing(['id' => $outOfRange->id]);
    }

    public function test_results_are_paginated_at_250_per_page(): void
    {
        Product::factory()->count(300)->create(['name' => 'Bulk Item']);

        $response = $this->getJson('/api/v1/search?type=products&search=Bulk');

        $response->assertOk();
        $response->assertJsonCount(250, 'results.data');
    }

    public function test_it_rejects_an_invalid_search_type(): void
    {
        $response = $this->getJson('/api/v1/search?type=foobar&search=shoes');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('type');
    }

    public function test_it_returns_an_empty_result_set_when_nothing_matches(): void
    {
        Product::factory()->create(['name' => 'Running Shoes']);

        $response = $this->getJson('/api/v1/search?type=products&search=Nonexistentxyz123');

        $response->assertOk();
        $response->assertJsonCount(0, 'results.data');
    }

    public function test_search_term_with_sql_wildcards_is_escaped(): void
    {
        Product::factory()->create(['name' => 'Running Shoes']);
        Product::factory()->create(['name' => 'Winter Jacket']);
        Product::factory()->create(['name' => '50% Off Sale']);

        // A literal "%" in the search term shouldn't act as a wildcard
        // matching every row — it should only match products containing
        // a literal "%" character.
        $response = $this->getJson('/api/v1/search?type=products&search='.urlencode('%'));

        $response->assertOk();
        $response->assertJsonCount(1, 'results.data');
        $response->assertJsonFragment(['name' => '50% Off Sale']);
    }

    public function test_search_is_case_insensitive(): void
    {
        Product::factory()->create(['name' => 'Running Shoes']);

        $response = $this->getJson('/api/v1/search?type=products&search=running');

        $response->assertOk();
        $response->assertJsonFragment(['name' => 'Running Shoes']);
    }

    public function test_pagination_returns_remaining_results_on_page_two(): void
    {
        Product::factory()->count(300)->create(['name' => 'Bulk Item']);

        $response = $this->getJson('/api/v1/search?type=products&search=Bulk&page=2');

        $response->assertOk();
        $response->assertJsonCount(50, 'results.data');
    }

    public function test_it_rejects_an_invalid_date_format(): void
    {
        $response = $this->getJson('/api/v1/search?type=orders&search=completed&from=not-a-date');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('from');
    }

    public function test_it_rejects_a_from_date_after_the_to_date(): void
    {
        $response = $this->getJson(
            '/api/v1/search?type=orders&search=completed&from=2026-06-30&to=2026-01-01'
        );

        $response->assertStatus(422);
    }
}
