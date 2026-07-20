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
        Schema::create('exam_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();

            $table->string('title');
            // Intitulé exact tel qu'imprimé en tête du compte rendu (peut différer du titre catalogue).
            $table->string('heading');

            // Modalité (radiographie, échographie, etc.) utile pour le regroupement/filtrage.
            $table->string('modality')->nullable();

            $table->boolean('requires_side')->default(false);

            $table->text('indication')->nullable();
            $table->text('technique')->nullable();

            // Lignes de résultats normaux : [{"text": "...", "abnormal": false}, ...] (F5 s'appuie dessus).
            $table->json('results');

            $table->text('conclusion')->nullable();

            $table->boolean('active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['hospital_id', 'title']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_templates');
    }
};
