<?php

namespace Jqhtml\Laravel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class InstallCommand extends Command
{
    protected $signature = 'jqhtml:install';
    protected $description = 'Install jqhtml dependencies and configure Laravel for jqhtml components';

    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    public function handle(): int
    {
        $this->info('Installing jqhtml for Laravel...');
        $this->newLine();

        // Step 1: Update package.json
        $this->updatePackageJson();

        // Step 2: Configure vite.config.js
        $this->configureVite();

        // Step 3: Configure app.js
        $this->configureAppJs();

        // Step 4: Create jqhtml directory
        $this->createJqhtmlDirectory();

        // Done
        $this->newLine();
        $this->info('jqhtml installed successfully!');
        $this->newLine();
        $this->line('Next steps:');
        $this->line('  1. Run: <comment>npm install</comment>');
        $this->line('  2. Create components in: <comment>resources/jqhtml/</comment>');
        $this->line('  3. Register components in: <comment>resources/js/app.js</comment>');
        $this->line('  4. Run: <comment>npm run dev</comment>');
        $this->newLine();

        return Command::SUCCESS;
    }

    protected function updatePackageJson(): void
    {
        $this->components->task('Updating package.json', function () {
            $packageJsonPath = base_path('package.json');

            if (!$this->files->exists($packageJsonPath)) {
                $this->warn('package.json not found. Skipping npm dependency setup.');
                return false;
            }

            $packages = json_decode($this->files->get($packageJsonPath), true);

            $packages['dependencies'] = array_merge(
                $packages['dependencies'] ?? [],
                [
                    '@jqhtml/core' => 'latest',
                    '@jqhtml/vite-plugin' => 'latest',
                    'jquery' => '^3.7.0',
                ]
            );

            $this->files->put(
                $packageJsonPath,
                json_encode($packages, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
            );

            return true;
        });
    }

    protected function configureVite(): void
    {
        $this->components->task('Configuring vite.config.js', function () {
            $viteConfigPath = base_path('vite.config.js');

            if (!$this->files->exists($viteConfigPath)) {
                // Create from stub
                $stub = $this->files->get(__DIR__ . '/../../stubs/vite.config.js.stub');
                $this->files->put($viteConfigPath, $stub);
                return true;
            }

            $content = $this->files->get($viteConfigPath);

            // Check if already configured
            if (str_contains($content, '@jqhtml/vite-plugin')) {
                return true; // Already configured
            }

            // Inject import at top (after other imports)
            if (preg_match('/^(import .+from .+;?\n)+/m', $content, $matches)) {
                $importBlock = $matches[0];
                $jqhtmlImport = "import jqhtml from '@jqhtml/vite-plugin';\n";
                $content = str_replace($importBlock, $importBlock . $jqhtmlImport, $content);
            } else {
                // Fallback: add at very top
                $content = "import jqhtml from '@jqhtml/vite-plugin';\n" . $content;
            }

            // Inject plugin into plugins array
            if (preg_match('/plugins:\s*\[/', $content)) {
                $content = preg_replace(
                    '/plugins:\s*\[/',
                    "plugins: [\n        jqhtml(),",
                    $content
                );
            }

            $this->files->put($viteConfigPath, $content);
            return true;
        });
    }

    protected function configureAppJs(): void
    {
        $this->components->task('Configuring resources/js/app.js', function () {
            $appJsPath = resource_path('js/app.js');

            if (!$this->files->exists($appJsPath)) {
                // Create from stub
                $stub = $this->files->get(__DIR__ . '/../../stubs/app.js.stub');
                $this->files->put($appJsPath, $stub);
                return true;
            }

            $content = $this->files->get($appJsPath);

            // Check if already configured
            if (str_contains($content, '@jqhtml/core')) {
                return true; // Already configured
            }

            $jqhtmlSetup = <<<'JS'

// jqhtml setup
import $ from 'jquery';
window.jQuery = window.$ = $;

import jqhtml, { boot, init_jquery_plugin } from '@jqhtml/core';
init_jquery_plugin($);

// Register your jqhtml components here:
// import MyComponent from '../jqhtml/MyComponent.jqhtml';
// jqhtml.register(MyComponent);

// Boot jqhtml when DOM is ready
$(document).ready(async () => {
    await boot();
});
JS;

            // Append to end of file
            $content = rtrim($content) . "\n" . $jqhtmlSetup . "\n";

            $this->files->put($appJsPath, $content);
            return true;
        });
    }

    protected function createJqhtmlDirectory(): void
    {
        $this->components->task('Creating resources/jqhtml directory', function () {
            $jqhtmlDir = resource_path('jqhtml');

            if (!$this->files->isDirectory($jqhtmlDir)) {
                $this->files->makeDirectory($jqhtmlDir, 0755, true);
            }

            // Create .gitkeep to preserve empty directory
            $gitkeep = $jqhtmlDir . '/.gitkeep';
            if (!$this->files->exists($gitkeep)) {
                $this->files->put($gitkeep, '');
            }

            return true;
        });
    }
}
