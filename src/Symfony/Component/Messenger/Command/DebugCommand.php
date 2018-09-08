<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * A console command to debug Messenger information.
 *
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
class DebugCommand extends Command
{
    protected static $defaultName = 'debug:messenger';

    private $mapping;

    public function __construct(array $mapping)
    {
        $this->mapping = $mapping;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->addArgument('bus', InputArgument::OPTIONAL, sprintf('The bus id (one of %s)', implode(', ', array_keys($this->mapping))), null)
            ->setDescription('Lists messages you can dispatch using the message buses')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command displays all messages that can be
dispatched using the message buses:

  <info>php %command.full_name%</info>

Or for a specific bus only:

  <info>php %command.full_name% command_bus</info>

EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Messenger');

        $mapping = $this->mapping;
        if ($bus = $input->getArgument('bus')) {
            if (!isset($mapping[$bus])) {
                throw new RuntimeException(sprintf('Bus "%s" does not exist. Known buses are %s.', $bus, implode(', ', array_keys($this->mapping))));
            }
            $mapping = array($bus => $mapping[$bus]);
        }

        foreach ($mapping as $bus => $handlersByMessage) {
            $io->section($bus);

            $tableRows = array();
            foreach ($handlersByMessage as $message => $handlers) {
                $tableRows[] = array(sprintf('<fg=cyan>%s</fg=cyan>', $message));
                foreach ($handlers as $handler) {
                    $tableRows[] = array(sprintf('    handled by <info>%s</>', $handler));
                }
            }

            if ($tableRows) {
                $io->text('The following messages can be dispatched:');
                $io->newLine();
                $io->table(array(), $tableRows);
            } else {
                $io->warning(sprintf('No handled message found in bus "%s".', $bus));
            }
        }
    }
}
