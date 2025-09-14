<?php

namespace Webgefaehrten\Locking\Traits;

use Exception;

trait OptimisticLockingTrait
{
    protected static function bootOptimisticLockingTrait()
    {
        static::updating(function ($model) {
            $originalUpdatedAt = $model->getOriginal('updated_at');

            if ($originalUpdatedAt && $model->updated_at->ne($originalUpdatedAt)) {
                if (method_exists($model, 'handleConflictMessage')) {
                    $model->handleConflictMessage(
                        "Dieser Datensatz wurde bereits von jemand anderem geändert. Bitte Seite neu laden."
                    );
                }

                throw new Exception(
                    "Dieser Datensatz wurde bereits von jemand anderem geändert. Bitte Seite neu laden."
                );
            }
        });
    }
}
