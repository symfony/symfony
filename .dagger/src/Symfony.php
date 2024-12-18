<?php

declare(strict_types=1);

namespace DaggerModule;

use Dagger\Attribute\DaggerFunction;
use Dagger\Attribute\DaggerObject;
use Dagger\Attribute\Doc;
use Dagger\Container;
use Dagger\Directory;
use Dagger\Service;

use function Dagger\dag;

#[DaggerObject]
#[Doc('A generated module for Symfony functions')]
class Symfony
{

    // +----------------------+
    // | STATIC ANALYSIS CODE |
    // +----------------------+

    #[DaggerFunction]
    #[Doc('Run phpstan across the entire symfony codebase')]
    // dagger call phpstan --source=.
    public function phpstan(Directory $source): Container
    {
        return dag()
            ->phpstan()
            ->analyze('8.2', $source, 'src');
    }

    #[DaggerFunction]
    #[Doc('Run phpstan across all components in Symfony')]
    // dagger call phpstan-components --source=.
    public function phpstanComponents(Directory $source): Container
    {
        return dag()
            ->phpstan()
            ->analyze('8.2', $source, 'src/Symfony/Component');
    }

    #[DaggerFunction]
    #[Doc('Run phpstan across on a specific Symfony component')]
    // dagger call phpstan-component --source=. --component=Asset
    public function phpstanComponent(Directory $source, string $component): Container
    {
        return dag()
            ->phpstan()
            ->analyze('8.2', $source, "src/Symfony/Component/$component");
    }


    #[DaggerFunction]
    #[Doc('Run psalm across the entire symfony codebase')]
    // dagger call psalm --source=.
    public function psalm(Directory $source): Container
    {
        return dag()
            ->psalm()
            ->run('8.2', $source, 'src');
    }


    #[DaggerFunction]
    #[Doc('Run phpstan across all components in Symfony')]
    // dagger call psalm-components --source=.
    public function psalmComponents(Directory $source): Container
    {
        return dag()
            ->psalm()
            ->run('8.2', $source, 'src/Symfony/Component');
    }

    #[DaggerFunction]
    #[Doc('Run psalm across on a specific Symfony component')]
    // dagger call psalm-component --source=. --component=Asset
    public function psalmComponent(Directory $source, string $component): Container
    {
        return dag()
            ->psalm()
            ->run('8.2', $source, "src/Symfony/Component/$component");
    }


    // +------------------------+
    // | INTEGRATION TESTS CODE |
    // +------------------------+

    private function phpBase()
    {
        // Extensions - json,couchbase-3.2.2,memcached,mongodb-1.12.0,redis,rdkafka,xsl,ldap,relay
        // Ini Values - date.timezone=UTC,memory_limit=-1,default_socket_timeout=10,session.gc_probability=0,apc.enable_cli=1,zend.assertions=1
        // tools pecl
    }

    #[DaggerFunction]
    #[Doc('Run psalm across on a specific Symfony component')]
    // dagger call integration-tests --source=.
    public function integrationTests(Directory $source): Container
    {
        return dag()->container()
            ->from('php:8.4-alpine')
            ->withServiceBinding('redis', $this->redis())
            ->withServiceBinding('postgres', $this->postgres())
            ->withServiceBinding('redis-authenticated', $this->redisAuthenticated())
            ->withServiceBinding('redis-cluster', $this->redisCluster())
            ->withServiceBinding('redis-sentinel', $this->redisSentinel())
            ->withServiceBinding('memcached', $this->memcached())
            ->withServiceBinding('rabbitmq', $this->rabbitmq())
            ->withServiceBinding('mongodb', $this->mongodb())
            ->withServiceBinding('couchbase', $this->couchbase())
            // ->withServiceBinding('sqs', $this->sqs())
            ->withServiceBinding('zookeeper', $this->zookeeper())
            ->withExec(['php', '-v']);
    }

    private function runIntegrationTests(Container $container): Container
    {
        return $container
        ->withExec($this->cmd("./phpunit --group integration -v"))
        ->withEnvVariable('INTEGRATION_FTP_URL', 'ftp://test:test@localhost')
        ->withEnvVariable('REDIS_HOST', 'localhost:16379')
        ->withEnvVariable('REDIS_AUTHENTICATED_HOST', 'localhost')
        ->withEnvVariable('REDIS_CLUSTER_HOSTS', 'localhost:7000 localhost:7001 localhost:7002 localhost:7003 localhost:7004 localhost:7005')
        ->withEnvVariable('REDIS_SENTINEL_HOSTS', 'unreachable-host:26379 localhost:26379 localhost:26379')
        ->withEnvVariable('REDIS_SENTINEL_SERVICE', 'redis_sentinel')
        ->withEnvVariable('MESSENGER_REDIS_DSN', 'redis://127.0.0.1:7006/messages')
        ->withEnvVariable('MESSENGER_AMQP_DSN', 'amqp://localhost/%2f/messages')
        ->withEnvVariable('MESSENGER_SQS_DSN', 'sqs://localhost:4566/messages?sslmode=disable&poll_timeout=0.01')
        ->withEnvVariable('MESSENGER_SQS_FIFO_QUEUE_DSN', 'sqs://localhost:4566/messages.fifo?sslmode=disable&poll_timeout=0.01')
        ->withEnvVariable('KAFKA_BROKER', '127.0.0.1:9092')
        ->withEnvVariable('POSTGRES_HOST', 'localhost')
        ->withEnvVariable('PGBOUNCER_HOST', 'localhost:6432');
    }



    private function redis($version = '6.2.8'): Service
    {
        return dag()->container()
            ->from("redis:$version")
            ->withExposedPort(6379)
            ->asService();
    }

    private function postgres($version = '16'): Service
    {
        return dag()->container()
            ->from("postgres:$version-alpine")
            ->withExposedPort(5432)
            ->asService();
    }

    private function redisAuthenticated($version = '6.2.8'): Service
    {
        return dag()->container()
            ->from("redis:$version-alpine")
            ->withExposedPort(6379)
            ->withEnvVariable('REDIS_ARGS', '--requirepass p@ssword')
            ->asService();
    }

    private function redisCluster($version = '6.2.8'): Service
    {
        return dag()->container()
            ->from("grokzen/redis-cluster:$version")
            ->withExposedPort(7000)
            ->withExposedPort(7001)
            ->withExposedPort(7002)
            ->withExposedPort(7003)
            ->withExposedPort(7004)
            ->withExposedPort(7005)
            ->withExposedPort(7006)
            ->withEnvVariable('STANDALONE', '1')
            ->asService();
    }

    private function redisSentinel($version = '6.2.8'): Service
    {
        return dag()->container()
            ->from("bitnami/redis-sentinel:$version")
            ->withExposedPort(26379)
            ->withEnvVariable('REDIS_MASTER_HOST', 'redis')
            ->withEnvVariable('REDIS_MASTER_SET', 'redis_sentinel')
            ->withEnvVariable('REDIS_SENTINEL_QUORUM', '1')
            ->asService();
    }

    private function memcached($version = '1.6.5'): Service
    {
        return dag()->container()
            ->from("memcached:$version")
            ->withExposedPort(11211)
            ->asService();
    }

    private function rabbitmq($version = '3.8.3'): Service
    {
        return dag()->container()
            ->from("rabbitmq:$version")
            ->withExposedPort(5672)
            ->asService();
    }

    // @todo - this failed to come up - why ?
    private function mongodb($version = '3.8.3'): Service
    {
        return dag()->container()
            ->from("mongo")
            ->withExposedPort(27017)
            ->asService();
    }

    private function couchbase($version = '6.5.1'): Service
    {
        return dag()->container()
            ->from("couchbase")
            ->withExposedPort(8091)
            ->withExposedPort(8092)
            ->withExposedPort(8093)
            ->withExposedPort(8094)
            ->withExposedPort(11210)
            ->asService();
    }

    private function sqs($version = '3.0.2'): Service
    {
        return dag()->container()
            ->from("localstack/localstack:$version")
            ->withExposedPort(4566)
            ->asService();
    }

    private function zookeeper(): Service
    {
        return dag()->container()
            ->from("zookeeper")
            ->asService();
    }    

    // @todo - kafka how to do "options" YML key

    // @todo -frankenphp how to do volumes, and how to do CADDY_SERVER_EXTRA_DIRECTIVES setup

    private function initKafka(Container $container): Container
    {
        return $container
        ->withExec($this->cmd(
            'docker exec kafka /opt/bitnami/kafka/bin/kafka-topics.sh --create --topic test-topic --bootstrap-server kafka:9092'
        ));
    }

    private function setupCouchbase(Container $container): Container
    {
        return $container
        ->withExec($this->cmd('sudo wget -O - https://packages.couchbase.com/clients/c/repos/deb/couchbase.key | sudo apt-key add -'))
        ->withExec($this->cmd('echo "deb https://packages.couchbase.com/clients/c/repos/deb/ubuntu2004 focal focal/main" | sudo tee /etc/apt/sources.list.d/couchbase.list'))
        ->withExec($this->cmd('apt-get update'));
    }

    private function installTools(Container $container): Container
    {
        return $container
        ->withExec($this->cmd('sudo apt-get install librdkafka-dev redis-server libcouchbase-dev'))
        ->withExec($this->cmd("sudo -- sh -c 'echo unixsocket /var/run/redis/redis-server.sock >> /etc/redis/redis.conf'"))
        ->withExec($this->cmd("sudo -- sh -c 'echo unixsocketperm 777 >> /etc/redis/redis.conf'"))
        ->withExec($this->cmd('sudo service redis-server restart'));
    }

    private function installPbBouncer(Container $container): Container
    {
        return $container
        ->withExec($this->cmd("curl -s -u 'username=Administrator&password=111111@' -X POST http://localhost:8091/node/controller/setupServices -d 'services=kv%2Cn1ql%2Cindex%2Cfts'"))
        ->withExec($this->cmd("curl -s -X POST http://localhost:8091/settings/web -d 'username=Administrator&password=111111%40&port=SAME'"))
        ->withExec($this->cmd("curl -s -u Administrator:111111@ -X POST http://localhost:8091/pools/default/buckets -d 'ramQuotaMB=100&bucketType=ephemeral&name=cache'"))
        ->withExec($this->cmd("curl -s -u Administrator:111111@ -X POST http://localhost:8091/pools/default -d 'memoryQuota=256'"));
    }

    private function createFtpFixtures(Container $container): Container
    {
        return $container
        ->withExec($this->cmd("mkdir -p ./ftpusers/test/pub"))
        ->withExec($this->cmd("touch ./ftpusers/test/pub/example ./ftpusers/test/readme.txt"));
    }

    private function loadLdapFixtures(Container $container): Container
    {
        // - name: Load fixtures
        // uses: docker://bitnami/openldap
        // with:
        //   entrypoint: /bin/bash
        //   args: -c "(/opt/bitnami/openldap/bin/ldapwhoami -H ldap://ldap:3389 -D cn=admin,dc=symfony,dc=com -w symfony||sleep 5) && /opt/bitnami/openldap/bin/ldapadd -H ldap://ldap:3389 -D cn=admin,dc=symfony,dc=com -w symfony -f src/Symfony/Component/Ldap/Tests/Fixtures/data/fixtures.ldif && /opt/bitnami/openldap/bin/ldapdelete -H ldap://ldap:3389 -D cn=admin,dc=symfony,dc=com -w symfony cn=a,ou=users,dc=symfony,dc=com"

    }

    private function installComposerDeps(Container $container): Container
    {
        // COMPOSER_HOME="$(composer config home)"
        // ([ -d "$COMPOSER_HOME" ] || mkdir "$COMPOSER_HOME") && cp .github/composer-config.json "$COMPOSER_HOME/config.json"
        // export COMPOSER_ROOT_VERSION=$(grep ' VERSION = ' src/Symfony/Component/HttpKernel/Kernel.php | grep -P -o '[0-9]+\.[0-9]+').x-dev
        // echo COMPOSER_ROOT_VERSION=$COMPOSER_ROOT_VERSION >> $GITHUB_ENV

        // composer update --no-progress --ansi

        // ./phpunit install
    }

    private function checkTranslationFileChanges(Container $container): Container
    {
        // echo 'changed='$((git diff --quiet HEAD~1 HEAD -- 'src/**/Resources/translations/*.xlf' || (echo 'true' && exit 1)) && echo 'false') >> $GITHUB_OUTPUT
    }

    private function checkTransltionStatus(Container $container): Container
    {
        //   php src/Symfony/Component/Translation/Resources/bin/translation-status.php -v
        //   php .github/sync-translations.php
        //   git diff --exit-code src/ || (echo '::error::Run "php .github/sync-translations.php" to fix XLIFF files.' && exit 1)
    }         


    private function cmd(string $cmd)
    {
        return ["/bin/sh", "-c", $cmd];
    }


}
