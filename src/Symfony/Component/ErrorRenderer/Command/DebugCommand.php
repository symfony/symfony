<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorRenderer\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\ErrorRenderer\ErrorRenderer\ErrorRendererInterface;
use Symfony\Component\ErrorRenderer\Exception\FlattenException;
use Symfony\Component\HttpKernel\Debug\FileLinkFormatter;

/**
 * A console command for retrieving information about error renderers.
 *
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 *
 * @internal
 */
class DebugCommand extends Command
{
    protected static $defaultName = 'debug:error-renderer';

    private $renderers;
    private $fileLinkFormatter;

    /**
     * @param ErrorRendererInterface[] $renderers
     */
    public function __construct(array $renderers, FileLinkFormatter $fileLinkFormatter = null)
    {
        $this->renderers = $renderers;
        $this->fileLinkFormatter = $fileLinkFormatter;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->addArgument('format', InputArgument::OPTIONAL, sprintf('Outputs a sample in a specific format (one of %s)', implode(', ', array_keys($this->renderers))))
            ->setDescription('Displays all available error renderers and their formats.')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command displays all available error renderers and
their formats:

  <info>php %command.full_name%</info>

Or output a sample in a specific format:

  <info>php %command.full_name% json</info>

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
        $renderers = $this->renderers;

        if ($format = $input->getArgument('format')) {
            if (!isset($renderers[$format])) {
                throw new InvalidArgumentException(sprintf('No error renderer found for format "%s". Known format are %s.', $format, implode(', ', array_keys($this->renderers))));
            }

            $exception = FlattenException::createFromThrowable(new \Exception('This is a sample exception.'), 500, ['X-Debug' => false]);
            $io->writeln($renderers[$format]->render($exception));
        } else {
            $tableRows = [];
            foreach ($renderers as $format => $renderer) {
                $tableRows[] = [sprintf('<fg=cyan>%s</fg=cyan>', $format), $this->formatClassLink(\get_class($renderer))];
            }

            $io->title('Error Renderers');
            $io->text('The following error renderers are available:');
            $io->newLine();
            $io->table(['Format', 'Class'], $tableRows);
        }

        return 0;
    }

    private function formatClassLink(string $class): string
    {
        if ('' === $fileLink = $this->getFileLink($class)) {
            return $class;
        }

        return sprintf('<href=%s>%s</>', $fileLink, $class);
    }

    private function getFileLink(string $class): string
    {
        if (null === $this->fileLinkFormatter) {
            return '';
        }

        try {
            $r = new \ReflectionClass($class);
        } catch (\ReflectionException $e) {
            return '';
        }

        return $this->fileLinkFormatter->format($r->getFileName(), $r->getStartLine());
    }
}
