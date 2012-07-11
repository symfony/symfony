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

class MinCountValidatorCountableTest_Countable implements \Countable
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
class MinCountValidatorCountableTest extends MinCountValidatorTest
{
    protected function createCollection(array $content)
    {
        return new MinCountValidatorCountableTest_Countable($content);
    }
}
