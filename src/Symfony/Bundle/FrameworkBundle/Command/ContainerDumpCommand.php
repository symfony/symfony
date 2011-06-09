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

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Dumper\Dumper;
use Symfony\Component\DependencyInjection\Dumper\GraphvizDumper;
use Symfony\Component\DependencyInjection\Dumper\XmlDumper;
use Symfony\Component\DependencyInjection\Dumper\YamlDumper;

/**
 * A console command for dumping information about services
 *
 * @author Richard Miller <richard.miller@limethinking.co.uk>
 */
class ContainerDumpCommand extends Command
{

    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputOption('file', null, InputOption::VALUE_REQUIRED, 'Use to specify output file.'),
                new InputOption('format', null, InputOption::VALUE_REQUIRED, 'Use to specify dump format'),
            ))
            ->setName('container:dump')
            ->setDescription('Dumps current services for an application')
            ->setHelp(<<<EOF
The <info>container:dump</info> command dumps the configured services:

  <info>container:dump</info>

By default, the dump is sent to standard output. You can specify a file to
save to using the --file argument:

  <info>container:debug --file=/path/to/file</info>

The default dump format is Graphviz. The services can also be dumped as
XML and YAML. You can specify the format the --format argument:

  <info>container:debug --format=FORMAT</info>

The Graphviz dumper generates a dot representation of the container. This
representation can be converted to an image by using the dot program.

  <info>dot -Tpng /path/to/container.dot > /path/to/container.png</info>

EOF
            )
        ;
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $filePath = $input->getOption('file');
        $format = $input->getOption('format');
        $dumper = $this->getDumper($format);
        $dump = $dumper->dump();

        if(isset($filePath) === false){
            $output->writeln($dump);
            return;
        }

        if (false === @file_put_contents($filePath, $asset->dump())) {
            throw new \RuntimeException('Unable to write file '.$filePath);
        }
        $output->writeln(sprintf('Dumping all services to <comment>%s</comment>.', $filePath));
    }


    /**
     * Returns the relevant Dumper for the format.
     *
     * @return Dumper
     */
    protected function getDumper($format = 'graphviz')
    {
        $containerBuilder = $this->getContainerBuilder();

        switch(strtolower($format)){
            case 'yaml':
                return new YamlDumper($containerBuilder);
            break;
            case 'xml':
                return new XmlDumper($containerBuilder);
            break;
            case 'graphviz':
            default:
                return new GraphvizDumper($containerBuilder);
            break;
        }
    }


    /**
     * Loads the ContainerBuilder from the cache.
     *
     * @return ContainerBuilder
     */
    private function getContainerBuilder()
    {
        if (!$this->getApplication()->getKernel()->isDebug()) {
            throw new \LogicException(sprintf('Dumping services is only available in debug mode.'));
        }

        if (!file_exists($cachedFile = $this->container->getParameter('debug.container.dump'))) {
            throw new \LogicException(sprintf('Debug information about the container could not be found. Please clear the cache and try again.'));
        }

        $container = new ContainerBuilder();

        $loader = new XmlFileLoader($container, new FileLocator());
        $loader->load($cachedFile);

        return $container;
    }


}
