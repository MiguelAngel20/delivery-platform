<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class ProductImageStorage
{
    public function store(UploadedFile $file): string
    {
        return $file->store('products/images', 'public');
    }

    public function replace(Product $product, UploadedFile $file): string
    {
        $previousPath = $product->image_path;
        $path = $this->store($file);

        $product->update(['image_path' => $path]);

        if (is_string($previousPath) && $previousPath !== '' && $previousPath !== $path) {
            $this->deleteIfUnused($previousPath);
        }

        return $path;
    }

    public function deleteIfUnused(string $path): void
    {
        $stillReferenced = Product::withTrashed()
            ->where('image_path', $path)
            ->exists();

        if ($stillReferenced) {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    public function url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return Storage::disk('public')->url($path);
    }
}
