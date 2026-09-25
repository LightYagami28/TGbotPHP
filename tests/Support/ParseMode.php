<?php

declare(strict_types=1);

namespace TGbotPHP\Tests\Support;

/**
 * A backed enum, as applications use for parse modes or chat actions
 */
enum ParseMode: string
{
    case Html = 'HTML';
}
