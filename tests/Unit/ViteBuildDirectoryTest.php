<?php

namespace Tests\Unit;

use App\Support\ViteBuildDirectory;
use PHPUnit\Framework\TestCase;

class ViteBuildDirectoryTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'vite-build-'.uniqid('', true);
        mkdir($this->tmp.'/public/build', 0777, true);
        mkdir($this->tmp.'/build', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tmp);

        parent::tearDown();
    }

    public function test_prefers_public_manifest_when_both_exist(): void
    {
        file_put_contents($this->tmp.'/public/build/manifest.json', '{}');
        file_put_contents($this->tmp.'/build/manifest.json', '{}');

        $this->assertSame('build', ViteBuildDirectory::relativeToPublic(
            $this->tmp.'/public/build/manifest.json',
            $this->tmp.'/build/manifest.json',
        ));
    }

    public function test_falls_back_to_project_root_build(): void
    {
        file_put_contents($this->tmp.'/build/manifest.json', '{}');

        $this->assertSame('../build', ViteBuildDirectory::relativeToPublic(
            $this->tmp.'/public/build/manifest.json',
            $this->tmp.'/build/manifest.json',
        ));
    }

    public function test_keeps_default_when_neither_manifest_exists(): void
    {
        $this->assertSame('build', ViteBuildDirectory::relativeToPublic(
            $this->tmp.'/public/build/manifest.json',
            $this->tmp.'/build/manifest.json',
        ));
    }

    public function test_strips_parent_prefix_from_asset_urls(): void
    {
        $this->assertSame(
            'build/assets/app.js',
            ViteBuildDirectory::toPublicUrlPath('../build/assets/app.js'),
        );

        $this->assertSame(
            'build/assets/app.js',
            ViteBuildDirectory::toPublicUrlPath('build/assets/app.js'),
        );
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = scandir($directory) ?: [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$item;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
