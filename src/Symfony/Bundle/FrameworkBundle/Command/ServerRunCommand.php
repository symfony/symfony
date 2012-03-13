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

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Runs Symfony2 application using PHP built-in web server
 *
 * @author Michał Pipa <michal.pipa.xsolve@gmail.com>
 */
class ServerRunCommand extends ContainerAwareCommand
{
    /**
     * {@inheritDoc}
     */
    public function isEnabled()
    {
        if (version_compare(phpversion(), '5.4.0', '<')) {
            return false;
        }

        return parent::isEnabled();
    }

    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputArgument('address', InputArgument::OPTIONAL, 'Address:port', 'localhost:8000'),
                new InputOption('docroot', 'd', InputOption::VALUE_REQUIRED, 'Document root', 'web/'),
                new InputOption('router', 'r', InputOption::VALUE_REQUIRED, 'Path to custom router script'),
            ))
            ->setName('server:run')
            ->setDescription('Runs Symfony2 application using PHP built-in web server')
            ->setHelp(<<<EOF
The <info>%command.name%</info> runs Symfony2 application using PHP built-in web server:

  <info>%command.full_name%</info>

To change default bind address and port use the <info>address</info> argument:

  <info>%command.full_name% 127.0.0.1:8080</info>

To change default docroot directory use the <info>--docroot</info> option:

  <info>%command.full_name% --docroot=htdocs/</info>

If you have custom docroot directory layout, you can specify your own
router script using <info>--router</info> option:

  <info>%command.full_name% --router=app/config/router.php</info>

See also: http://www.php.net/manual/en/features.commandline.webserver.php
EOF
            )
        ;
    }

    /**
     * @see Command
     *
     * @throws \InvalidArgumentException When the docroot directory does not exist
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $docroot = $input->getOption('docroot');

        if (@!chdir($docroot)) {
            throw new \InvalidArgumentException(sprintf(
                'Unable to change directory to %s',
                $docroot
            ));
        }

        $router = $input->getOption('router') ?: $this
            ->getContainer()
            ->get('kernel')
            ->locateResource('@FrameworkBundle/Resources/config/router.php')
        ;

        $command = escapeshellcmd(
            sprintf(
                '%s -S %s %s',
                PHP_BINARY,
                $input->getArgument('address'),
                $router
            )
        );

        proc_open(
            $command,
            array(
                0 => STDIN,
                1 => STDOUT,
                2 => STDERR
            ),
            $pipes
        );
    }
}
