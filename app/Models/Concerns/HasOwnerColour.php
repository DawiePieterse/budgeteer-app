<?php

namespace App\Models\Concerns;

use App\Support\OwnerColours;

/** A person or project gets its own colour when added; it can be changed on its page. */
trait HasOwnerColour
{
    public static function bootHasOwnerColour(): void
    {
        static::creating(function (self $model): void {
            if ($model->colour === null && $model->household_id !== null) {
                $model->colour = OwnerColours::next($model->household_id);
            }
        });
    }

    public function ownerColour(): string
    {
        return isset(OwnerColours::CHOICES[$this->colour]) ? $this->colour : array_key_first(OwnerColours::CHOICES);
    }
}
