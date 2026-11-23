CHANGELOG
=========

8.3
---

 * Make `DoctrineReceiver` and `DoctrineTransport` implement `ReleasableReceiverInterface`, so that the messages a worker fetched but did not start handling are available again as soon as it stops, instead of after `redeliver_timeout`
 * Make `DoctrineReceiver::keepalive()` leave alone a message that another worker received in the meantime, after `redeliver_timeout`

8.2
---

 * Make `DoctrineSender` and `DoctrineTransport` implement `BatchSenderInterface`, inserting several messages per statement
 * Add `DoctrineInbox` to record the handled messages in a database table
 * Make the Doctrine transport reconnect on the next poll after losing its database connection, and replay `ack()` and `reject()` when the connection comes back within 5 seconds

8.1
---

 * Add `PostgreSqlNotifyOnIdleListener` to properly support LISTEN/NOTIFY with multi-queue workers

7.3
---

 * Add "keepalive" support

7.1
---

 * Use `SKIP LOCKED` in the doctrine transport for MySQL, PostgreSQL and MSSQL

5.1.0
-----

 * Introduced the Doctrine bridge.
 * Added support for PostgreSQL `LISTEN`/`NOTIFY`.
