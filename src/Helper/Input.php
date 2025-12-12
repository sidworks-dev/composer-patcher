<?php

declare(strict_types=1);

namespace Sidworks\ComposerPatcher\Helper;

use Composer\IO\IOInterface;

class Input
{
    public function __construct(
        private readonly IOInterface $io
    ) {}

    /**
     * Ask for Y/n confirmation with proper validation
     */
    public function confirm(string $question, bool $default = true): bool
    {
        $defaultHint = $default ? 'Y/n' : 'y/N';

        while (true) {
            $answer = $this->io->ask("<question>{$question} [{$defaultHint}]</question> ");

            if ($answer === null || trim($answer) === '') {
                // Echo back the default choice
                $this->io->write($default ? '<info>yes</info>' : '<info>no</info>');
                return $default;
            }

            $answer = strtolower(trim($answer));

            if (in_array($answer, ['y', 'yes'], true)) {
                return true;
            }

            if (in_array($answer, ['n', 'no'], true)) {
                return false;
            }

            $this->io->writeError('<error>Please answer yes, y, no, or n.</error>');
        }
    }

    /**
     * Ask for text input with optional default and validation
     */
    public function ask(string $question, ?string $default = null): string
    {
        while (true) {
            if ($default !== null) {
                $this->io->write("<question>{$question} [{$default}]:</question>");
            } else {
                $this->io->write("<question>{$question}:</question>");
            }

            $answer = $this->io->ask('> ');
            $answer = trim((string) $answer);

            if ($answer === '' && $default !== null) {
                // Echo back the default choice
                $this->io->write("<info>{$default}</info>");
                return $default;
            }

            if ($answer !== '') {
                return $answer;
            }

            $this->io->writeError('<error>Input is required.</error>');
            $this->io->write('');
        }
    }
}
