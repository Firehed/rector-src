<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\EarlyReturn\Rector\If_\ChangeOrIfReturnToEarlyReturnRector;

return static function (RectorConfig $rectorConfig): void {
    $services = $rectorConfig->services();
    $services->set(ChangeOrIfReturnToEarlyReturnRector::class);
};
