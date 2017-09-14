<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Form\Console\Helper\DescriptorHelper;
use Symfony\Component\Form\FormRegistryInterface;

/**
 * A console command for retrieving information about form types.
 *
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
class DebugCommand extends Command
{
    protected static $defaultName = 'debug:form';

    private $formRegistry;
    private $namespaces;

    public function __construct(FormRegistryInterface $formRegistry, array $namespaces = array('Symfony\Component\Form\Extension\Core\Type'))
    {
        parent::__construct();

        $this->formRegistry = $formRegistry;
        $this->namespaces = $namespaces;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputArgument('class', InputArgument::REQUIRED, 'The form type class'),
                new InputOption('format', null, InputOption::VALUE_REQUIRED, 'The output format (txt or json)', 'txt'),
            ))
            ->setDescription('Displays form type information')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command displays information about a form type.

Either the fully-qualified class name or the short class name can be used:

  <info>php %command.full_name% Symfony\Component\Form\Extension\Core\Type\ChoiceType</info>
  <info>php %command.full_name% ChoiceType</info>

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

        if (!class_exists($class = $input->getArgument('class'))) {
            $class = $this->getFqcnTypeClass($input, $io, $class);
        }

        $object = $this->formRegistry->getType($class);

        $helper = new DescriptorHelper();
        $options['format'] = $input->getOption('format');
        $helper->describe($io, $object, $options);
    }

    private function getFqcnTypeClass(InputInterface $input, SymfonyStyle $io, $shortClassName)
    {
        $classes = array();
        foreach ($this->namespaces as $namespace) {
            if (class_exists($fqcn = $namespace.'\\'.$shortClassName)) {
                $classes[] = $fqcn;
            }
        }

        if (0 === $count = count($classes)) {
            throw new \InvalidArgumentException(sprintf("Could not find type \"%s\" into the following namespaces:\n    %s", $shortClassName, implode("\n    ", $this->namespaces)));
        }
        if (1 === $count) {
            return $classes[0];
        }
        if (!$input->isInteractive()) {
            throw new \InvalidArgumentException(sprintf("The type \"%s\" is ambiguous.\nDid you mean one of these?\n    %s", $shortClassName, implode("\n    ", $classes)));
        }

        return $io->choice(sprintf("The type \"%s\" is ambiguous.\n\n Select one of the following form types to display its information:", $shortClassName), $classes, $classes[0]);
    }
}
