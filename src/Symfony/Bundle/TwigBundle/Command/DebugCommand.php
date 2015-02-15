<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Command;

use Symfony\Bridge\Twig\Command\DebugCommand as BaseDebugCommand;
<<<<<<< HEAD
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
=======
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;

/**
 * Lists twig functions, filters, globals and tests present in the current project
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class DebugCommand extends BaseDebugCommand implements ContainerAwareInterface
{
    /**
     * @var ContainerInterface|null
     */
    private $container;

    /**
     * {@inheritdoc}
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    protected function getTwigEnvironment()
    {
        return $this->container->get('twig');
    }
<<<<<<< HEAD

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->setAliases(array('twig:debug'));
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (false !== strpos($input->getFirstArgument(), ':d')) {
            $output->writeln('<comment>The use of "twig:debug" command is deprecated since version 2.7 and will be removed in 3.0. Use the "debug:twig" instead.</comment>');
        }

        parent::execute($input, $output);
    }
=======
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
}
