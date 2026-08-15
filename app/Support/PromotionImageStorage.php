<?php

namespace App\Support;

use App\Models\Promotion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class PromotionImageStorage
{
    public function store(UploadedFile $file): string
    {
        return $file->store('promotions/images', 'public');
    }

    public function replace(Promotion $promotion, UploadedFile $file): string
    {
        $previousPath = $promotion->image_path;
        $path = $this->store($file);

        $promotion->update(['image_path' => $path]);

        if (is_string($previousPath) && $previousPath !== '' && $previousPath !== $path) {
            $this->deleteIfUnused($previousPath);
        }

        return $path;
    }

    public function deleteIfUnused(string $path): void
    {
        $stillReferenced = Promotion::withTrashed()
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
