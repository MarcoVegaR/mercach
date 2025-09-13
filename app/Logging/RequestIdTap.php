<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Logger as MonologLogger;
use Monolog\LogRecord;

class RequestIdTap
{
    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();
        if ($monolog instanceof MonologLogger) {
            $monolog->pushProcessor(function (LogRecord $record): LogRecord {
                try {
                    /** @var \Illuminate\Http\Request $req */
                    $req = request();
                    $rid = $req->attributes->get('request_id');
                    if ($rid === null) {
                        $rid = $req->headers->get('X-Request-Id');
                    }
                } catch (\Throwable $e) {
                    $rid = null;
                }

                return $record->with(
                    extra: $record->extra + ['request_id' => $rid]
                );
            });
        }
    }
}
