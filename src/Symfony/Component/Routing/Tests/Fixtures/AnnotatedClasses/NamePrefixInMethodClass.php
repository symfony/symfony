<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Fixtures\AnnotatedClasses;

use Symfony\Component\Routing\Annotation\Route;

class NamePrefixInMethodClass
{
    /**
     * @Route("/", namePrefix="sf2_")
     */
    public function testAction()
    {
    }
}
