Microsoft Teams Notifier
========================

Provides [Microsoft Teams](https://www.microsoft.com/en/microsoft-365/microsoft-teams/free) integration
through Incoming Webhook for Symfony Notifier.

DSN example
-----------

```
MICROSOFT_TEAMS_DSN=microsoftteams://HOST/PATH
```

where:
 - `HOST/PATH` is the URL of a Workflows webhook without its `https://` prefix,
   query string included, for example
   `default123.45.environment.api.powerplatform.com:443/powerautomate/automations/direct/workflows/678/triggers/manual/paths/invoke?api-version=1&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=SIGNATURE`

To get this URL, create a workflow in the Teams channel from the "Send webhook
alerts to a channel" template. See [Send messages in Teams using incoming webhooks](https://support.microsoft.com/en-us/workflows/send-messages-in-teams-using-incoming-webhooks).

Office 365 connectors (`outlook.office.com/webhookb2/...` URLs) stopped working
in May 2026, see [their retirement notice](https://devblogs.microsoft.com/microsoft365dev/retirement-of-office-365-connectors-within-microsoft-teams/).

Adding text to a Message
------------------------

With a Microsoft Teams, you can use the `ChatMessage` class::

```php
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\MicrosoftTeamsTransport;
use Symfony\Component\Notifier\Message\ChatMessage;

$chatMessage = (new ChatMessage('Contribute To Symfony'))->transport('microsoftteams');
$chatter->send($chatMessage);
```

Adding Interactions to a Message
--------------------------------

With a Microsoft Teams Message, you can use the `MicrosoftTeamsOptions` class
to add [MessageCard options](https://docs.microsoft.com/en-us/outlook/actionable-messages/message-card-reference).
Note that Workflows webhooks display the content of MessageCards but not their
action buttons.

```php
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Action\ActionCard;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Action\HttpPostAction;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Action\Input\DateInput;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Action\Input\TextInput;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\MicrosoftTeamsOptions;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\MicrosoftTeamsTransport;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Section\Field\Fact;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Section\Section;
use Symfony\Component\Notifier\Message\ChatMessage;

$chatMessage = new ChatMessage('');

// Action elements
$input = new TextInput();
$input->id('input_title');
$input->isMultiline(true)->maxLength(5)->title('In a few words, why would you like to participate?');

$inputDate = new DateInput();
$inputDate->title('Proposed date')->id('input_date');

// Create Microsoft Teams MessageCard
$microsoftTeamsOptions = (new MicrosoftTeamsOptions())
    ->title('Symfony Online Meeting')
    ->text('Symfony Online Meeting are the events where the best developers meet to share experiences...')
    ->summary('Summary')
    ->themeColor('#F4D35E')
    ->section((new Section())
        ->title('Talk about Symfony 5.3 - would you like to join? Please give a shout!')
        ->fact((new Fact())
            ->name('Presenter')
            ->value('Fabien Potencier')
        )
        ->fact((new Fact())
            ->name('Speaker')
            ->value('Patricia Smith')
        )
        ->fact((new Fact())
            ->name('Duration')
            ->value('90 min')
        )
        ->fact((new Fact())
            ->name('Date')
            ->value('TBA')
        )
    )
    ->action((new ActionCard())
        ->name('ActionCard')
        ->input($input)
        ->input($inputDate)
        ->action((new HttpPostAction())
            ->name('Add comment')
            ->target('http://target')
        )
    )
;

// Add the custom options to the chat message and send the message
$chatMessage->options($microsoftTeamsOptions);
$chatter->send($chatMessage);
```

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
