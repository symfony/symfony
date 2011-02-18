<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SwiftmailerBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Warmup the cache.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author Clément JOBEILI <clement.jobeili@gmail.com>
 */
class SendEmailCommand extends Command
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setName('swiftmailer:spool:send')
            ->setDescription('Send all emails from the spool')
            ->addOption('message-limit', 0, InputOption::VALUE_OPTIONAL, 'The maximum number of messages to send.')
            ->addOption('time-limit', 0, InputOption::VALUE_OPTIONAL, 'The time limit for sending messages (in seconds).')
            ->setHelp(<<<EOF
The <info>swiftmailer:spool:send</info> command send all emails from the spool.

EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $mailer     = $this->container->get('mailer');
        $transport  = $mailer->getTransport();

        if ($transport instanceof \Swift_Transport_SpoolTransport) {
            $spool = $transport->getSpool();
            $spool->setMessageLimit($input->getOption('message-limit'));
            $spool->setTimeLimit($input->getOption('time-limit'));
            $sent = $spool->flushQueue($this->container->get('swiftmailer.transport.real'));

            $output->writeln(sprintf('sent %s emails', $sent));
        }
    }
}
