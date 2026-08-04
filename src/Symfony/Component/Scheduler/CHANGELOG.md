CHANGELOG
=========

8.2
---

 * Add a "Next Run In" column to `debug:scheduler` showing the time until the next run
 * Add `env` option to `#[AsCronTask]` and `#[AsPeriodicTask]` to restrict a task to one or more environments
 * Deprecate `Schedule::with()`, use `add()` on a new `Schedule` instead
 * `AddScheduleMessengerPass` now wraps scheduled messages in a `RedispatchMessage` when the container parameter
   `scheduler.use_messenger_routing` is `true`, even without an explicit `transports` option, so they go through
   the Messenger senders configured for their class instead of being handled synchronously by the scheduler worker
   (see `framework.scheduler.use_messenger_routing` in FrameworkBundle)

8.1
---

 * Add `--sort` option to `debug:scheduler` to order recurring messages by next run date; with `--all`, rows with no next run date appear first

7.3
---

 * Add `TriggerNormalizer`
 * Throw exception when multiple schedule provider services are registered under the same scheduler name

7.2
---

 * Add capability to skip missed periodic tasks, only the last schedule will be called
 * Add MessageHandler returned result to `PostRunEvent`

6.4
---

 * Mark the component as non experimental
 * [BC BREAK] Add `from()` to `CheckpointInterface`
 * Add `--date` and `--all` options to the `schedule:debug` command
 * Allow setting timezone of next run date in CronExpressionTrigger
 * Add `AbstractTriggerDecorator`
 * Make `ScheduledStamp` "send-able"
 * Add `ScheduledStamp` to `RedispatchMessage`
 * Allow modifying Schedule instances at runtime
 * Add `MessageProviderInterface` to trigger unique messages at runtime
 * Add `PreRunEvent` and `PostRunEvent` events
 * Add `DispatchSchedulerEventListener`
 * Add `FailureEvent` event

6.3
---

 * Add the component as experimental
