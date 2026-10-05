<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class ProductImageController extends Controller
{
    public function __invoke(string $product): Response
    {
        $model = Product::findOrFail($product);
        $headers = ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'public, max-age=3600'];
        if ($model->image_path === null || ! Storage::disk('public')->exists($model->image_path)) {
            $placeholder = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 480"><rect width="640" height="480" fill="#eee"/><path d="M160 340l100-110 70 70 55-60 95 100z" fill="#bbb"/><circle cx="230" cy="155" r="38" fill="#bbb"/></svg>';

            return response($placeholder, 200, ['Content-Type' => 'image/svg+xml', ...$headers]);
        }

        return response(
            Storage::disk('public')->get($model->image_path),
            200,
            ['Content-Type' => $model->image_mime_type, 'Content-Length' => (string) $model->image_size, ...$headers],
        );
    }
}
