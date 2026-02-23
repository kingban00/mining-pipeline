<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('executives', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('fk_company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->jsonb('expertise');
            $table->jsonb('technical_summary');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('executives');
    }
};
