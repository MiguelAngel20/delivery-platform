<?php

use App\Support\WebpImageConverter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('converts jpeg uploads to resized webp without keeping the original', function () {
    $file = UploadedFile::fake()->image('photo.jpg', 2000, 1500);

    $path = app(WebpImageConverter::class)->store($file, 'products/images', 1200, 1200);

    expect($path)
        ->toStartWith('products/images/')
        ->toEndWith('.webp');

    Storage::disk('public')->assertExists($path);

    $absolute = Storage::disk('public')->path($path);
    $info = getimagesize($absolute);

    expect($info)->not->toBeFalse()
        ->and($info[0])->toBeLessThanOrEqual(1200)
        ->and($info[1])->toBeLessThanOrEqual(1200)
        ->and($info['mime'] ?? null)->toBe('image/webp');
});

test('converts png uploads to webp only', function () {
    $file = UploadedFile::fake()->image('logo.png', 800, 800);

    $path = app(WebpImageConverter::class)->store($file, 'businesses/logos', 512, 512, 85);

    expect($path)->toEndWith('.webp');

    Storage::disk('public')->assertExists($path);
    expect(Storage::disk('public')->files('businesses/logos'))->toHaveCount(1);
});
