MongoDB Messenger
=================

Provides MongoDB integration for Symfony Messenger.

DSN example
-----------

```
MESSENGER_TRANSPORT_DSN=mongodb://user:pass@mongodb1.example.com:27017/db_name?collection_name=messenger_messages&queue_name=default
```

The transport reads its own settings (`database`, `collection_name`, `queue_name`,
`redeliver_timeout`, `wait_time`) from the query string and passes any
other parameter to the MongoDB driver, so
[connection options](https://www.mongodb.com/docs/manual/reference/connection-string-options/)
keep working:

```
MESSENGER_TRANSPORT_DSN=mongodb+srv://mongodb.example.com/db_name?replicaSet=repl&connectTimeoutMS=3000
```

Change streams
--------------

The transport uses a MongoDB [change stream](https://www.mongodb.com/docs/manual/changeStreams/)
only to be notified as soon as a message is inserted, reducing the latency of
fresh messages versus polling for the whole `--sleep` period. The trade-off:
delayed (`DelayStamp`) and redelivered messages fire no event, so they wait for
the poll claim and can be delayed by up to `wait_time`.

Change streams are best effort: they require a replica set or a sharded cluster
and fall back to polling otherwise. A fresh stream is opened per wait cycle and
dropped once a message is claimed or the budget is spent, so no cursor or
session stays open. `wait_time` (default `1`, `0` or less disables change
streams) bounds each wait and is capped at `300` seconds so delayed and
redelivered messages are never starved.

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
