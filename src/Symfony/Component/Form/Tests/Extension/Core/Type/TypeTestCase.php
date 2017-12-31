<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\Type;

use Symfony\Component\Form\Test\TypeTestCase as BaseTypeTestCase;

/**
 * @deprecated since version 2.3, to be removed in 3.0.
 *             Use {@link \Symfony\Component\Form\Test\TypeTestCase} instead.
 */
abstract class TypeTestCase extends BaseTypeTestCase
{
    protected function setUp()
    {
        @trigger_error('Abstract class '.__CLASS__.' is deprecated since Symfony 2.3 and will be removed in 3.0. Use the Symfony\Component\Form\Test\TypeTestCase class instead.', E_USER_DEPRECATED);
        parent::setUp();
    }
}
