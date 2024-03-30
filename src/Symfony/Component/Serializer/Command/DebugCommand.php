<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Dumper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Serializer\Mapping\ClassMetadataInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;

/**
 * A console command to debug Serializer information.
 *
 * @author Loïc Frémont <lc.fremont@gmail.com>
 */
#[AsCommand(name: 'debug:serializer', description: 'Display serialization information for classes')]
class DebugCommand extends Command
{
    public function __construct(private readonly ClassMetadataFactoryInterface $serializer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('class', InputArgument::REQUIRED, 'A fully qualified class name')
            ->setHelp("The <info>%command.name% 'App\Entity\Dummy'</info> command dumps the serializer groups for the dummy class.")
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $class = $input->getArgument('class');

        if (!class_exists($class)) {
            $io = new SymfonyStyle($input, $output);
            $io->error(sprintf('Class "%s" was not found.', $class));

            return Command::FAILURE;
        }

        $this->dumpSerializerDataForClass($input, $output, $class);

        return Command::SUCCESS;
    }

    private function dumpSerializerDataForClass(InputInterface $input, OutputInterface $output, string $class): void
    {
        $io = new SymfonyStyle($input, $output);
        $title = sprintf('<info>%s</info>', $class);
        $rows = [];
        $dump = new Dumper($output);

        $classMetadata = $this->serializer->getMetadataFor($class);

        foreach ($this->getAttributesData($classMetadata) as $propertyName => $data) {
            $rows[] = [
                $propertyName,
                $dump($data),
            ];
        }

        if (!$rows) {
            $io->section($title);
            $io->text('No Serializer data were found for this class.');

            return;
        }

        $io->section($title);

        $table = new Table($output);
        $table->setHeaders(['Property', 'Options']);
        $table->setRows($rows);
        $table->render();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getAttributesData(ClassMetadataInterface $classMetadata): array
    {
        $data = [];

        foreach ($classMetadata->getAttributesMetadata() as $attributeMetadata) {
            $data[$attributeMetadata->getName()] = [
                'groups' => $attributeMetadata->getGroups(),
                'maxDepth' => $attributeMetadata->getMaxDepth(),
                'serializedName' => $attributeMetadata->getSerializedName(),
                'serializedPath' => $attributeMetadata->getSerializedPath() ? (string) $attributeMetadata->getSerializedPath() : null,
                'ignore' => $attributeMetadata->isIgnored(),
                'normalizationContexts' => $attributeMetadata->getNormalizationContexts(),
                'denormalizationContexts' => $attributeMetadata->getDenormalizationContexts(),
            ];
        }

        return $data;
    }
}
