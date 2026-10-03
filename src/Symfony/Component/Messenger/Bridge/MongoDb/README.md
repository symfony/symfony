MongoDB Messenger
=================

Provides MongoDB integration for Symfony Messenger.

DSN example
-----------

```
MESSENGER_TRANSPORT_DSN=mongodb://user:pass@mongodb1.example.com:27017/db_name?collection_name=messenger_messages&queue_name=default
```

The transport reads its own settings (`database`, `collection_name`, `queue_name`,
`redeliver_timeout`) from the query string and passes any other parameter to the
MongoDB driver, so [connection options](https://www.mongodb.com/docs/manual/reference/connection-string-options/)
keep working:

```
MESSENGER_TRANSPORT_DSN=mongodb+srv://mongodb.example.com/db_name?replicaSet=repl&connectTimeoutMS=3000
```

Listening to several queues
---------------------------

A transport sends to a single queue, named by the `queue_name` option, and by
default reads only from that queue. Queues listened to together by one receiver
must live in the same namespace in the same MongoDB cluster, since the queue
name is a field of the message document. Declare one transport per queue, so
the routing can target each:

```yaml
framework:
    messenger:
        transports:
            foo: 'mongodb://host/db?queue_name=foo'
            bar: 'mongodb://host/db?queue_name=bar'
        routing:
            App\FooMessage: foo
            App\BarMessage: bar
```

Then consume them all with one worker:

```
messenger:consume foo --queues=foo --queues=bar
```

A receiver consumes every listed queue with its own settings: with
`messenger:consume foo --queues=bar`, a message of `bar` that exhausted its
retries goes to the failure transport configured on `foo`.

See [Limit Consuming to Specific Queues](https://symfony.com/doc/current/messenger.html#limit-consuming-to-specific-queues)
in the Messenger documentation.

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
