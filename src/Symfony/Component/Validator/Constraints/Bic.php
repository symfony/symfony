<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Markus Malkusch <markus@malkusch.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * Value must be a BIC (Bank Identifier Code).
 * 
 * Set the option $country to limit valid BICs to one country only.
 * 
 * Note: BIC validation is currently implemented only with the country filter
 * Bic::DE.
 * 
 * Validation of a German BIC uses the library BAV. BAV's default configuration
 * is not recommended for BIC validation. Use a configuration with one of the
 * following DataBackendContainer implementations: PDODataBackendContainer or 
 * DoctrineBackendContainer.
 * 
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Markus Malkusch <markus@malkusch.de>
 *
 * @see \malkusch\bav\BAV::isValidBIC()
 * @see \malkusch\bav\ConfigurationRegistry::setConfiguration()
 * @api
 */
class Bic extends Constraint
{
    /**
     * @var string validate only German BIC
     */
    const DE = 'de';
    
    /**
     * @var string Limits the validation only for one country.
     */
    public $country;
    
    public $message = 'This value is not a valid BIC.';
    
    /**
     * {@inheritdoc}
     */
    public function getDefaultOption()
    {
        return 'country';
    }
}
