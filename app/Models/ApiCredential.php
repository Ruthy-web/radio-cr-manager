<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiCredential extends Model
{
    protected $fillable = [
        'provider',
        'api_key',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            // Clé API chiffrée au repos (R4 : jamais en clair en base).
            'api_key' => 'encrypted',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
