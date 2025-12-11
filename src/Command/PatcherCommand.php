<?php

declare(strict_types=1);

namespace Sidworks\ComposerPatcher\Command;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to manually run the Sidworks Composer Patcher
 */
class PatcherCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->setName('sidworks:composer-patcher')
            ->setDescription('Manually apply all patches from the patches directory')
            ->addOption(
                'create',
                'c',
                InputOption::VALUE_NONE,
                'Create a new patch from modified file(s)'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->getIO();

        $composer = $this->getComposer();
        $baseDir = dirname($composer->getConfig()->get('vendor-dir'));
        $patchesDir = $baseDir . DIRECTORY_SEPARATOR . 'patches';

        // Change to base directory
        chdir($baseDir);

        // Check if --create flag was used
        if ($input->getOption('create')) {
            if (!file_exists($patchesDir)) {
                $io->writeError('<error>Patches directory not found: ' . $patchesDir . '</error>');
                return 1;
            }
            return $this->createPatch($io, $baseDir, $patchesDir);
        }

        // Load the patcher
        $patcherClass = 'Sidworks\\ComposerPatcher\\Model\\Patcher';
        if (!class_exists($patcherClass)) {
            $io->writeError('<error>Patcher class not found. Make sure sidworks/composer-patcher is installed.</error>');
            return 1;
        }

        $patcher = new $patcherClass();

        // Activate the patcher
        $patcher->activate($composer, $io);

        if (!file_exists($patchesDir)) {
            $patcher->noPatchesMessage();
            return 0;
        }

        // Determine if we're in dev mode (default to true for manual runs)
        $devMode = true;

        // Run the patcher - apply all patches
        $reflection = new \ReflectionClass($patcher);
        $method = $reflection->getMethod('applyPatches');
        $method->invoke($patcher, $devMode);

        return 0;
    }

    private function createPatch($io, string $baseDir, string $patchesDir): int
    {
        // Check if we're in a git repository
        if (!is_dir($baseDir . DIRECTORY_SEPARATOR . '.git')) {
            $io->writeError('<error>Not a git repository. Patches can only be created from git repositories.</error>');
            return 1;
        }

        $io->write('<info>Creating a new patch from modified files...</info>');
        $io->write('');

        // Ask for the file path
        $io->write('<question>Enter the file path (relative to project root, e.g., vendor/shopware/core/Checkout/Cart/Price/Struct/CartPrice.php):</question>');
        $filePath = trim($io->ask('> '));

        if (empty($filePath)) {
            $io->writeError('<error>File path is required.</error>');
            return 1;
        }

        // Normalize path
        $filePath = trim($filePath);
        $fullPath = $baseDir . DIRECTORY_SEPARATOR . $filePath;

        if (!file_exists($fullPath)) {
            $io->writeError('<error>File not found: ' . $filePath . '</error>');
            return 1;
        }

        // For vendor files, we need to get the original from the package
        // Use a much faster approach: extract from composer's dist cache
        $tempDir = sys_get_temp_dir();
        $modifiedPath = $tempDir . DIRECTORY_SEPARATOR . 'sidworks_composer_patcher_modified_' . md5($filePath) . '.php';
        $originalPath = $tempDir . DIRECTORY_SEPARATOR . 'sidworks_composer_patcher_original_' . md5($filePath) . '.php';

        // Save the modified file
        if (!copy($fullPath, $modifiedPath)) {
            $io->writeError('<error>Failed to create backup of modified file.</error>');
            return 1;
        }

        $io->write('<comment>Extracting original file from package...</comment>');

        // Extract package name and relative file path
        if (preg_match('#vendor/([^/]+/[^/]+)/(.+)$#', $filePath, $matches)) {
            $package = $matches[1];
            $relativeFilePath = $matches[2];
            $io->write("<comment>Package: $package</comment>");

            // Get package installation path
            $packagePath = $baseDir . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . $package;

            // Check if package has .git directory (dev install)
            if (is_dir($packagePath . DIRECTORY_SEPARATOR . '.git')) {
                // Git checkout the original file
                $gitCommand = sprintf(
                    'cd %s && git show HEAD:%s 2>&1',
                    escapeshellarg($packagePath),
                    escapeshellarg($relativeFilePath)
                );
                $originalContent = shell_exec($gitCommand);

                if ($originalContent && !str_contains($originalContent, 'fatal:')) {
                    file_put_contents($originalPath, $originalContent);
                } else {
                    // Fallback: just copy the current file (will show no changes)
                    $io->writeError('<error>Failed to get original from git. File may not be tracked.</error>');
                    copy($fullPath, $originalPath);
                }
            } else {
                // For dist installs, we need to reinstall (but much faster with --no-scripts --no-autoloader)
                $io->write('<comment>Quick reinstall (no scripts)...</comment>');
                shell_exec(sprintf(
                    'cd %s && composer reinstall %s --no-scripts --no-autoloader --no-interaction > /dev/null 2>&1',
                    escapeshellarg($baseDir),
                    escapeshellarg($package)
                ));

                // Copy the restored original
                if (file_exists($fullPath)) {
                    copy($fullPath, $originalPath);
                } else {
                    @unlink($modifiedPath);
                    $io->writeError('<error>Failed to restore original file.</error>');
                    return 1;
                }
            }
        } else {
            @unlink($modifiedPath);
            $io->writeError('<error>Could not parse vendor package path.</error>');
            return 1;
        }

        // Create diff between original and modified
        $io->write('<comment>Creating diff...</comment>');

        $diffCmd = sprintf(
            'diff -u %s %s',
            escapeshellarg($originalPath),
            escapeshellarg($modifiedPath)
        );

        $patchContent = shell_exec($diffCmd . ' 2>&1');

        // Restore the modified file
        copy($modifiedPath, $fullPath);

        // Clean up temp files
        @unlink($originalPath);
        @unlink($modifiedPath);

        // diff returns exit code 1 when files differ, which is what we want
        // Empty output means files are identical
        if ($patchContent === null || $patchContent === '' || trim($patchContent) === '') {
            $io->writeError('<error>Failed to generate patch. No differences found between files.</error>');
            $io->write('<comment>The file may not have been modified, or composer reinstall restored your changes.</comment>');
            return 1;
        }

        // Fix patch headers to use git-style a/ b/ format
        // Replace the file paths in the diff headers
        $lines = explode("\n", $patchContent);
        if (isset($lines[0]) && str_starts_with($lines[0], '---')) {
            $lines[0] = '--- a/' . $filePath;
        }
        if (isset($lines[1]) && str_starts_with($lines[1], '+++')) {
            $lines[1] = '+++ b/' . $filePath;
        }
        $patchContent = implode("\n", $lines);

        // Ask for patch name
        $io->write('');
        $io->write('<question>Enter patch name (e.g., fix-price-calculation or shopware/cart-price-fix):</question>');
        $patchName = trim($io->ask('> '));

        if (empty($patchName)) {
            $io->writeError('<error>Patch name is required.</error>');
            return 1;
        }

        $patchName = trim($patchName);

        // Ensure .patch extension
        if (!str_ends_with($patchName, '.patch')) {
            $patchName .= '.patch';
        }

        // Create subdirectory if patch name contains slashes
        $patchPath = $patchesDir . DIRECTORY_SEPARATOR . $patchName;
        $patchDir = dirname($patchPath);

        if (!is_dir($patchDir)) {
            if (!mkdir($patchDir, 0755, true)) {
                $io->writeError('<error>Failed to create directory: ' . $patchDir . '</error>');
                return 1;
            }
        }

        // Write patch file
        if (file_put_contents($patchPath, $patchContent) === false) {
            $io->writeError('<error>Failed to write patch file: ' . $patchPath . '</error>');
            return 1;
        }

        $io->write('');
        $io->write('<info>✓ Patch created successfully!</info>');
        $io->write('<comment>Location: patches/' . $patchName . '</comment>');
        $io->write('<comment>The patch will be applied automatically on next composer operation.</comment>');
        $io->write('');

        return 0;
    }
}
