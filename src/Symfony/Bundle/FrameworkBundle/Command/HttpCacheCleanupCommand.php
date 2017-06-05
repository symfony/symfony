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

/**
 * TODO: this command is a bit all over the place
 * imho there should be a command for releasing locks and purging stale data
 * and it should be able to do this for all cache entries or just a specific uri
 * note sure if this should be two separate or just one command
 * also this will require further changes to Store/StoreInterface
 */
class HttpCacheCleanupCommand extends AbstractHttpCacheCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setName('http:cache:cleanup')
            ->setDefinition($this->getDefinitionArray())
            ->setDescription('Cleans up locked files in the http cache')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command cleans up all locked entries in the http cache
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
        if (empty($uri)) {
            $output->writeln(sprintf('Cleaning up the http cache for the <info>%s</info> environment with debug <info>%s</info>', $kernel->getEnvironment(), var_export($kernel->isDebug(), true)));
            $cacheKernel->getStore()->cleanup();
            return 0;
        }

        $output->writeln(sprintf('Cleaning up the http cache for the uri <info>%s</info> and the <info>%s</info> environment with debug <info>%s</info>', $uri, $kernel->getEnvironment(), var_export($kernel->isDebug(), true)));

        $store = $cacheKernel->getStore();
        $request = Request::create($uri);
        if ($store->isLocked($request)) {
            $output->writeln(sprintf('Removed lock for the uri <info>%s</info>', $uri));
            $store->unlock($request);
        }
    }
}
