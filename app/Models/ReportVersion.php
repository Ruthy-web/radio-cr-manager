<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportVersion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'report_id',
        'content',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ReportVersion $version) {
            $version->created_at ??= now();
        });
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
