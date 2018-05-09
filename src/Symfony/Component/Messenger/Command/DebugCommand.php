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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * A console command to debug Messenger information.
 *
 * @author Roland Franssen <franssen.roland@gmail.com>
 *
 * @experimental in 4.1
 */
class DebugCommand extends Command
{
    protected static $defaultName = 'debug:messenger';

    private $mapping;

    public function __construct(array $mapping)
    {
        parent::__construct();

        $this->mapping = $mapping;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDescription('Lists messages you can dispatch using the message bus')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command displays all messages that can be
dispatched using the message bus:

  <info>php %command.full_name%</info>

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
        $io->text('The following messages can be dispatched:');
        $io->newLine();

        $tableRows = array();
        foreach ($this->mapping as $message => $handlers) {
            $tableRows[] = array(sprintf('<fg=cyan>%s</fg=cyan>', $message));
            foreach ($handlers as $handler) {
                $tableRows[] = array(sprintf('    handled by %s', $handler));
            }
        }

        if ($tableRows) {
            $io->table(array(), $tableRows);
        } else {
            $io->text('No messages were found that have valid handlers.');
        }
    }
}
