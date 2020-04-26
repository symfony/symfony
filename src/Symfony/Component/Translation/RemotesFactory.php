<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation;

use Symfony\Component\Translation\Bridge\Firebase\FirebaseRemoteFactory;
use Symfony\Component\Translation\Bridge\FreeMobile\FreeMobileRemoteFactory;
use Symfony\Component\Translation\Bridge\Mattermost\MattermostRemoteFactory;
use Symfony\Component\Translation\Bridge\Nexmo\NexmoRemoteFactory;
use Symfony\Component\Translation\Bridge\OvhCloud\OvhCloudRemoteFactory;
use Symfony\Component\Translation\Bridge\RocketChat\RocketChatRemoteFactory;
use Symfony\Component\Translation\Bridge\Sinch\SinchRemoteFactory;
use Symfony\Component\Translation\Bridge\Slack\SlackRemoteFactory;
use Symfony\Component\Translation\Bridge\Telegram\TelegramRemoteFactory;
use Symfony\Component\Translation\Bridge\Twilio\TwilioRemoteFactory;
use Symfony\Component\Translation\Exception\UnsupportedSchemeException;
use Symfony\Component\Translation\Remote\Dsn;
use Symfony\Component\Translation\Remote\FailoverRemote;
use Symfony\Component\Translation\Remote\NullRemoteFactory;
use Symfony\Component\Translation\Remote\RoundRobinRemote;
use Symfony\Component\Translation\Remote\RemoteDecorator;
use Symfony\Component\Translation\Remote\RemoteFactoryInterface;
use Symfony\Component\Translation\Remote\RemoteInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class RemotesFactory
{
    private const FACTORY_CLASSES = [
        LocoRemoteFactory::class,
    ];

    private $factories;
    private $enabledLocales;

    /**
     * @param RemoteFactoryInterface[] $factories
     */
    public function __construct(iterable $factories, array $enabledLocales)
    {
        $this->factories = $factories;
        $this->enabledLocales = $enabledLocales;
    }

    public function fromConfig(array $config): Remotes
    {
        $remotes = [];
        foreach ($config as $name => $currentConfig) {
            $remotes[$name] = $this->fromString(
                $currentConfig['dsn'],
                empty($currentConfig['locales']) ? $this->enabledLocales : $currentConfig['locales'],
                empty($currentConfig['domains']) ? [] : $currentConfig['domains']
            );
        }

        return new Remotes($remotes);
    }

    public function fromString(string $dsn, array $locales, array $domains = []): RemoteInterface
    {
        return $this->fromDsnObject(Dsn::fromString($dsn), $locales, $domains);
    }

    public function fromDsnObject(Dsn $dsn, array $locales, array $domains = []): RemoteInterface
    {
        foreach ($this->factories as $factory) {
            if ($factory->supports($dsn)) {
                return new RemoteDecorator($factory->create($dsn), $locales, $domains);
            }
        }

        throw new UnsupportedSchemeException($dsn);
    }

    /**
     * @return RemoteFactoryInterface[]
     */
    private static function getDefaultFactories(HttpClientInterface $client = null): iterable
    {
        foreach (self::FACTORY_CLASSES as $factoryClass) {
            if (class_exists($factoryClass)) {
                yield new $factoryClass($client);
            }
        }

        yield new NullRemoteFactory($client);
    }
}
