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

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
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
     * {@inheritdoc}
     */
    public function __construct(CacheItemPoolInterface $cache = null, array $providers = [])
    {
        parent::__construct($cache, $providers);

        foreach (['is_anonymous', 'is_authenticated', 'is_fully_authenticated', 'is_granted', 'is_remember_me'] as $name) {
            if (!isset($this->functions[$name])) {
                continue;
            }
            $compiler = $this->functions[$name]['compiler'];
            $evaluator = $this->functions[$name]['evaluator'];
            $this->register($name, $compiler, function (array $variables, $attributes, $object = null) use ($name, $evaluator) {
                if (!isset($variables['auth_checker']) || !$variables['auth_checker'] instanceof AuthorizationCheckerInterface) {
                    throw new RuntimeException(sprintf('"%s" cannot be used as the SecurityBundle is not registered in your application.', $name));
                }

                return $evaluator($variables, $attributes, $object);
            });
        }
    }

    protected function registerFunctions()
    {
        parent::registerFunctions();

        $this->register('is_valid', function ($object = 'null', $groups = 'null') {
            return sprintf('0 === count($validator->validate(%s, null, %s))', $object, $groups);
        }, function (array $variables, $object = null, $groups = null) {
            if (!$variables['validator'] instanceof ValidatorInterface) {
                throw new RuntimeException('"is_valid" cannot be used as the Validator component is not installed.');
            }

            $errors = $variables['validator']->validate($object, null, $groups);

            return 0 === \count($errors);
        });
    }
}
