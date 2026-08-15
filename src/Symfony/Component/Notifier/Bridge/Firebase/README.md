Firebase Notifier
=================

Provides [Firebase](https://firebase.google.com) integration for Symfony Notifier.

DSN example
-----------

```
FIREBASE_DSN=firebase://<CLIENT_EMAIL>@default?project_id=<PROJECT_ID>&private_key_id=<PRIVATE_KEY_ID>&private_key=<PRIVATE_KEY>
```

All 4 parameters are required. All 4 parameters are located inside your firebase json private key.

IMPORTANT: Make sure that `PRIVATE_KEY` is safely url encoded. Get more information in [Notifier documentation](https://symfony.com/doc/current/notifier.html#chat-channel) or use the script below.


Getting credentials for your DSN
--------------------------------

Steps for getting your private key:
 1. Log into the [firebase console](https://console.firebase.google.com/).
 2. Click on your project
 3. Click on the gear icon next to "Project Overview" and click on "Project settings"
 4. Click on "Service accounts" tab
 5. Click on "Generate new private key" button at the bottom
 6. A JSON file with your private key will be downloaded

The downloaded private key is a JSON file which should contain the following keys:
 * `type`
 * `project_id`
 * `private_key_id`
 * `private_key`
 * `client_email`
 * `client_id`
 * `auth_uri`
 * `token_uri`
 * `auth_provider_x509_cert_url`
 * `client_x509_cert_url`
 * `universe_domain`

You can then use the following script to convert your JSON private key to DSN format:

```php
<?php

if (!isset($argv[1])) {
    echo 'Usage: php ' . $argv[0] . ' path_to_your_key.json'.PHP_EOL;
    exit(1);
}

$file = file_get_contents($argv[1]);

if (false === $file) {
    echo 'Could not open file at path: '.$argv[1].PHP_EOL.PHP_EOL;
    exit(1);
}

$key = json_decode($file, true);

if (false === $key) {
    echo 'Unable to load JSON content of the file at path: '.$argv[1].PHP_EOL;
    exit(1);
}

foreach (['client_email', 'project_id', 'private_key_id', 'private_key'] as $param) {
    if (!isset($key[$param])) {
        echo 'Missing param '.$param.' inside the JSON key.'.PHP_EOL;
        exit(1);
    }
}

echo sprintf(
    'FIREBASE_DSN=firebase://%s@default?project_id=%s&private_key_id=%s&private_key=%s',
    $key['client_email'],
    $key['project_id'],
    $key['private_key_id'],
    urlencode($key['private_key']),
).PHP_EOL;
```

The script then can be used as follows:
```bash
php script_name.php path/to/your/firebase/key.json
```


Adding Interactions to a Message
--------------------------------

With a Firebase message, you can use the `FirebaseOptions` class to add
[platform specific message options](https://firebase.google.com/docs/reference/fcm/rest/v1/projects.messages#resource:-message).

Specifying Firebase options for a message:

```php
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Bridge\Firebase\FirebaseOptions;

$chatMessage = new ChatMessage('Hello, you should contribute to Symfony.');

// Specify options for Firebase
$firebaseOptions = (new FirebaseOptions('/topics/news'))
    ->title('New message!')
    ->body('This will overwrite the subject from ChatMessage object.')
    ->image('https://path-to-your-image.png')
    ->data([
        'key1' => 'value1',
        'key2' => 'value2',
    ])
    // ...
    ;

// Add the custom options to the chat message and send the message
$chatMessage->options($androidOptions);

$chatter->send($chatMessage);
```

Firebase offers 3 different types of targets to send the message to:
 * token
 * topic
 * condition

How to specify different targets for firebase messages:

```php
use Symfony\Component\Notifier\Bridge\Firebase\FirebaseOptions;
use Symfony\Component\Notifier\Bridge\Firebase\TargetType;

// Use a topic as a target
$topicOptions = new FirebaseOptions('/topics/news', targetType: TargetType::Topic);

// Use a token as a target
$tokenOptions = new FirebaseOptions('dU5e3nFJf9bE:APA91bH3Kd/exampleToken', targetType: TargetType::Token);

// Use a condition as a target
$conditionOptions = new FirebaseOptions('\'news\' in topics', targetType: TargetType::Condition);
```

Firebase also allows specifying platform specific options. They can be specified as follows:

```php
use Symfony\Component\Notifier\Bridge\Firebase\FirebaseOptions;
use Symfony\Component\Notifier\Bridge\Firebase\TargetType;

$detailedOptions = (new FirebaseOptions('dU5e3nFJf9bE:APA91bH3Kd/exampleToken', targetType: TargetType::Token))
    // Basic options
    ->title('New message!')
    ->data([
        'key1' => 'value1',
        'key2' => 'value2',
    ])
    // Android specific options
    ->android([
        'notification' => [
            'color' => '#4538D5',
        ],
    ])
    // WebPush specific options
    ->webpush([
        'notification' => [
            'icon' => 'https://path-to-an-icon.png',
        ],
    ])
    // APNS specific options
    ->apns([
        'payload' => [
            'aps' => [
                'sound' => 'default',
            ],
        ],
    ])
    // ...
    ;
```

For all available options please refer to the [firebase documentation](https://firebase.google.com/docs/reference/fcm/rest/v1/projects.messages).

Sponsor
-------

This package is looking for a [backer][1].

Help Symfony by [sponsoring][3] its development!

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)

[1]: https://symfony.com/backers
[3]: https://symfony.com/sponsor
