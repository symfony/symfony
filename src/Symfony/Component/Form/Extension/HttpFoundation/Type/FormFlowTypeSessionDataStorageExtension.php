<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\HttpFoundation\Type;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Flow\DataStorage\SessionDataStorage;
use Symfony\Component\Form\Flow\FormFlowBuilderInterface;
use Symfony\Component\Form\Flow\Type\FormFlowType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class FormFlowTypeSessionDataStorageExtension extends AbstractTypeExtension
{
    public function __construct(
        private readonly ?RequestStack $requestStack = null,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (!$builder instanceof FormFlowBuilderInterface) {
            throw new \InvalidArgumentException(\sprintf('The "%s" can only be used with FormFlowType.', self::class));
        }

        if (null === $this->requestStack || null !== $options['data_storage']) {
            return;
        }

        $key = \sprintf('_sf_formflow.%s_%s', strtolower(str_replace('\\', '_', $builder->getType()->getInnerType()::class)), $builder->getName());
        $builder->setDataStorage(new SessionDataStorage($key, $this->requestStack));
    }

    public static function getExtendedTypes(): iterable
    {
        return [FormFlowType::class];
    }
}
