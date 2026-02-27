<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Controller;

use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;

class ControllerHelperTest extends AbstractControllerTest
{
    protected function createController()
    {
        return new class extends ControllerHelper {
            public function __construct()
            {
            }

            public function setContainer(ContainerInterface $container)
            {
                parent::__construct($container);
            }
        };
    }

    public function testSync()
    {
        $r = new \ReflectionClass(ControllerHelper::class);
        $m = $r->getMethod('getSubscribedServices');
        $helperSrc = file($r->getFileName());
        $helperSrc = implode('', \array_slice($helperSrc, $m->getStartLine() - 1, $r->getEndLine() - $m->getStartLine()));

        $r = new \ReflectionClass(AbstractController::class);
        $m = $r->getMethod('getSubscribedServices');
        $abstractSrc = file($r->getFileName());
        $code = [
            implode('', \array_slice($abstractSrc, $m->getStartLine() - 1, $m->getEndLine() - $m->getStartLine() + 1)),
        ];

        foreach ($r->getMethods(\ReflectionMethod::IS_PROTECTED) as $m) {
            if ($m->getDocComment()) {
                $code[] = '    '.$m->getDocComment();
            }
            $code[] = substr_replace(implode('', \array_slice($abstractSrc, $m->getStartLine() - 1, $m->getEndLine() - $m->getStartLine() + 1)), 'public', 4, 9);
        }
        foreach ($r->getMethods(\ReflectionMethod::IS_PRIVATE) as $m) {
            if ($m->getDocComment()) {
                $code[] = '    '.$m->getDocComment();
            }
            $code[] = implode('', \array_slice($abstractSrc, $m->getStartLine() - 1, $m->getEndLine() - $m->getStartLine() + 1));
        }
        $code = implode("\n", $code);

        $this->assertSame($code, $helperSrc, 'Methods from AbstractController are not properly synced in ControllerHelper');
    }
}
