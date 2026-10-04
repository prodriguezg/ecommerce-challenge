<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageControllerTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->actingAs(User::factory()->admin()->create());
        $this->product = Product::factory()->create(['currency_id' => Currency::factory()->usd()->create()->getKey()]);
        Inventory::factory()->create(['product_id' => $this->product->getKey()]);
    }

    public function test_image_is_content_validated_stored_with_generated_name_and_served_safely(): void
    {
        Storage::disk('public')->put('products/original.png', $this->pngBytes());
        $this->product->update(['image_path' => 'products/original.png', 'image_mime_type' => 'image/png', 'image_size' => strlen($this->pngBytes())]);
        $file = UploadedFile::fake()->createWithContent('attacker.php.png', $this->pngBytes());

        $this->post("/api/v1/admin/products/{$this->product->getKey()}/image", ['image' => $file], ['Accept' => 'application/json'])
            ->assertOk();

        $product = $this->product->fresh();
        $this->assertNotSame('attacker.php.png', basename($product->image_path));
        Storage::disk('public')->assertMissing('products/original.png');
        Storage::disk('public')->assertExists($product->image_path);
        $this->get("/api/v1/products/{$product->getKey()}/image")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_failed_replacement_preserves_the_previous_image(): void
    {
        Storage::disk('public')->put('products/original.png', $this->pngBytes());
        $this->product->update(['image_path' => 'products/original.png', 'image_mime_type' => 'image/png', 'image_size' => strlen($this->pngBytes())]);

        $bad = UploadedFile::fake()->createWithContent('bad.jpg', '<?php echo 1;');
        $this->post("/api/v1/admin/products/{$this->product->getKey()}/image", ['image' => $bad], ['Accept' => 'application/json'])
            ->assertUnsupportedMediaType();

        $this->assertSame('products/original.png', $this->product->fresh()->image_path);
        Storage::disk('public')->assertExists('products/original.png');
    }

    private function pngBytes(): string
    {
        return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    }
}
