<?php

declare(strict_types=1);

namespace TGbotPHP\Utilities;

use JsonSerializable;

/**
 * Fluent inline keyboard builder.
 *
 * Can be passed directly as reply markup to any method.
 */
final class InlineKeyboard implements JsonSerializable
{
    /** @var array<int, array<int, array<string, mixed>>> Completed rows */
    private array $rows = [];

    /** @var array<int, array<string, mixed>> Buttons of the row being built */
    private array $current = [];

    public static function make(): self
    {
        return new self();
    }

    /** Add a callback button to the current row. */
    public function button(string $text, string $callbackData): self
    {
        $this->current[] = Keyboard::button($text, $callbackData);

        return $this;
    }

    /** Add a URL button to the current row. */
    public function url(string $text, string $url): self
    {
        $this->current[] = Keyboard::urlButton($text, $url);

        return $this;
    }

    /** Add a Web App button to the current row. */
    public function webApp(string $text, string $url): self
    {
        $this->current[] = Keyboard::webAppButton($text, $url);

        return $this;
    }

    /** Add a switch-to-inline button for the current chat or any chat. */
    public function switchInline(string $text, string $query = '', bool $currentChat = false): self
    {
        $this->current[] = [
            'text' => $text,
            $currentChat ? 'switch_inline_query_current_chat' : 'switch_inline_query' => $query,
        ];

        return $this;
    }

    /** Add a copy-text button to the current row. */
    public function copyText(string $text, string $copy): self
    {
        $this->current[] = ['text' => $text, 'copy_text' => ['text' => $copy]];

        return $this;
    }

    /** Add a payment button to the current row. */
    public function pay(string $text): self
    {
        $this->current[] = ['text' => $text, 'pay' => true];

        return $this;
    }

    /**
     * Add a raw InlineKeyboardButton object.
     *
     * @param array<string, mixed> $button
     */
    public function raw(array $button): self
    {
        $this->current[] = $button;

        return $this;
    }

    /** Start a new row, if the current one has buttons. */
    public function row(): self
    {
        if ($this->current !== []) {
            $this->rows[] = $this->current;
            $this->current = [];
        }

        return $this;
    }

    /**
     * @return array{inline_keyboard: array<int, array<int, array<string, mixed>>>}
     */
    public function toArray(): array
    {
        $rows = $this->rows;

        if ($this->current !== []) {
            $rows[] = $this->current;
        }

        return ['inline_keyboard' => $rows];
    }

    /**
     * @return array{inline_keyboard: array<int, array<int, array<string, mixed>>>}
     */
    #[\Override]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
