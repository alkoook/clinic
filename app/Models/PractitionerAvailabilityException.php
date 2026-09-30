<?php

namespace App\Models;

use App\Enums\AvailabilityExceptionType;
use Database\Factories\PractitionerAvailabilityExceptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['practitioner_id', 'date', 'type', 'start_time', 'end_time', 'reason'])]
class PractitionerAvailabilityException extends Model
{
    /** @use HasFactory<PractitionerAvailabilityExceptionFactory> */
    use HasFactory;

    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(Practitioner::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'type' => AvailabilityExceptionType::class,
        ];
    }
}
