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
 * @author Przemysław Bogusz <przemyslaw.bogusz@tubotax.pl>
 */
class AtLeastOneOf extends Composite
{
    public const AT_LEAST_ONE_OF_ERROR = 'f27e6d6c-261a-4056-b391-6673a623531c';

    protected static $errorNames = [
        self::AT_LEAST_ONE_OF_ERROR => 'AT_LEAST_ONE_OF_ERROR',
    ];

    public $constraints = [];
    public $message = 'This value should satisfy at least one of the following constraints:';
    public $messageCollection = 'Each element of this collection should satisfy its own set of constraints.';
    public $includeInternalMessages = true;

    public function getDefaultOption()
    {
        return 'constraints';
    }

    public function getRequiredOptions()
    {
        return ['constraints'];
    }

    protected function getCompositeOption()
    {
        return 'constraints';
    }
}
