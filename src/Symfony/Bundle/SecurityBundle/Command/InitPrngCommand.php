<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Initializes a custom PRNG seed provider.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class InitPrngCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('init:prng');
            ->addArgument('phrase', InputArgument::REQUIRED, 'A random string');
            ->setDescription('Initialize a custom PRNG seed provider')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command initializes a custom PRNG seed provider:

<info>php %command.full_name% ABCDE...</info>

The argument should be a random string, whatever comes to your mind right now.
You do not need to remember it, it does not need to be cryptic, or long, and it
will not be stored in a decipherable way. One restriction however, you should
not let this be generated in an automated fashion.
EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->getContainer()->has('security.prng_seed_provider')) {
            throw new \RuntimeException('No seed provider has been configured under path "secure.prng".');
        }

        $this->getContainer()->get('security.prng_seed_provider')->updateSeed(base64_encode(hash('sha512', $input->getArgument('phrase'), true)));

        $output->writeln('The CSPRNG has been initialized successfully.');
    }
}
