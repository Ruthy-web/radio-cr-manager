<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();

            // Identifiant généré côté PWA, utilisé pour la synchronisation idempotente (F6).
            $table->uuid('client_uuid')->unique();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_template_id')->nullable()->constrained()->nullOnDelete();

            // Données patient : chiffrées au repos pour les champs directement nominatifs (R3).
            $table->text('patient_name')->nullable();
            $table->string('patient_age')->nullable();
            $table->string('patient_sex', 20)->nullable();
            $table->text('file_number')->nullable();
            $table->string('prescriber')->nullable();
            $table->date('exam_date')->nullable();

            // {heading, identity, indication, technique, results[], conclusion}
            $table->json('content');

            $table->string('status')->default('brouillon');
            $table->timestamp('finalized_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['hospital_id', 'exam_date']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
