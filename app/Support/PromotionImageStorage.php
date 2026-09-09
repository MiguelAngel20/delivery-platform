<?php

namespace App\Support;

use App\Models\Promotion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class PromotionImageStorage
{
    private const MAX_WIDTH = 1200;

    private const MAX_HEIGHT = 1200;

    public function __construct(
        private readonly WebpImageConverter $converter,
    ) {}

    public function store(UploadedFile $file): string
    {
        return $this->converter->store(
            $file,
            'promotions/images',
            self::MAX_WIDTH,
            self::MAX_HEIGHT,
            attribute: 'image',
        );
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
