CHANGELOG
=========

5.2.0
-----

 * Added a `delete_after_reject` option to the DSN to allow control over message
   deletion, similar to `delete_after_ack`.
 * Added option `lazy` to delay connecting to Redis server until we first use it.

5.1.0
-----

 * Introduced the Redis bridge.
 * Added TLS option in the DSN. Example: `redis://127.0.0.1?tls=1`
 * Deprecated use of invalid options
 * Added ability to receive of old pending messages with new `redeliver_timeout`
   and `claim_interval` options.
 * Added a `delete_after_ack` option to the DSN as an alternative to
   `stream_max_entries` to avoid leaking memory.
