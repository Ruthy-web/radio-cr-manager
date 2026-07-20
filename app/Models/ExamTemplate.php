<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExamTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'hospital_id',
        'title',
        'heading',
        'modality',
        'requires_side',
        'indication',
        'technique',
        'results',
        'conclusion',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'requires_side' => 'boolean',
            'results' => 'array',
            'active' => 'boolean',
        ];
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }
}
