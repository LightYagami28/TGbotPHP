<?php

declare(strict_types=1);

namespace TGbotPHP\Utilities;

class Keyboard
{
    public static function inline(array $buttons): array
    {
        $inline = array_map(
            fn($text, $data) => ['text' => $text, 'callback_data' => $data],
            array_keys($buttons),
            array_values($buttons)
        );

        return ['inline_keyboard' => [$inline]];
    }

    public static function links(array $links): array
    {
        $urlButtons = array_map(
            fn($text, $url) => ['text' => $text, 'url' => $url],
            array_keys($links),
            array_values($links)
        );

        return ['inline_keyboard' => [$urlButtons]];
    }

    public static function row(array $buttons): array
    {
        return ['inline_keyboard' => [
            array_map(
                fn($text, $data) => ['text' => $text, 'callback_data' => $data],
                array_keys($buttons),
                array_values($buttons)
            ),
        ]];
    }

    public static function grid(array $buttons, int $cols = 2): array
    {
        $grid = [];
        $row = [];

        foreach ($buttons as $text => $data) {
            $row[] = ['text' => $text, 'callback_data' => $data];

            if (count($row) === $cols) {
                $grid[] = $row;
                $row = [];
            }
        }

        if (!empty($row)) {
            $grid[] = $row;
        }

        return ['inline_keyboard' => $grid];
    }

    public static function menu(array $items, int $itemsPerRow = 1): array
    {
        $keyboard = [];

        foreach (array_chunk($items, $itemsPerRow, true) as $chunk) {
            $keyboard[] = array_map(
                fn($text, $data) => ['text' => $text, 'callback_data' => $data],
                array_keys($chunk),
                array_values($chunk)
            );
        }

        return ['inline_keyboard' => $keyboard];
    }
}
