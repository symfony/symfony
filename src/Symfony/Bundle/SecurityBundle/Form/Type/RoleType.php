<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RoleType extends AbstractType
{
    private $roleHierarchy;

    public function __construct(array $roleHierarchy)
    {
        $this->roleHierarchy = $roleHierarchy;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'choices' => $this->getKnownRoles(),
            'multiple' => true,
            'expanded' => true,
        ));
    }

    public function getKnownRoles()
    {
        $roles = array();

        foreach ($this->roleHierarchy as $parent => $children) {
            $roles[$parent] = $parent;

            foreach ($children as $child) {
                $roles[$child] = $child;
            }
        }

        return $roles;
    }

    public function getBlockPrefix()
    {
        return 'role';
    }

    public function getParent()
    {
        return ChoiceType::class;
    }
}
