<?php

namespace App\Support;

use App\Models\Business;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class BusinessBannerStorage
{
    private const MAX_WIDTH = 1600;

    private const MAX_HEIGHT = 900;

    public function __construct(
        private readonly WebpImageConverter $converter,
    ) {}

    public function store(UploadedFile $file): string
    {
        return $this->converter->store(
            $file,
            'businesses/banners',
            self::MAX_WIDTH,
            self::MAX_HEIGHT,
            80,
            'banner',
        );
    }

    public function replace(Business $business, UploadedFile $file): string
    {
        $previousPath = $business->banner_path;
        $path = $this->store($file);

        $business->update(['banner_path' => $path]);

        if (is_string($previousPath) && $previousPath !== '' && $previousPath !== $path) {
            $this->deleteIfUnused($previousPath);
        }

        return $path;
    }

    public function deleteIfUnused(string $path): void
    {
        $stillReferenced = Business::withTrashed()
            ->where('banner_path', $path)
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
