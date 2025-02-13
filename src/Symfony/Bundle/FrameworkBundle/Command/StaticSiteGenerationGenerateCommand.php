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

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\Exception\RuntimeException;
use Symfony\Component\HttpKernel\StaticSiteGeneration\StaticPageDumperInterface;
use Symfony\Component\HttpKernel\StaticSiteGeneration\StaticPagesGenerator;
use Symfony\Component\Routing\StaticSiteGeneration\StaticPageUrisProviderInterface;

/**
 * @author Thomas Bibaut <bibaut.t@gmail.com>
 */
#[AsCommand(name: 'static-site-generation:generate', description: 'Generates static pages')]
final class StaticSiteGenerationGenerateCommand extends Command
{
    public function __construct(
        private readonly StaticPagesGenerator $staticPagesGenerator,
        private readonly StaticPageDumperInterface $staticPageDumper,
        private readonly StaticPageUrisProviderInterface $staticPageUrisProvider,
    ) {
        parent::__construct();
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'A pattern to filter the pages to generate')] ?string $filterPattern = null,
        #[Option(description: 'Do not dump pages')] bool $dryRun = false,
    ): int {
        $successful = true;

        foreach ($this->staticPageUrisProvider->provide() as $uri) {
            if (null !== $filterPattern && !preg_match($filterPattern, $uri)) {
                continue;
            }

            try {
                ['content' => $content, 'format' => $format] = $this->staticPagesGenerator->generate($uri);

                if (false === $dryRun) {
                    $this->staticPageDumper->dump($uri, $content, $format);
                }

                $io->info(\sprintf('Generated static page for URI "%s"', $uri));
            } catch (RuntimeException $exception) {
                $io->error(\sprintf('Generating page for URI "%s" failed : %s', $uri, $exception->getMessage()));
                $successful = false;
            }
        }

        return $successful ? Command::SUCCESS : Command::FAILURE;
    }
}
