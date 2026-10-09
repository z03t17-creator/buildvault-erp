<?php

namespace App\Services;

use App\Support\AuditActions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Contracts\Activity;

class AuditLogger
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function log(
        string $action,
        string $description,
        ?Model $subject = null,
        array $properties = [],
        ?Model $causer = null,
    ): ?Activity {
        $logger = activity(AuditActions::LOG_NAME)
            ->event($action)
            ->withProperties($properties);

        if ($subject) {
            $logger->performedOn($subject);
        }

        if ($causer) {
            $logger->causedBy($causer);
        } elseif (auth()->check()) {
            $logger->causedBy(auth()->user());
        }

        return $logger->log($description);
    }
}
