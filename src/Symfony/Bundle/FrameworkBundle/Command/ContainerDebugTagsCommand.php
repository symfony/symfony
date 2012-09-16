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

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\DependencyInjection\Definition;

/**
 * A console command for retrieving information about tagged services
 *
 * @author Richard Miller <rmiller@sensiolabs.co.uk>
 */
class ContainerDebugTagsCommand extends ContainerDebugCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setName('container:debug:tags')
            ->setDefinition(
                array(
                    new InputArgument('name', InputArgument::OPTIONAL, 'A tag name (form.type)'),
                    new InputOption(
                        'show-private',
                        null,
                        InputOption::VALUE_NONE,
                        'Use to show public *and* private services'
                    ),
                )
            )
            ->setDescription('Displays tagged services for an application')
            ->setHelp(
            <<<EOF
            The <info>%command.name%</info> command displays tagged <comment>public</comment> services grouped by tag:

  <info>php %command.full_name%</info>

To get services for a particular tag, specify its name:

  <info>php %command.full_name% kernel.event_subscriber</info>

By default, private services are hidden. You can display all services by
using the --show-private flag:

  <info>php %command.full_name% --show-private</info>
EOF
        );
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->containerBuilder = $this->getContainerBuilder();
        $name = $input->getArgument('name');

        if ($name) {
            $this->outputTag($output, $name);
        } else {
            $this->outputTags($output, $input->getOption('show-private'));
        }
    }

    /**
     * Renders list of tagged services grouped by tag
     *
     * @param OutputInterface $output
     * @param bool            $showPrivate
     */
    protected function outputTags(OutputInterface $output, $showPrivate = false)
    {
        $tags = $this->containerBuilder->findTags();
        asort($tags);

        $label = 'Tagged services';
        $output->writeln($this->getHelper('formatter')->formatSection('container', $label));

        foreach ($tags as $tag) {
            $serviceIds = $this->containerBuilder->findTaggedServiceIds($tag);

            foreach ($serviceIds as $serviceId => $attributes) {
                $definition = $this->resolveServiceDefinition($serviceId);
                if ($definition instanceof Definition) {
                    if (!$showPrivate && !$definition->isPublic()) {
                        unset($serviceIds[$serviceId]);
                        continue;
                    }
                }
            }

            if (count($serviceIds) === 0) {
                continue;
            }

            $output->writeln($this->getHelper('formatter')->formatSection('tag', $tag));

            foreach ($serviceIds as $serviceId => $attributes) {
                $output->writeln($serviceId);
            }

            $output->writeln('');
        }
    }

    /**
     * Renders detailed information on the services with a particular tag
     *
     * @param OutputInterface $output
     * @param string          $name
     */
    protected function outputTag(OutputInterface $output, $name)
    {
        $label = sprintf('Information for tag <info>%s</info>', $name);
        $output->writeln($this->getHelper('formatter')->formatSection('tag', $label));
        $output->writeln('');

        $serviceIds = $this->containerBuilder->findTaggedServiceIds($name);

        if (count($serviceIds) === 0) {
            $output->writeln('There are no services tagged with ' . $name);

            return;
        }

        foreach ($serviceIds as $serviceId => $tagAttributes) {
            $output->writeln($this->getHelper('formatter')->formatSection('service', $serviceId));
            $format = $this->getAttributesFormat($tagAttributes);
            $this->outputAttributes($tagAttributes, $output, $format);
        }
    }

    /**
     * Creates the format string to use to output the tag attributes
     *
     * @param array $tagAttributes
     *
     * @return string
     */
    private function getAttributesFormat($tagAttributes)
    {
        $maxKey = 4;

        foreach ($tagAttributes as $attributes) {
            foreach ($attributes as $key => $value) {
                if (strlen($key) > $maxKey) {
                    $maxKey = strlen($key);
                }
            }
        }
        $format = '    %-' . $maxKey . 's - %s';

        return $format;
    }

    /**
     * Outputs tag attributes
     *
     * @param array           $tagAttributes
     * @param OutputInterface $output
     * @param string          $format
     */
    private function outputAttributes($tagAttributes, OutputInterface $output, $format)
    {
        if (count($tagAttributes[0]) > 0) {
            $output->writeln('Attributes:');
        }

        foreach ($tagAttributes as $attributes) {
            foreach ($attributes as $key => $value) {
                $output->writeln(sprintf($format, $key, $value));
            }
            $output->writeln('');
        }
    }
}

