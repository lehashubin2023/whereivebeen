<?php

namespace Tests\Feature\Addon;

use App\Actions\Addon\ResolveAddonDownload;
use App\Exceptions\Addon\AddonSourceNotFoundException;
use App\Exceptions\Addon\InvalidAddonTocException;
use App\Support\Addon\AddonPackage;
use Tests\TestCase;
use ZipArchive;

class PackageAddonTest extends TestCase
{
    private string $source;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->source = sys_get_temp_dir().'/addon-source-'.uniqid();
        $this->directory = 'downloads-test-'.uniqid();

        config()->set('addon.directory', $this->directory);

        mkdir($this->source.'/Libs', 0755, true);
        file_put_contents($this->source.'/WhereIveBeen.toc', "## Interface: 20506\n## Version: 2.7\n\nCore.lua\n");
        file_put_contents($this->source.'/Core.lua', 'return true');
        file_put_contents($this->source.'/Libs/dkjson.lua', 'return {}');
        file_put_contents($this->source.'/.gitignore', 'ignored');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory(public_path($this->directory));
        $this->removeDirectory($this->source);

        parent::tearDown();
    }

    public function test_it_packages_the_addon_under_the_required_folder_name()
    {
        $archive = (new AddonPackage)->package($this->source);

        $this->assertSame(public_path($this->directory.'/WhereIveBeen-2.7.zip'), $archive);
        $this->assertFileExists($archive);
        $this->assertEqualsCanonicalizing(
            ['WhereIveBeen/WhereIveBeen.toc', 'WhereIveBeen/Core.lua', 'WhereIveBeen/Libs/dkjson.lua'],
            $this->entries($archive)
        );
    }

    public function test_it_accepts_a_version_override_and_drops_previous_archives()
    {
        (new AddonPackage)->package($this->source);
        (new AddonPackage)->package($this->source, '3.0');

        $this->assertFileDoesNotExist(public_path($this->directory.'/WhereIveBeen-2.7.zip'));
        $this->assertFileExists(public_path($this->directory.'/WhereIveBeen-3.0.zip'));
    }

    public function test_it_fails_when_the_source_directory_is_missing()
    {
        $this->expectException(AddonSourceNotFoundException::class);

        (new AddonPackage)->package($this->source.'-nope');
    }

    public function test_it_fails_when_the_toc_has_no_version()
    {
        file_put_contents($this->source.'/WhereIveBeen.toc', "## Interface: 20506\n\nCore.lua\n");

        $this->expectException(InvalidAddonTocException::class);

        (new AddonPackage)->package($this->source);
    }

    public function test_the_command_packages_the_addon()
    {
        $this->artisan('addon:package', ['--source' => $this->source])
            ->expectsOutputToContain('WhereIveBeen-2.7.zip')
            ->assertSuccessful();

        $this->assertFileExists(public_path($this->directory.'/WhereIveBeen-2.7.zip'));
    }

    public function test_the_command_fails_on_a_missing_source()
    {
        $this->artisan('addon:package', ['--source' => $this->source.'-nope'])->assertFailed();
    }

    public function test_resolver_reports_the_packaged_archive()
    {
        (new AddonPackage)->package($this->source);

        $download = (new ResolveAddonDownload(new AddonPackage))->exec();

        $this->assertTrue($download['available']);
        $this->assertSame('2.7', $download['version']);
        $this->assertSame('WhereIveBeen-2.7.zip', $download['file']);
        $this->assertSame('/'.$this->directory.'/WhereIveBeen-2.7.zip', $download['url']);
        $this->assertGreaterThan(0, $download['size']);
    }

    public function test_resolver_picks_the_highest_version()
    {
        mkdir(public_path($this->directory), 0755, true);
        touch(public_path($this->directory.'/WhereIveBeen-1.9.zip'));
        touch(public_path($this->directory.'/WhereIveBeen-1.10.zip'));

        $this->assertSame('1.10', (new ResolveAddonDownload(new AddonPackage))->exec()['version']);
    }

    public function test_resolver_reports_a_missing_archive()
    {
        $download = (new ResolveAddonDownload(new AddonPackage))->exec();

        $this->assertFalse($download['available']);
        $this->assertNull($download['version']);
        $this->assertNull($download['url']);
    }

    /**
     * @return array<int, string>
     */
    private function entries(string $archive): array
    {
        $zip = new ZipArchive;
        $zip->open($archive);

        $entries = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[] = (string) $zip->getNameIndex($i);
        }

        $zip->close();

        return $entries;
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (glob($directory.'/{,.}[!.]*', GLOB_BRACE) ?: [] as $path) {
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($directory);
    }
}
