<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Visibility\Rector\ClassMethod\ExplicitPublicClassMethodRector;

return static function (RectorConfig $rectorConfig): void {
    $services = $rectorConfig->services();
    $services->set(ExplicitPublicClassMethodRector::class);
};
