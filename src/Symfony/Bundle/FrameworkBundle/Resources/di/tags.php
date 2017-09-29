<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator\tag {
    const AUTO_ALIAS = 'auto_alias';
    const DATA_COLLECTOR = 'data_collector';
}

namespace Symfony\Component\DependencyInjection\Loader\Configurator\tag\config_cache {
    const RESOURCE_CHECKER = 'config_cache.resource_checker';
}

namespace Symfony\Component\DependencyInjection\Loader\Configurator\tag\console {
    const COMMAND = 'console.command';
}

namespace Symfony\Component\DependencyInjection\Loader\Configurator\tag\container {
    const ENV_VAR_PROCESSOR = 'container.env_var_processor';
    const SERVICE_SUBSCRIBER = 'container.service_subscriber';
}

namespace Symfony\Component\DependencyInjection\Loader\Configurator\tag\controller {
    const ARGUMENT_VALUE_RESOLVER = 'controller.argument_value_resolver';
    const SERVICE_ARGUMENTS = 'controller.service_arguments';
}

namespace Symfony\Component\DependencyInjection\Loader\Configurator\tag\form {
    const TYPE = 'form.type';
    const TYPE_GUESSER = 'form.type_guesser';
}

namespace Symfony\Component\DependencyInjection\Loader\Configurator\tag\kernel {
    const CACHE_CLEARER = 'kernel.cache_clearer';
    const CACHE_WARMER = 'kernel.cache_warmer';
    const EVENT_SUBSCRIBER = 'kernel.event_subscriber';
    const RESET = 'kernel.reset';
}

namespace Symfony\Component\DependencyInjection\Loader\Configurator\tag\property_info {
    const ACCESS_EXTRACTOR = 'property_info.access_extractor';
    const DESCRIPTION_EXTRACTOR = 'property_info.description_extractor';
    const LIST_EXTRACTOR = 'property_info.list_extractor';
    const TYPE_EXTRACTOR = 'property_info.type_extractor';
}

namespace Symfony\Component\DependencyInjection\Loader\Configurator\tag\serializer {
    const ENCODER = 'serializer.encoder';
    const NORMALIZER = 'serializer.normalizer';
}

namespace Symfony\Component\DependencyInjection\Loader\Configurator\tag\validator {
    const CONSTRAINT_VALIDATOR = 'validator.constraint_validator';
    const INITIALIZER = 'validator.initializer';
}
