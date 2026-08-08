<?php

namespace App\Support;

/**
 * Resolve the Vite build directory for hosts whose web root is the
 * project root (cPanel public_html) rather than /public.
 */
final class ViteBuildDirectory
{
    public const DEFAULT = 'build';

    public const WEB_ROOT_MIRROR = '../build';

    /**
     * Path relative to public_path() where manifest.json lives.
     * Prefer public/build; fall back to project-root /build.
     */
    public static function relativeToPublic(string $publicManifest, string $rootManifest): string
    {
        if (is_file($publicManifest)) {
            return self::DEFAULT;
        }

        if (is_file($rootManifest)) {
            return self::WEB_ROOT_MIRROR;
        }

        return self::DEFAULT;
    }

    /**
     * Browser URLs must stay /build/... even when the manifest is read from ../build.
     */
    public static function toPublicUrlPath(string $path): string
    {
        return str_starts_with($path, '../') ? substr($path, 3) : $path;
    }

    public static function usesWebRootMirror(string $directory): bool
    {
        return $directory === self::WEB_ROOT_MIRROR;
    }
}
