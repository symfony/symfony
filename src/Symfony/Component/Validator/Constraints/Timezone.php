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
 * @author Hugo Hamon <hugohamon@neuf.fr>
 */
class Timezone extends Constraint
{
    public const TIMEZONE_IDENTIFIER_ERROR = '5ce113e6-5e64-4ea2-90fe-d2233956db13';
    public const TIMEZONE_IDENTIFIER_IN_ZONE_ERROR = 'b57767b1-36c0-40ac-a3d7-629420c775b8';
    public const TIMEZONE_IDENTIFIER_IN_COUNTRY_ERROR = 'c4a22222-dc92-4fc0-abb0-d95b268c7d0b';
    public const TIMEZONE_IDENTIFIER_INTL_ERROR = '45863c26-88dc-41ba-bf53-c73bd1f7e90d';

    public $zone = \DateTimeZone::ALL;
    public $countryCode;
    public $intlCompatible = false;
    public $message = 'This value is not a valid timezone.';

    protected static $errorNames = [
        self::TIMEZONE_IDENTIFIER_ERROR => 'TIMEZONE_IDENTIFIER_ERROR',
        self::TIMEZONE_IDENTIFIER_IN_ZONE_ERROR => 'TIMEZONE_IDENTIFIER_IN_ZONE_ERROR',
        self::TIMEZONE_IDENTIFIER_IN_COUNTRY_ERROR => 'TIMEZONE_IDENTIFIER_IN_COUNTRY_ERROR',
        self::TIMEZONE_IDENTIFIER_INTL_ERROR => 'TIMEZONE_IDENTIFIER_INTL_ERROR',
    ];

    /**
     * {@inheritdoc}
     */
    public function __construct($options = null)
    {
        parent::__construct($options);

        if (null === $this->countryCode) {
            if (0 >= $this->zone || \DateTimeZone::ALL_WITH_BC < $this->zone) {
                throw new ConstraintDefinitionException('The option "zone" must be a valid range of "\DateTimeZone" constants.');
            }
        } elseif (\DateTimeZone::PER_COUNTRY !== (\DateTimeZone::PER_COUNTRY & $this->zone)) {
            throw new ConstraintDefinitionException('The option "countryCode" can only be used when the "zone" option is configured with "\DateTimeZone::PER_COUNTRY".');
        }
        if ($this->intlCompatible && !class_exists(\IntlTimeZone::class)) {
            throw new ConstraintDefinitionException('The option "intlCompatible" can only be used when the PHP intl extension is available.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultOption()
    {
        return 'zone';
    }
}
