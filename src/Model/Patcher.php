<?php
declare(strict_types=1);

namespace Sidworks\ComposerPatcher\Model;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\Capable;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

class Patcher implements PluginInterface, EventSubscriberInterface, Capable
{
    private const PATCH_OPTIONS = ' --whitespace=nowarn --ignore-space-change --ignore-whitespace ';
    private const SEPARATOR = '────────────────────────────────────────────────────────────';

    private string $patchesDir = '';
    private string $baseDir = '';
    private array $errorOutput = [];
    private array $successOutput = [];
    private string $version = 'unknown';
    private string $description = 'Sidworks Composer Patcher';

    // Cache for composer.json data
    private static ?array $composerData = null;

    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => 'onPostInstallCommand',
            ScriptEvents::POST_UPDATE_CMD => 'onPostInstallCommand',
        ];
    }

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->baseDir = dirname($composer->getConfig()->get('vendor-dir'));
        $this->patchesDir = $this->baseDir . DIRECTORY_SEPARATOR . 'patches';
        $this->loadVersion();
    }

    public function onPostInstallCommand(Event $event): void
    {
        $this->applyPatches($event->isDevMode());
    }

    public function noPatchesMessage(): void
    {
        $output = [
            '',
            self::SEPARATOR,
            "\033[36mℹ {$this->description} {$this->version}\033[0m",
            self::SEPARATOR,
            " \033[90mPatches directory not found: {$this->patchesDir}\033[0m",
            " \033[90mNo patches to apply\033[0m",
            self::SEPARATOR,
            ''
        ];
        echo implode(PHP_EOL, $output);
    }

    protected function applyPatches(bool $devmode = false): void
    {
        if (empty($this->baseDir) || !is_dir($this->patchesDir)) {
            $this->noPatchesMessage();
            return;
        }

        chdir($this->baseDir);

        // Collect all patches at once instead of multiple calls
        $patches = $this->findAllPatches($devmode);

        if (!empty($patches)) {
            $this->applyPatchList($patches);
        }

        $this->message();
    }

    private function findAllPatches(bool $devmode): array
    {
        $patches = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $this->patchesDir,
                \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS
            ),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $filename = $file->getFilename();

            // Check for .patch files
            if (str_ends_with($filename, '.patch')) {
                $patches[] = $file->getPathname();
            }
            // Check for .patch.dev files only in dev mode
            elseif ($devmode && str_ends_with($filename, '.patch.dev')) {
                $patches[] = $file->getPathname();
            }
        }

        // Sort for consistent ordering
        sort($patches, SORT_STRING);

        return $patches;
    }

    protected function applyPatchList(array $patches): void
    {
        // Pre-calculate relative paths once
        $relativePaths = [];
        $patchesDirLen = strlen($this->patchesDir) + 1;

        foreach ($patches as $patch) {
            $relativePaths[$patch] = strpos($patch, $this->patchesDir) === 0
                ? substr($patch, $patchesDirLen)
                : basename($patch);
        }

        // Revert patches in reverse order (suppress output)
        foreach (array_reverse($patches) as $patch) {
            shell_exec('git apply --reverse' . self::PATCH_OPTIONS . escapeshellarg($patch) . ' 2>&1 > /dev/null');
        }

        // Apply patches and collect results grouped by folder
        $successByFolder = [];
        $errorsByFolder = [];

        foreach ($patches as $patch) {
            $result = shell_exec('git apply' . self::PATCH_OPTIONS . escapeshellarg($patch) . ' 2>&1');
            $relativePath = $relativePaths[$patch];

            // Extract folder name (everything before first /)
            $folder = 'root';
            if (str_contains($relativePath, '/')) {
                $folder = substr($relativePath, 0, strpos($relativePath, '/'));
            }

            if ($result && str_contains($result, 'error')) {
                if (!isset($errorsByFolder[$folder])) {
                    $errorsByFolder[$folder] = [];
                }
                $errorsByFolder[$folder][] = [
                    'patch' => $relativePath,
                    'error' => $result
                ];
            } else {
                if (!isset($successByFolder[$folder])) {
                    $successByFolder[$folder] = [];
                }
                $successByFolder[$folder][] = $relativePath;
            }
        }

        // Store grouped results for message output
        $this->successOutput = $successByFolder;
        $this->errorOutput = $errorsByFolder;
    }

    private function loadVersion(): void
    {
        // Cache composer.json data to avoid multiple reads
        if (self::$composerData === null) {
            $composerFile = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'composer.json';

            if (file_exists($composerFile)) {
                $contents = file_get_contents($composerFile);
                self::$composerData = $contents ? json_decode($contents, true) : [];
            } else {
                self::$composerData = [];
            }
        }

        $this->version = self::$composerData['version'] ?? 'unknown';
        $this->description = self::$composerData['extra']['display-name'] ?? 'Sidworks Composer Patcher';
    }

    protected function message(): void
    {
        // Count patches across all folders
        $successCount = 0;
        foreach ($this->successOutput as $patches) {
            $successCount += count($patches);
        }

        $errorCount = 0;
        foreach ($this->errorOutput as $patches) {
            $errorCount += count($patches);
        }

        $hasErrors = !empty($this->errorOutput);
        $totalCount = $successCount + $errorCount;

        // Build output buffer to reduce echo calls
        $output = [
            '',
            self::SEPARATOR,
            $hasErrors
                ? "\033[33m ⚠ {$this->description} {$this->version}\033[0m"
                : "\033[32m ✓ {$this->description} {$this->version}\033[0m",
            self::SEPARATOR,
            " Total: {$totalCount} patches | \033[32mSuccess: {$successCount}\033[0m | \033[31mFailed: {$errorCount}\033[0m",
            self::SEPARATOR
        ];

        if (!empty($this->successOutput)) {
            $output[] = '';
            $output[] = "\033[1;32mSuccessfully Applied:\033[0m";

            // Sort folders alphabetically
            ksort($this->successOutput);

            foreach ($this->successOutput as $folder => $patches) {
                $output[] = " \033[1;36m{$folder}\033[0m";
                foreach ($patches as $patch) {
                    // Extract just the filename after the folder
                    $filename = str_contains($patch, '/')
                        ? substr($patch, strpos($patch, '/') + 1)
                        : $patch;
                    $output[] = "  \033[32m✓\033[0m {$filename}";
                }
            }
        }

        if ($hasErrors) {
            $output[] = '';
            $output[] = "\033[1;31mFailed Patches:\033[0m";

            // Sort folders alphabetically
            ksort($this->errorOutput);

            foreach ($this->errorOutput as $folder => $patches) {
                $output[] = " \033[1;36m{$folder}\033[0m";

                foreach ($patches as $patchData) {
                    // Extract just the filename after the folder
                    $filename = str_contains($patchData['patch'], '/')
                        ? substr($patchData['patch'], strpos($patchData['patch'], '/') + 1)
                        : $patchData['patch'];

                    $output[] = "  \033[31mX\033[0m {$filename}";

                    // Process error lines efficiently
                    $errorLines = explode("\n", trim($patchData['error']));
                    foreach ($errorLines as $errorLine) {
                        $errorLine = trim($errorLine);
                        if ($errorLine !== '') {
                            $output[] = "    \033[33m{$errorLine}\033[0m";
                        }
                    }
                }
            }

            $output[] = '';
            $output[] = self::SEPARATOR;
            $output[] = "\033[31m✗ Patch application failed - please review errors above\033[0m";
            $output[] = self::SEPARATOR;
            $output[] = '';

            // Output everything at once, then exit
            echo implode(PHP_EOL, $output);
            exit(1);
        }

        $output[] = '';
        $output[] = "\033[32m✓ All patches applied successfully!\033[0m";
        $output[] = self::SEPARATOR;
        $output[] = '';

        // Single output call
        echo implode(PHP_EOL, $output);
    }

    public function getCapabilities(): array
    {
        return [
            'Composer\Plugin\Capability\CommandProvider' => 'Sidworks\ComposerPatcher\Model\CommandProvider',
        ];
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        // No-op
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        // No-op
    }
}
