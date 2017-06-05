<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;

class HttpCacheInfoCommand extends AbstractHttpCacheCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setName('http:cache:info')
            ->setDefinition($this->getDefinitionArray())
            ->setDescription('Provides information about entries in the http cache')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command provides information about entries in the http cache
for a given environment and debug mode:

<info>php %command.full_name% --env=dev</info>
<info>php %command.full_name% --env=prod --no-debug</info>
EOF
        )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $kernel = $this->getContainer()->get('kernel');
        $cacheKernel = $this->getCacheKernel($input, $kernel);

        $uri = $input->getArgument('uri');

        $output->writeln(sprintf('Reading information from the http cache for the uri <info>%s</info> and the <info>%s</info> environment with debug <info>%s</info>', $uri, $kernel->getEnvironment(), var_export($kernel->isDebug(), true)));

        $store = $cacheKernel->getStore();
        $request = Request::create($uri);
        $txt = $store->isCached($request) ? ('cached'.($store->isLocked($request) ? ' and locked' : ' and unlocked')) : 'not cached';
        $output->writeln(sprintf('Location (is %s): <info>%s</info>', $txt, $store->getLocation($request)));
    }
}
