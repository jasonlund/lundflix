<?php

namespace App\Actions\Request;

use App\Enums\MediaType;
use App\Models\Request;
use App\Models\RequestItem;

class CreateRequestItems
{
    /**
     * @param  array<int, array{type: MediaType, id: int}>  $items
     */
    public function create(Request $request, array $items): bool
    {
        $data = array_map(fn ($item) => [
            'request_id' => $request->id,
            'requestable_type' => $item['type']->value,
            'requestable_id' => $item['id'],
            'created_at' => now(),
            'updated_at' => now(),
        ], $items);

        return RequestItem::insert($data);
    }
}
