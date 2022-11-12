Pusher Notifier
==============

Provides [Pusher](https://pusher.com) integration for Symfony Notifier.

DSN example
-----------

```
PUSHER_DSN=pusher://APP_KEY:APP_SECRET@APP_ID?server=SERVER
```

where:

- `APP_KEY` is your app unique key
- `APP_SECRET` is your app unique and secret password
- `APP_ID` is your app unique id
- `SERVER` is your app server

valid DSN's are:

```
PUSHER_DSN=pusher://as8d09a0ds8:as8d09a8sd0a8sd0@123123123?server=mt1
```

invalid DSN's are:

```
PUSHER_DSN=pusher://asdasdasd@asdasdasd?server=invalid-server
PUSHER_DSN=pusher://:asdasdasd@asdasdasd?server=invalid-server
PUSHER_DSN=pusher://asdadasdasd:asdasdasd@asdasdasd?server=invalid-server
```
