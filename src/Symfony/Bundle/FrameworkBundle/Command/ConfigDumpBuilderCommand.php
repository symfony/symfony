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

use Symfony\Component\Config\Builder\ConfigBuilderGeneratorInterface;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

/**
 * A command for dumping all config builder for your bundles.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * @final
 */
class ConfigDumpBuilderCommand extends AbstractConfigCommand
{
    protected static $defaultName = 'config:dump-builders';
    protected static $defaultDescription = 'Dump the config builder for an extension';

    private $generator;

    public function __construct(ConfigBuilderGeneratorInterface $generator)
    {
        $this->generator = $generator;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDefinition([
                new InputArgument('name', InputArgument::OPTIONAL, 'The Bundle name or the extension alias'),
            ])
            ->setDescription(self::$defaultDescription)
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command dumps "configuration bundles" that help
writing configuration for an extension/bundle.

Either the extension alias or bundle name can be used:

  <info>php %command.full_name% framework</info>
  <info>php %command.full_name% FrameworkBundle</info>

EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \LogicException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $errorIo = $io->getErrorStyle();

        if (null !== $name = $input->getArgument('name')) {
            $extensions = [$this->findExtension($name)];
        } else {
            $extensions = array_map(function (BundleInterface $a) {
                return $a->getContainerExtension();
            }, $this->getApplication()->getKernel()->getBundles());
        }

        foreach ($extensions as $extension) {
            if (null === $extension) {
                continue;
            }

            try {
                $this->dumpExtension($extension);
            } catch (\Throwable $e) {
                $errorIo->error(sprintf('Could not dump configuration for "%s". Exception: %s', \get_class($extension), $e->getMessage()));
            }
        }

        return 0;
    }

    private function dumpExtension(ExtensionInterface $extension): void
    {
        if ($extension instanceof ConfigurationInterface) {
            $configuration = $extension;
        } else {
            $configuration = $extension->getConfiguration([], $this->getContainerBuilder());
        }

        $this->generator->build($configuration);
    }
}
