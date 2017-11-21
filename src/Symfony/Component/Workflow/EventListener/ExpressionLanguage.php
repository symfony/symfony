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
    protected function registerFunctions()
    {
        parent::registerFunctions();

        $this->register('is_granted', function ($attributes, $object = 'null') {
            return sprintf('$auth_checker->isGranted(%s, %s)', $attributes, $object);
        }, function (array $variables, $attributes, $object = null) {
            return $variables['auth_checker']->isGranted($attributes, $object);
        });

        $this->register('is_valid', function ($object = 'null', $groups = 'null') {
            return sprintf('0 === count($validator->validate(%s, null, %s))', $object, $groups);
        }, function (array $variables, $object = null, $groups = null) {
            if (!$variables['validator'] instanceof ValidatorInterface) {
                throw new RuntimeException('"is_valid" cannot be used as the Validator component is not installed.');
            }

            $errors = $variables['validator']->validate($object, null, $groups);

            return 0 === count($errors);
        });
    }
}
