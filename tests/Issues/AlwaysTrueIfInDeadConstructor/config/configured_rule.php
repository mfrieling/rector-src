<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveEmptyClassMethodRector;
use Rector\DeadCode\Rector\If_\RemoveAlwaysTrueIfConditionRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(RemoveAlwaysTrueIfConditionRector::class);
    $rectorConfig->rule(RemoveEmptyClassMethodRector::class);
};
