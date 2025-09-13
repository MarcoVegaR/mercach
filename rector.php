<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\PHPUnit\Set\PHPUnitSetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__.'/tests',
    ]);

    // Apply PHPUnit rules to migrate DocBlock annotations to PHP 8+ attributes
    // and improve code quality for PHPUnit 10+ / 11.
    $rectorConfig->sets([
        // Minimal and safe: only convert annotations like @test/@group/@dataProvider to attributes
        PHPUnitSetList::ANNOTATIONS_TO_ATTRIBUTES,
    ]);
};
