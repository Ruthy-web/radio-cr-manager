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
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();

            // bulletin | audio | document — le fichier audio n'est jamais conservé
            // après transcription (F4) : seule la métadonnée peut subsister.
            $table->string('type');
            $table->string('path')->nullable();
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
