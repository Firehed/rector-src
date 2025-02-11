<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Renaming\Rector\String_\RenameStringRector;

return static function (RectorConfig $rectorConfig): void {
    $services = $rectorConfig->services();
    $services->set(RenameStringRector::class)
        ->configure([
            'ROLE_PREVIOUS_ADMIN' => 'IS_IMPERSONATOR',
        ]);
};
