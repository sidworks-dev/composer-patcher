<?php

declare(strict_types=1);

namespace Sidworks\ComposerPatcher\Model;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use Sidworks\ComposerPatcher\Command\PatcherCommand;

/**
 * Command provider for Sidworks Composer Patcher
 */
class CommandProvider implements CommandProviderCapability
{
    public function getCommands()
    {
        return [
            new PatcherCommand(),
        ];
    }
}
