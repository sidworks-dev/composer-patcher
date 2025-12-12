<?php

declare(strict_types=1);

namespace Sidworks\ComposerPatcher\Helper;

class Output
{
    private const SEPARATOR = '────────────────────────────────────────────────────────────';
    private const SEPARATOR_LIGHT = '┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄';

    // ANSI color codes
    private const GREEN = "\033[32m";
    private const RED = "\033[31m";
    private const YELLOW = "\033[33m";
    private const CYAN = "\033[36m";
    private const GRAY = "\033[90m";
    private const DIM = "\033[2m";
    private const BOLD = "\033[1m";
    private const BOLD_GREEN = "\033[1;32m";
    private const BOLD_RED = "\033[1;31m";
    private const BOLD_CYAN = "\033[1;36m";
    private const BOLD_YELLOW = "\033[1;33m";
    private const RESET = "\033[0m";

    private array $lines = [];

    public function header(string $title, string $status = 'info'): self
    {
        $icon = match ($status) {
            'success' => self::GREEN . '✓' . self::RESET,
            'warning' => self::YELLOW . '⚠' . self::RESET,
            'error' => self::RED . '✗' . self::RESET,
            default => self::CYAN . 'ℹ' . self::RESET,
        };

        $this->lines[] = '';
        $this->lines[] = self::SEPARATOR;
        $this->lines[] = " {$icon} {$title}";
        $this->lines[] = self::SEPARATOR;

        return $this;
    }

    public function stats(int $total, int $success, int $failed): self
    {
        $successIcon = $success > 0 ? self::GREEN . '●' . self::RESET : self::GRAY . '○' . self::RESET;
        $failedIcon = $failed > 0 ? self::RED . '●' . self::RESET : self::GRAY . '○' . self::RESET;

        $this->lines[] = sprintf(
            " %s %d applied  %s %d failed  %s%d total%s",
            $successIcon,
            $success,
            $failedIcon,
            $failed,
            self::DIM,
            $total,
            self::RESET
        );
        $this->lines[] = self::SEPARATOR;

        return $this;
    }

    public function separator(): self
    {
        $this->lines[] = self::SEPARATOR;
        return $this;
    }

    public function blank(): self
    {
        $this->lines[] = '';
        return $this;
    }

    public function info(string $message): self
    {
        $this->lines[] = ' ' . self::GRAY . $message . self::RESET;
        return $this;
    }

    public function success(string $message): self
    {
        $this->lines[] = self::GREEN . "✓ {$message}" . self::RESET;
        return $this;
    }

    public function error(string $message): self
    {
        $this->lines[] = self::RED . "✗ {$message}" . self::RESET;
        return $this;
    }

    public function sectionTitle(string $title, string $type = 'default'): self
    {
        $color = match ($type) {
            'success' => self::BOLD_GREEN,
            'error' => self::BOLD_RED,
            default => self::RESET,
        };

        $this->lines[] = '';
        $this->lines[] = $color . $title . self::RESET;

        return $this;
    }

    public function groupHeader(string $name, int $count = 0): self
    {
        $countStr = $count > 0 ? self::DIM . " ({$count})" . self::RESET : '';
        $this->lines[] = ' ' . self::BOLD_CYAN . $name . self::RESET . $countStr;
        return $this;
    }

    public function groupSeparator(): self
    {
        $this->lines[] = '';
        return $this;
    }

    public function listItem(string $item, string $type = 'default'): self
    {
        $prefix = match ($type) {
            'success' => self::GREEN . '  ✓' . self::RESET,
            'error' => self::RED . '  ✗' . self::RESET,
            default => '  •',
        };

        $this->lines[] = "{$prefix} {$item}";
        return $this;
    }

    public function errorDetail(string $detail): self
    {
        $this->lines[] = '    ' . self::YELLOW . $detail . self::RESET;
        return $this;
    }

    public function render(): void
    {
        echo implode(PHP_EOL, $this->lines) . PHP_EOL;
        $this->lines = [];
    }

    public function renderAndExit(int $code = 0): never
    {
        $this->render();
        exit($code);
    }
}
