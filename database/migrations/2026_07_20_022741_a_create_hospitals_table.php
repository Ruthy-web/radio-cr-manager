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
        Schema::create('hospitals', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();

            // Couleurs extraites du template DOCX d'origine : ['primary' => '#RRGGBB', ...] (R1).
            $table->json('colors')->nullable();

            // Fichier DOCX source (entête, logo, mise en forme) dans storage/app/templates/.
            $table->string('header_docx_path')->nullable();

            $table->string('radiologist_name')->nullable();
            $table->boolean('active')->default(true);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hospitals');
    }
};
