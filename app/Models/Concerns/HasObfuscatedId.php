<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\Sqid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

trait HasObfuscatedId
{
    /**
     * Get the value of the model's route key (encode for URLs).
     */
    public function getRouteKey(): string
    {
        return Sqid::encode($this->id);
    }

    /**
     * Decode the sqid before querying. Used by both Laravel route binding
     * and Filament record resolution.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        if ($field !== null && $field !== 'id') {
            return $query->where($field, $value);
        }

        $id = Sqid::decode((string) $value);

        if ($id === null) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where('id', $id);
    }

    /**
     * Resolve the model from an obfuscated route binding value.
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        /** @var Builder<static> $query */
        $query = $this->newQuery();

        return $this->resolveRouteBindingQuery($query, $value, $field)->first();
    }

    protected function sqid(): Attribute
    {
        return Attribute::get(fn (): string => Sqid::encode($this->id))->shouldCache();
    }
}
