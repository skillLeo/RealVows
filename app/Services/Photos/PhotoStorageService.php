<?php

namespace App\Services\Photos;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

class PhotoStorageService
{
    public const DISK = 'local';

    public const MAX_BYTES = 8 * 1024 * 1024;

    private const LONGEST_EDGE = 1600;

    private const THUMBNAIL_EDGE = 400;

    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp', 'image/heic'];

    /**
     * Process one upload and put it on the private disk.
     *
     * Three things happen here that matter more than they look:
     *
     * 1. The MIME type is read from the file's own bytes, never from the
     *    extension or the browser-supplied header. A .jpg that is actually a
     *    PHP script is the oldest upload attack there is.
     *
     * 2. Re-encoding through the image library strips EXIF wholesale — which
     *    includes the GPS coordinates phones attach by default. Publishing a
     *    member's home location on a marriage platform because nobody thought
     *    about metadata is not a risk worth carrying.
     *
     * 3. Everything lands on a private disk. There is no public path, so
     *    there is nothing to guess and nothing to hotlink.
     *
     * @return array<string, mixed>
     */
    public function store(UploadedFile $file, int $profileId, string $slot): array
    {
        $realMime = $this->realMimeType($file);

        $manager = $this->manager();
        $image = $manager->decodePath($file->getRealPath());

        // scaleDown never enlarges, so a small photo keeps its own resolution
        // rather than being stretched into a blurry 1600px version.
        $image->scaleDown(width: self::LONGEST_EDGE, height: self::LONGEST_EDGE);

        $directory = "profiles/{$profileId}/photos";
        $base = $slot.'-'.Str::random(24);

        $jpegPath = "{$directory}/{$base}.jpg";
        $webpPath = "{$directory}/{$base}.webp";
        $thumbPath = "{$directory}/{$base}-thumb.jpg";

        $disk = Storage::disk(self::DISK);

        // JPEG as the fallback, WebP as the one actually served to anything
        // modern — roughly a third of the bytes over a Caribbean 4G link.
        $disk->put($jpegPath, (string) $image->encode(new JpegEncoder(quality: 82)));
        $disk->put($webpPath, (string) $image->encode(new WebpEncoder(quality: 80)));

        $thumb = $manager->decodePath($file->getRealPath())
            ->scaleDown(width: self::THUMBNAIL_EDGE, height: self::THUMBNAIL_EDGE);

        $disk->put($thumbPath, (string) $thumb->encode(new JpegEncoder(quality: 78)));

        return [
            'path' => $jpegPath,
            'webp_path' => $webpPath,
            'thumbnail_path' => $thumbPath,
            'original_filename' => Str::limit($file->getClientOriginalName(), 180, ''),
            'mime' => $realMime,
            'size_bytes' => $disk->size($jpegPath),
            'width' => $image->width(),
            'height' => $image->height(),
        ];
    }

    public function storeVerificationSelfie(UploadedFile $file, int $profileId): array
    {
        $this->realMimeType($file);

        $image = $this->manager()
            ->decodePath($file->getRealPath())
            ->scaleDown(width: self::LONGEST_EDGE, height: self::LONGEST_EDGE);

        $directory = "profiles/{$profileId}/verification";
        $base = 'selfie-'.Str::random(24);

        $disk = Storage::disk(self::DISK);
        $disk->put("{$directory}/{$base}.jpg", (string) $image->encode(new JpegEncoder(quality: 84)));
        $disk->put("{$directory}/{$base}.webp", (string) $image->encode(new WebpEncoder(quality: 82)));

        return [
            'selfie_path' => "{$directory}/{$base}.jpg",
            'selfie_webp_path' => "{$directory}/{$base}.webp",
        ];
    }

    public function delete(?string ...$paths): void
    {
        $disk = Storage::disk(self::DISK);

        foreach (array_filter($paths) as $path) {
            $disk->delete($path);
        }
    }

    /**
     * GD rather than Imagick: it is the extension actually present on
     * Hostinger shared hosting, and everything this pipeline does — scale,
     * re-encode, strip metadata — GD handles.
     */
    private function manager(): ImageManager
    {
        return new ImageManager(new Driver());
    }

    /**
     * The file's actual type, read from its contents.
     */
    public function realMimeType(UploadedFile $file): string
    {
        $mime = @mime_content_type($file->getRealPath()) ?: '';

        abort_unless(
            in_array($mime, self::ALLOWED_MIME, true),
            422,
            'That file does not look like a photo. JPEG, PNG or WebP, please.'
        );

        return $mime;
    }
}
