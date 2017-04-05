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
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Javier Spagnoletti <phansys@gmail.com>
 */
class Timezone extends Constraint
{
    const NO_SUCH_TIMEZONE_ERROR = '45de6628-3479-46d6-a210-00ad584f530a';
    const NO_SUCH_TIMEZONE_IN_ZONE_ERROR = 'b57767b1-36c0-40ac-a3d7-629420c775b8';
    const NO_SUCH_TIMEZONE_IN_COUNTRY_ERROR = 'c4a22222-dc92-4fc0-abb0-d95b268c7d0b';

    public $zone;

    public $countryCode;

    public $message = 'This value is not a valid timezone{{ extra_info }}.';

    protected static $errorNames = array(
        self::NO_SUCH_TIMEZONE_ERROR => 'NO_SUCH_TIMEZONE_ERROR',
    );

    /**
     * {@inheritdoc}
     */
    public function __construct($options = null)
    {
        if (isset($options['zone'])) {
            $this->zone = $options['zone'];
        }

        if (isset($options['countryCode'])) {
            if (\DateTimeZone::PER_COUNTRY !== $this->zone) {
                throw new ConstraintDefinitionException('The option "countryCode" can only be used when "zone" option has `\DateTimeZone::PER_COUNTRY` as value');
            }

            $this->countryCode = $options['countryCode'];
        }

        parent::__construct($options);
    }
}
