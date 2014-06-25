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
 * Validates a German bank account (Konto) for a given bank.
 * 
 * @Annotation
 * @Target({"CLASS", "ANNOTATION"})
 *
 * @author Markus Malkusch <markus@malkusch.de>
 *
 * @see \malkusch\bav\BAV::isValidBankAccount()
 * @api
 */
class Konto extends Constraint
{
    /**
     * @var string Name of the BLZ property
     */
    public $blz;
    
    /**
     * @var string Name of the bank account property
     */
    public $konto;
    
    public $message = 'This value is not a valid German bank id (BLZ).';
    
    /**
     * {@inheritdoc}
     */
    public function getRequiredOptions()
    {
        return array('blz', 'konto');
    }
}
