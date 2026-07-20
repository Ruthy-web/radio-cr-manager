<?php

namespace App\Models;

use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Report extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_uuid',
        'user_id',
        'hospital_id',
        'exam_template_id',
        'patient_name',
        'patient_age',
        'patient_sex',
        'file_number',
        'prescriber',
        'exam_date',
        'content',
        'status',
        'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'exam_date' => 'date',
            'finalized_at' => 'datetime',
            'status' => ReportStatus::class,
            // Champs directement nominatifs chiffrés au repos (R3).
            'patient_name' => 'encrypted',
            'file_number' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Report $report) {
            $report->client_uuid ??= (string) Str::uuid();
            $report->status ??= ReportStatus::Brouillon;
        });

        // Chaque sauvegarde du contenu médical crée une version consultable
        // et restaurable (F3) — jamais de perte silencieuse d'une rédaction.
        static::saved(function (Report $report) {
            if ($report->wasRecentlyCreated || $report->wasChanged('content')) {
                $report->versions()->create([
                    'content' => $report->content,
                    'created_by' => auth()->id(),
                ]);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function examTemplate(): BelongsTo
    {
        return $this->belongsTo(ExamTemplate::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ReportVersion::class)->latest('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function finalize(): void
    {
        $this->update(['status' => ReportStatus::Finalise, 'finalized_at' => now()]);
    }

    public function sign(): void
    {
        $this->update(['status' => ReportStatus::Signe, 'finalized_at' => $this->finalized_at ?? now()]);
    }

    /**
     * Restaure le contenu d'une version antérieure. L'ancien contenu courant
     * n'est pas perdu : la sauvegarde déclenche elle-même une nouvelle
     * version (F3).
     */
    public function restoreVersion(ReportVersion $version): void
    {
        $this->update(['content' => $version->content]);
    }
}
