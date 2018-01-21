<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class All extends Composite
{
    const WRONG_TYPE_ERROR = '824268b5-91c0-4730-983f-896fb0f971f0';

    protected static $errorNames = array(
        self::WRONG_TYPE_ERROR => 'WRONG_TYPE_ERROR',
    );

    public $constraints = array();
    public $wrongTypeMessage = 'This value should be an array.';

    public function getDefaultOption()
    {
        return 'constraints';
    }

    public function getRequiredOptions()
    {
        return array('constraints');
    }

    protected function getCompositeOption()
    {
        return 'constraints';
    }
}
