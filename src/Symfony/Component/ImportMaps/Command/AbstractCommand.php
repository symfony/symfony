<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ImportMaps\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\ImportMaps\Env;
use Symfony\Component\ImportMaps\ImportMapManager;
use Symfony\Component\ImportMaps\Provider;

/**
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
abstract class AbstractCommand extends Command
{
    public function __construct(
        protected readonly ImportMapManager $importMapManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('js-env', 'je', InputOption::VALUE_OPTIONAL, '"development" or "production"', Env::Production->value)
            ->addOption('provider', 'p', InputOption::VALUE_OPTIONAL, '"jspm", "jspm.system", "skypack", "jsdelivr" or "unpkg"', Provider::Jspm->value)
        ;
    }
}
