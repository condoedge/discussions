# condoedge/discussions

Channels, discussions and chat components for Kompo applications.

## What it provides

- **Models**: `Channel`, `Discussion` (self-referencing for replies/subjects), `DiscussionRead` (read receipts), `DiscussionBox` (archive/trash per user), `Member` (channel membership pivot).
- **Komponents**: `ChannelsView` (3-column chat page), `ChannelsList` / `ChannelsListTeam`, `ChannelSubjectsList`, `ChannelDiscussionsPanel`, `DiscussionForm`, `SingleDiscussionCard`, `ProjectDiscussionThread`, `ChannelSettingsForm`.
- **Policies**: `ChannelPolicy` / `DiscussionPolicy`, registered automatically (membership-based).
- **Broadcasting**: a `DiscussionSent` event broadcast on the private channel `discussion.{teamId}`; the channel authorization callback is registered by the package's service provider.

## Installation

```bash
composer require condoedge/discussions
```

The service provider is auto-discovered. Migrations load automatically (`php artisan migrate`).

## Route registration

The package does not auto-register routes. Call the static registrars from your app's route files, inside your auth middleware group:

```php
Route::middleware(['auth'])->group(function () {
    \Kompo\Discussions\Services\DiscussionsService::setAllRoutes();      // discussions pages
    \Kompo\Discussions\Services\DiscussionsService::setAllModalRoutes(); // channel-settings modal
});
```

## Live refresh (Pusher)

The chat lists refresh live through Laravel broadcasting. The host app must have broadcasting enabled:

1. Enable `App\Providers\BroadcastServiceProvider` in `config/app.php` (registers `/broadcasting/auth`).
2. Set `BROADCAST_DRIVER=pusher` and the `PUSHER_*` / `MIX_PUSHER_*` env values.
3. `composer require pusher/pusher-php-server`.
4. Make sure the front-end bundle initializes Echo (kompo's `withBroadcasting()`).

The package takes care of the rest: the `discussion.{teamId}` channel authorization (team membership) and the event-name wiring between `DiscussionSent::broadcastAs()` and the komponents' `pusherRefresh`.

## Styling

Import the package stylesheet in your app's scss:

```scss
@import "../../vendor/condoedge/discussions/resources/scss/discussions";
```

The chat page (`.discussions-page`) sizes itself to the area below your app's navbar; no fixed viewport heights are used.

## Host integration points

- Listen to `Kompo\Discussions\Events\DiscussionSent` (exposes `getDiscussion()`) for notifications.
- Override the team users available in a channel by defining `getAvailableUsersForChannel($channel, $search)` on your Team model.
- Translations are flat JSON keys (`discussions.*`) for the `en` and `fr` locales; app-level JSON translations override them.
