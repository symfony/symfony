<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\EventListener;

use Symfony\Component\Security\Core\Authorization\ExpressionLanguage as BaseExpressionLanguage;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Workflow\Exception\RuntimeException;

/**
 * Adds some function to the default Symfony Security ExpressionLanguage.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ExpressionLanguage extends BaseExpressionLanguage
{
    /**
     * @return void
     */
    protected function registerFunctions()
    {
        parent::registerFunctions();

        $this->register('is_granted', fn ($attributes, $object = 'null') => sprintf('$auth_checker->isGranted(%s, %s)', $attributes, $object), fn (array $variables, $attributes, $object = null) => $variables['auth_checker']->isGranted($attributes, $object));

        $this->register('is_valid', fn ($object = 'null', $groups = 'null') => sprintf('0 === count($validator->validate(%s, null, %s))', $object, $groups), function (array $variables, $object = null, $groups = null) {
            if (!$variables['validator'] instanceof ValidatorInterface) {
                throw new RuntimeException('"is_valid" cannot be used as the Validator component is not installed. Try running "composer require symfony/validator".');
            }

            $errors = $variables['validator']->validate($object, null, $groups);

            return 0 === \count($errors);
        });
    }
}
