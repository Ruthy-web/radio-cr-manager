<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Hospital extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'colors',
        'header_docx_path',
        'radiologist_name',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'colors' => 'array',
            'active' => 'boolean',
        ];
    }

    public function examTemplates(): HasMany
    {
        return $this->hasMany(ExamTemplate::class);
    }

    /**
     * Couleur principale utilisée pour les titres de section du compte rendu (F3).
     */
    public function primaryColor(): string
    {
        return $this->colors['primary'] ?? '#1F3864';
    }
}
