<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'user_id', 'request_hash', 'response_status', 'response_body'])]
class IdempotencyKey extends Model
{
    protected function casts(): array
    {
        return [
            'response_body' => 'array',
            'response_status' => 'integer',
        ];
    }
}
