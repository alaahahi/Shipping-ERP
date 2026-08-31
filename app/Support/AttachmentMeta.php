<?php

namespace App\Support;

final class AttachmentMeta
{
    /**
     * @return array{
     *     has_attachment: bool,
     *     attachment_url: string|null,
     *     attachment_name: string|null,
     *     attachment_is_image: bool,
     *     attachment_is_pdf: bool
     * }
     */
    public static function payload(?string $url, ?string $name = null, ?string $path = null, ?int $version = null): array
    {
        $has = filled($url) || filled($path);

        return [
            'has_attachment' => $has,
            'attachment_url' => $has ? self::versionedUrl($url, $version) : null,
            'attachment_name' => $name ?: ($path ? basename($path) : null),
            'attachment_is_image' => self::isImage($name, $path),
            'attachment_is_pdf' => self::isPdf($name, $path),
        ];
    }

    public static function versionedUrl(?string $url, ?int $version = null): ?string
    {
        if (! $url) {
            return null;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').'v='.($version ?: time());
    }

    public static function isImage(?string $name, ?string $path = null): bool
    {
        return in_array(self::extension($name, $path), ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
    }

    public static function isPdf(?string $name, ?string $path = null): bool
    {
        return self::extension($name, $path) === 'pdf';
    }

    private static function extension(?string $name, ?string $path = null): string
    {
        return strtolower(pathinfo((string) ($name ?: $path ?: ''), PATHINFO_EXTENSION));
    }
}
