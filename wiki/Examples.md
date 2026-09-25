# Examples

Complete bots you can copy. Each one runs with long polling (`$bot->poll()`); replace it with `$bot->handle()` behind a webhook. The repository's [`examples/`](https://github.com/LightYagami28/TGbotPHP/tree/main/examples) directory has two more.

All examples start the same way:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use TGbotPHP\Framework\Bot;

$bot = new Bot(getenv('TELEGRAM_BOT_TOKEN'));
```

## Menu with pages

```php
use TGbotPHP\Support\Value;
use TGbotPHP\Utilities\Formatter;
use TGbotPHP\Utilities\Keyboard;

$products = ['Coffee', 'Tea', 'Cocoa', 'Juice', 'Water', 'Milk', 'Soda'];
$perPage = 3;
$pages = (int) ceil(count($products) / $perPage);

$render = function (int $page) use ($products, $perPage): string {
    $lines = array_slice($products, ($page - 1) * $perPage, $perPage);

    return Formatter::bold("Menu, page $page") . "\n" . implode("\n", array_map(Formatter::escape(...), $lines));
};

$bot->command('menu', function (stdClass $message, Bot $bot) use ($render, $pages): void {
    $bot->reply($message, $render(1), ['reply_markup' => Keyboard::pagination(1, $pages, 'menu:')]);
});

$bot->callback('menu:*', function (stdClass $callback, Bot $bot, array $matches) use ($render, $pages): void {
    $page = max(1, min($pages, Value::int($matches[1])));

    $bot->answer($callback);
    $bot->edit($callback, $render($page), ['reply_markup' => Keyboard::pagination($page, $pages, 'menu:')]);
});

$bot->poll();
```

## Feedback form

A conversation that asks two questions, then forwards the answers to the owner. With a webhook, replace `ArrayCache` with a `FileCache`.

```php
use TGbotPHP\Cache\ArrayCache;
use TGbotPHP\Support\Value;
use TGbotPHP\Utilities\Formatter;
use TGbotPHP\Utilities\Keyboard;

$ownerId = (int) getenv('OWNER_CHAT_ID');

$bot->useConversations(new ArrayCache(), ttl: 600);

$bot->command('feedback', function (stdClass $message, Bot $bot): void {
    $bot->setState($message, 'feedback:rating');
    $bot->reply($message, 'How would you rate us?', [
        'reply_markup' => Keyboard::reply([['⭐', '⭐⭐', '⭐⭐⭐']], oneTime: true),
    ]);
});

$bot->state('feedback:rating', function (stdClass $message, Bot $bot): void {
    $bot->setState($message, 'feedback:comment', ['rating' => Value::string($message->text ?? null)]);
    $bot->reply($message, 'Anything to add?', ['reply_markup' => Keyboard::remove()]);
});

$bot->state('feedback:comment', function (stdClass $message, Bot $bot, array $data) use ($ownerId): void {
    $bot->clearState($message);

    $bot->sendMessage($ownerId, sprintf(
        "New feedback from %s\nRating: %s\n%s",
        Formatter::mention($message->from->id, $message->from->first_name),
        Formatter::escape(Value::string($data['rating'])),
        Formatter::escape(Value::string($message->text ?? null)),
    ));

    $bot->reply($message, 'Thank you!');
});

$bot->command('cancel', function (stdClass $message, Bot $bot): void {
    $bot->clearState($message);
    $bot->reply($message, 'Cancelled.', ['reply_markup' => Keyboard::remove()]);
});

$bot->poll();
```

## Group moderation

Welcomes new members and lets administrators ban a user by replying `/ban` to one of their messages. Add the bot to a group as an administrator with the *Ban users* right.

```php
use TGbotPHP\Exceptions\ApiException;
use TGbotPHP\Utilities\Formatter;

$isAdmin = function (Bot $bot, int|string $chatId, int $userId): bool {
    $status = $bot->getChatMember($chatId, $userId)['status'] ?? null;

    return in_array($status, ['creator', 'administrator'], true);
};

$bot->onUpdate('chat_member', function (stdClass $update, Bot $bot): void {
    $wasOut = in_array($update->old_chat_member->status, ['left', 'kicked'], true);

    if ($wasOut && $update->new_chat_member->status === 'member') {
        $bot->sendMessage($update->chat->id, 'Welcome, ' . Formatter::mention(
            $update->new_chat_member->user->id,
            $update->new_chat_member->user->first_name,
        ) . '!');
    }
});

$bot->command('ban', function (stdClass $message, Bot $bot) use ($isAdmin): void {
    $target = $message->reply_to_message->from ?? null;

    if ($target === null || !$isAdmin($bot, $message->chat->id, $message->from->id)) {
        return;
    }

    try {
        $bot->banChatMember($message->chat->id, $target->id);
        $bot->reply($message, Formatter::escape($target->first_name) . ' was banned.');
    } catch (ApiException $e) {
        $bot->reply($message, 'I could not ban them: ' . Formatter::escape($e->getMessage()));
    }
});

// chat_member updates are only sent when asked for
$bot->poll(allowedUpdates: ['message', 'chat_member']);
```

## Inline search

Users type `@your_bot php` in any chat and pick a result. Enable inline mode with `/setinline` in @BotFather.

```php
$docs = [
    'arrays' => 'https://www.php.net/manual/en/language.types.array.php',
    'strings' => 'https://www.php.net/manual/en/language.types.string.php',
    'enums' => 'https://www.php.net/manual/en/language.enumerations.php',
    'readonly' => 'https://www.php.net/manual/en/language.oop5.properties.php#language.oop5.properties.readonly-properties',
];

$bot->inlineQuery('*', function (stdClass $query, Bot $bot) use ($docs): void {
    $term = strtolower(trim($query->query));
    $results = [];

    foreach ($docs as $topic => $url) {
        if ($term === '' || str_contains($topic, $term)) {
            $results[] = [
                'type' => 'article',
                'id' => $topic,
                'title' => "PHP $topic",
                'url' => $url,
                'input_message_content' => ['message_text' => "PHP manual: $topic\n$url"],
            ];
        }
    }

    $bot->answerInlineQuery($query->id, $results, cacheTime: 300);
});

$bot->poll();
```

## Selling with Telegram Stars

Digital goods are paid in Telegram Stars (currency `XTR`), without a payment provider.

```php
$bot->command('buy', function (stdClass $message, Bot $bot): void {
    $bot->sendInvoice(
        chatId: $message->chat->id,
        title: 'Pro plan',
        description: 'One month of Pro features',
        payload: 'pro-1m-' . $message->from->id,
        currency: 'XTR',
        prices: [['label' => 'Pro plan', 'amount' => 100]],
    );
});

// Telegram asks for confirmation before charging: answer within 10 seconds
$bot->onUpdate('pre_checkout_query', function (stdClass $query, Bot $bot): void {
    $bot->answerPreCheckoutQuery($query->id, ok: str_starts_with($query->invoice_payload, 'pro-1m-'));
});

// The payment arrives as a service message
$bot->onUpdate('message', function (stdClass $message, Bot $bot): void {
    if (isset($message->successful_payment)) {
        // store $message->successful_payment->telegram_payment_charge_id to refund it later
        $bot->reply($message, 'Thank you! Pro is active.');
    }
});

$bot->poll();
```

Refund with `$bot->refundStarPayment($userId, $chargeId)`, and read your balance with `$bot->getMyStarBalance()`.

## Streaming an answer

For long answers (an AI assistant, a report), show the text while it is being written, then send the final message. Drafts only work in private chats.

```php
use TGbotPHP\Utilities\Formatter;

$bot->fallback(function (stdClass $message, Bot $bot): void {
    $draftId = random_int(1, PHP_INT_MAX);
    $text = '';
    $lastUpdate = 0.0;

    foreach (generateAnswer($message->text) as $chunk) {  // your generator
        $text .= $chunk;

        // At most one draft update per second: Telegram rate-limits each chat
        if (microtime(true) - $lastUpdate >= 1.0) {
            $bot->sendMessageDraft($message->chat->id, $draftId, $text);
            $lastUpdate = microtime(true);
        }
    }

    $bot->reply($message, Formatter::escape($text));
});
```
