<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Dumper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Mapping\AutoMappingStrategy;
use Symfony\Component\Validator\Mapping\CascadingStrategy;
use Symfony\Component\Validator\Mapping\ClassMetadataInterface;
use Symfony\Component\Validator\Mapping\Factory\MetadataFactoryInterface;
use Symfony\Component\Validator\Mapping\GenericMetadata;
use Symfony\Component\Validator\Mapping\TraversalStrategy;

/**
 * A console command to debug Validators information.
 *
 * @author Loïc Frémont <lc.fremont@gmail.com>
 */
#[AsCommand(name: 'debug:validator', description: 'Display validation constraints for classes')]
class DebugCommand extends Command
{
    private MetadataFactoryInterface $validator;

    public function __construct(MetadataFactoryInterface $validator)
    {
        parent::__construct();

        $this->validator = $validator;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('class', InputArgument::REQUIRED, 'A fully qualified class name or a path')
            ->addOption('show-all', null, InputOption::VALUE_NONE, 'Show all classes even if they have no validation constraints')
            ->setHelp(<<<'EOF'
The <info>%command.name% 'App\Entity\Dummy'</info> command dumps the validators for the dummy class.

The <info>%command.name% src/</info> command dumps the validators for the `src` directory.
EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $class = $input->getArgument('class');

        if (class_exists($class)) {
            $this->dumpValidatorsForClass($input, $output, $class);

            return 0;
        }

        try {
            foreach ($this->getResourcesByPath($class) as $class) {
                $this->dumpValidatorsForClass($input, $output, $class);
            }
        } catch (DirectoryNotFoundException) {
            $io = new SymfonyStyle($input, $output);
            $io->error(sprintf('Neither class nor path were found with "%s" argument.', $input->getArgument('class')));

            return 1;
        }

        return 0;
    }

    private function dumpValidatorsForClass(InputInterface $input, OutputInterface $output, string $class): void
    {
        $io = new SymfonyStyle($input, $output);
        $title = sprintf('<info>%s</info>', $class);
        $rows = [];
        $dump = new Dumper($output);

        /** @var ClassMetadataInterface $classMetadata */
        $classMetadata = $this->validator->getMetadataFor($class);

        foreach ($this->getClassConstraintsData($classMetadata) as $data) {
            $rows[] = [
                '-',
                $data['class'],
                implode(', ', $data['groups']),
                $dump($data['options']),
            ];
        }

        foreach ($this->getConstrainedPropertiesData($classMetadata) as $propertyName => $constraintsData) {
            foreach ($constraintsData as $data) {
                $rows[] = [
                    $propertyName,
                    $data['class'],
                    implode(', ', $data['groups']),
                    $dump($data['options']),
                ];
            }
        }

        if (!$rows) {
            if (false === $input->getOption('show-all')) {
                return;
            }

            $io->section($title);
            $io->text('No validators were found for this class.');

            return;
        }

        $io->section($title);

        $table = new Table($output);
        $table->setHeaders(['Property', 'Name', 'Groups', 'Options']);
        $table->setRows($rows);
        $table->setColumnMaxWidth(3, 80);
        $table->render();
    }

    private function getClassConstraintsData(ClassMetadataInterface $classMetadata): iterable
    {
        foreach ($classMetadata->getConstraints() as $constraint) {
            yield [
                'class' => $constraint::class,
                'groups' => $constraint->groups,
                'options' => $this->getConstraintOptions($constraint),
            ];
        }
    }

    private function getConstrainedPropertiesData(ClassMetadataInterface $classMetadata): array
    {
        $data = [];

        foreach ($classMetadata->getConstrainedProperties() as $constrainedProperty) {
            $data[$constrainedProperty] = $this->getPropertyData($classMetadata, $constrainedProperty);
        }

        return $data;
    }

    private function getPropertyData(ClassMetadataInterface $classMetadata, string $constrainedProperty): array
    {
        $data = [];

        $propertyMetadata = $classMetadata->getPropertyMetadata($constrainedProperty);
        foreach ($propertyMetadata as $metadata) {
            $autoMapingStrategy = 'Not supported';
            if ($metadata instanceof GenericMetadata) {
                $autoMapingStrategy = match ($metadata->getAutoMappingStrategy()) {
                    AutoMappingStrategy::ENABLED => 'Enabled',
                    AutoMappingStrategy::DISABLED => 'Disabled',
                    AutoMappingStrategy::NONE => 'None',
                };
            }
            $traversalStrategy = 'None';
            if (TraversalStrategy::TRAVERSE === $metadata->getTraversalStrategy()) {
                $traversalStrategy = 'Traverse';
            }
            if (TraversalStrategy::IMPLICIT === $metadata->getTraversalStrategy()) {
                $traversalStrategy = 'Implicit';
            }

            $data[] = [
                'class' => 'property options',
                'groups' => [],
                'options' => [
                    'cascadeStrategy' => CascadingStrategy::CASCADE === $metadata->getCascadingStrategy() ? 'Cascade' : 'None',
                    'autoMappingStrategy' => $autoMapingStrategy,
                    'traversalStrategy' => $traversalStrategy,
                ],
            ];
            foreach ($metadata->getConstraints() as $constraint) {
                $data[] = [
                    'class' => $constraint::class,
                    'groups' => $constraint->groups,
                    'options' => $this->getConstraintOptions($constraint),
                ];
            }
        }

        return $data;
    }

    private function getConstraintOptions(Constraint $constraint): array
    {
        $options = [];

        foreach (array_keys(get_object_vars($constraint)) as $propertyName) {
            // Groups are dumped on a specific column.
            if ('groups' === $propertyName) {
                continue;
            }

            $options[$propertyName] = $constraint->$propertyName;
        }

        ksort($options);

        return $options;
    }

    private function getResourcesByPath(string $path): array
    {
        $finder = new Finder();
        $finder->files()->in($path)->name('*.php')->sortByName(true);
        $classes = [];

        foreach ($finder as $file) {
            $fileContent = file_get_contents($file->getRealPath());

            preg_match('/namespace (.+);/', $fileContent, $matches);

            $namespace = $matches[1] ?? null;

            if (!preg_match('/class +([^{ ]+)/', $fileContent, $matches)) {
                // no class found
                continue;
            }

            $className = trim($matches[1]);

            if (null !== $namespace) {
                $classes[] = $namespace.'\\'.$className;
            } else {
                $classes[] = $className;
            }
        }

        return $classes;
    }
}
