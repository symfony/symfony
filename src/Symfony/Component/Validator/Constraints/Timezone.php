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

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Javier Spagnoletti <phansys@gmail.com>
 */
class Timezone extends Constraint
{
    const NO_SUCH_TIMEZONE_ERROR = '45de6628-3479-46d6-a210-00ad584f530a';

    public $value = \DateTimeZone::ALL;

    public $message = 'This value is not a valid timezone at {{ timezone_group }}.';

    protected static $errorNames = array(
        self::NO_SUCH_TIMEZONE_ERROR => 'NO_SUCH_TIMEZONE_ERROR',
    );

    /**
     * {@inheritdoc}
     */
    public function __construct($options = null)
    {
        if (isset($options['value'])) {
            $this->value = $options['value'];
        }

        parent::__construct($options);
    }
}
