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

class HttpCacheClearCommand extends AbstractHttpCacheCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setName('http:cache:clear')
            ->setDefinition($this->getDefinitionArray())
            ->setDescription('Clears the http cache')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command clears the application http cache for a given environment
and debug mode:

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
            $output->writeln(sprintf('Clearing the http cache for the <info>%s</info> environment with debug <info>%s</info>', $kernel->getEnvironment(), var_export($kernel->isDebug(), true)));
            $filesystem = $this->getContainer()->get('filesystem');
            $cacheDir = $this->getCacheDir();
            $filesystem->remove($cacheDir);
            return 0;
        }

        $output->writeln(sprintf('Clearing the http cache for the uri <info>%s</info> and the <info>%s</info> environment with debug <info>%s</info>', $uri, $kernel->getEnvironment(), var_export($kernel->isDebug(), true)));

        $store = $cacheKernel->getStore();
        $store->purge($uri);
    }
}
