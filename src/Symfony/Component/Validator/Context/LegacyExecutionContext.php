<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Context;

use Symfony\Component\Validator\ExecutionContextInterface as LegacyExecutionContextInterface;

/**
 * @since  %%NextVersion%%
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class LegacyExecutionContext extends ExecutionContext implements LegacyExecutionContextInterface
{
    public function addViolationAt($subPath, $message, array $params = array(), $invalidValue = null, $pluralization = null, $code = null)
    {

    }

    public function validate($value, $subPath = '', $groups = null, $traverse = false, $deep = false)
    {

    }

    public function validateValue($value, $constraints, $subPath = '', $groups = null)
    {

    }

    public function getMetadataFactory()
    {

    }
}
