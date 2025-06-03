<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Command;

use Symfony\Component\AssetMapper\Compressor\CompressorInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Pre-compresses files to serve through a web server.
 *
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
#[AsCommand(name: 'assets:compress', description: 'Pre-compresses files to serve through a web server')]
final class CompressAssetsCommand extends Command
{
    public function __construct(
        private readonly CompressorInterface $compressor,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('paths', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'The files to compress')
            ->setHelp(<<<'EOT'
The <info>%command.name%</info> command compresses the given file in Brotli, Zstandard and gzip formats.
This is especially useful to serve pre-compressed files through a web server.

The existing file will be kept. The compressed files will be created in the same directory.
The extension of the compression format will be appended to the original file name.
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $paths = $input->getArgument('paths');
        foreach ($paths as $path) {
            $this->compressor->compress($path);
        }

        $io->success(\sprintf('File%s compressed successfully.', \count($paths) > 1 ? 's' : ''));

        return Command::SUCCESS;
    }
}
