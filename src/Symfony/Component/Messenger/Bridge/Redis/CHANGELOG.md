CHANGELOG
=========

5.1.0
-----

 * Introduced the Redis bridge.
 * Added TLS option in the DSN. Example: `redis://127.0.0.1?tls=1`
 * Deprecated use of invalid options
 * Added ability to receive of old pending messages with new `redeliver_timeout`
   and `claim_interval` options.
 * Added a `delete_after_ack` option to the DSN as an alternative to
   `stream_max_entries` to avoid leaking memory.
