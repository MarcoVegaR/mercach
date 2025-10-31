<?php

namespace App\Logging;

use DateTimeZone;
use Illuminate\Log\Logger;
use Monolog\Logger as MonologLogger;
use Monolog\LogRecord;

class RequestIdTap
{
    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();
        if ($monolog instanceof MonologLogger) {
            // Force UTC timestamps in logs
            $monolog->setTimezone(new DateTimeZone('UTC'));

            $monolog->pushProcessor(function (LogRecord $record): LogRecord {
                try {
                    /** @var \Illuminate\Http\Request $req */
                    $req = request();
                    $rid = $req->attributes->get('request_id');
                    if ($rid === null) {
                        $rid = $req->headers->get('X-Request-Id');
                    }
                    $trace = $req->attributes->get('trace_id') ?? $req->headers->get('X-Trace-Id');
                    $span = $req->attributes->get('span_id') ?? $req->headers->get('X-Span-Id');
                } catch (\Throwable $e) {
                    $rid = null;
                    $trace = null;
                    $span = null;
                }

                return $record->with(
                    extra: $record->extra + [
                        'request_id' => $rid,
                        'trace_id' => $trace,
                        'span_id' => $span,
                    ]
                );
            });
        }
    }
}
