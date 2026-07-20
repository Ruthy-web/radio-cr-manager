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
        // Journalisation d'usage IA (F4) : uniquement des métadonnées
        // techniques (durée, succès, code HTTP) — jamais le contenu dicté ni
        // le texte généré, qui peuvent porter des données patient (R3).
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // stt | refine | draft
            $table->string('endpoint');
            // groq | anthropic
            $table->string('provider');
            $table->string('model')->nullable();
            $table->boolean('success');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('duration_ms');
            $table->string('error_message')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['endpoint', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
