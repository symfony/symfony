CHANGELOG
=========

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
