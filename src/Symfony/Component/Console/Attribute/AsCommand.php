<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Attribute;

/**
 * Service tag to autoconfigure commands.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class AsCommand
{
    /**
     * @param string      $name        The name of the command, used when calling it (i.e. "cache:clear")
     * @param string|null $description The description of the command, displayed with the help page
     * @param string[]    $aliases     The list of aliases of the command. The command will be executed when using one of them (i.e. "cache:clean")
     * @param bool        $hidden      If true, the command won't be shown when listing all the available commands, but it can still be run as any other command
     */
    public function __construct(
        public string $name,
        public ?string $description = null,
        array $aliases = [],
        bool $hidden = false,
    ) {
        if (!$hidden && !$aliases) {
            return;
        }

        $name = explode('|', $name);
        $name = array_merge($name, $aliases);

        if ($hidden && '' !== $name[0]) {
            array_unshift($name, '');
        }

        $this->name = implode('|', $name);
    }
}
