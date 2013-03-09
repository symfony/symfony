<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

class CountValidatorCountableTest_Countable implements \Countable
{
    private $content;

    public function __construct(array $content)
    {
        $this->content = $content;
    }

    public function count()
    {
        return count($this->content);
    }
}

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CountValidatorCountableTest extends CountValidatorTest
{
    protected function createCollection(array $content)
    {
        return new CountValidatorCountableTest_Countable($content);
    }
}
