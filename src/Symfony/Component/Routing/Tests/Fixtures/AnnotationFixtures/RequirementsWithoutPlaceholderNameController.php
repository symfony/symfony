<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures;

use Symfony\Component\Routing\Attribute\Route;

/**
 * @Route("/", requirements={"foo", "\d+"})
 */
class RequirementsWithoutPlaceholderNameController
{
    /**
     * @Route("/{foo}", name="foo", requirements={"foo", "\d+"})
     */
    public function foo()
    {
    }
}
