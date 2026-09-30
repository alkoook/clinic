<?php

namespace App\Models\Concerns;

use App\Exceptions\FinancialException;
use Illuminate\Database\Eloquent\Model;

trait PreventsFinancialRecordMutation
{
    protected static function bootPreventsFinancialRecordMutation(): void
    {
        static::updating(function (Model $model): void {
            $dirtyAttributes = array_values(array_diff(array_keys($model->getDirty()), ['updated_at']));
            $unexpectedAttributes = array_diff($dirtyAttributes, $model->mutableFinancialAttributes());

            if ($unexpectedAttributes !== [] || ! $model->permitsFinancialMutation($dirtyAttributes)) {
                throw FinancialException::immutableLedgerEntry();
            }
        });

        static::deleting(function (): void {
            throw FinancialException::immutableLedgerEntry();
        });
    }

    /** @return list<string> */
    protected function mutableFinancialAttributes(): array
    {
        return [];
    }

    /** @param list<string> $dirtyAttributes */
    protected function permitsFinancialMutation(array $dirtyAttributes): bool
    {
        return true;
    }
}
