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
use Symfony\Component\Messenger\Middleware\DescriptionAwareMiddleware;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;

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
            ->addArgument('bus', InputArgument::OPTIONAL, sprintf('The bus id (one of %s)', implode(', ', array_keys($this->mapping))))
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
            $mapping = [$bus => $mapping[$bus]];
        }

        foreach ($mapping as $bus => $handlersByMessage) {
            $io->section($bus);

            $this->describeMessages($handlersByMessage, $bus, $io);
            $this->describeMiddlewares([], $bus, $io);
        }
    }

    private function describeMessages($handlersByMessage, string $bus, SymfonyStyle $io): void
    {
        $tableRows = [];
        foreach ($handlersByMessage as $message => $handlers) {
            $tableRows[] = [sprintf('<fg=cyan>%s</fg=cyan>', $message)];
            foreach ($handlers as $handler) {
                $tableRows[] = [
                    sprintf('    handled by <info>%s</>', $handler[0]).$this->formatConditions($handler[1]),
                ];
            }
        }

        if ($tableRows) {
            $io->text('The following messages can be dispatched:');
            $io->newLine();
            $io->table([], $tableRows);
        } else {
            $io->warning(sprintf('No handled message found in bus "%s".', $bus));
        }
    }

    /**
     * @param MiddlewareInterface[] $middlewares
     */
    private function describeMiddlewares(array $middlewares, string $bus, SymfonyStyle $io): void
    {
        $before = $after = [];
        foreach ($middlewares as $middleware) {
            if ($middleware instanceof DescriptionAwareMiddleware) {
                $before[] = $middleware->getDescription()->getBefore();
                $after[] = $middleware->getDescription()->getAfter();
            } else {
                $before[] = 'aa';
                $after[] = 'aaa';
            }
        }

        $lines = array_merge($before, array_reverse($after));
        $io->text('The following middlewares are registered:');
        $io->newLine();
        $io->listing($lines);
    }

    private function formatConditions(array $options): string
    {
        if (!$options) {
            return '';
        }

        $optionsMapping = [];
        foreach ($options as $key => $value) {
            $optionsMapping[] = ' '.$key.'='.$value;
        }

        return ' (when'.implode(', ', $optionsMapping).')';
    }
}
