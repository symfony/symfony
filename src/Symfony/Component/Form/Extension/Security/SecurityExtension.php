<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Security;

use Symfony\Component\Form\AbstractExtension;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

/**
 * Integrates the Security (Core) component with the Form library.
 *
 * @author Loïck Piera <pyrech@gmail.com>
 */
class SecurityExtension extends AbstractExtension
{
    private $passwordEncoder;

    public function __construct(UserPasswordEncoderInterface $passwordEncoder = null)
    {
        $this->passwordEncoder = $passwordEncoder;
    }

    protected function loadTypes()
    {
        if (!$this->passwordEncoder) {
            return [];
        }

        return [
            new Type\SecurityPasswordType($this->passwordEncoder),
        ];
    }
}
