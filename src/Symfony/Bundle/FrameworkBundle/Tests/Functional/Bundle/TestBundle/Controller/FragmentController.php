<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerAware;

class FragmentController extends ContainerAware
{
    public function indexAction()
    {
        $actions = $this->container->get('templating')->get('actions');

        return new Response($actions->render($actions->controller('TestBundle:Fragment:inlined', array(
            'options' => array(
                'bar' => new Bar(),
                'eleven' => 11,
            ),
        ))));
    }

    public function inlinedAction($options, $_format)
    {
        return new Response($options['bar']->getBar().' '.$_format);
    }
}

class Bar
{
    private $bar = 'bar';

    public function getBar()
    {
        return $this->bar;
    }
}
