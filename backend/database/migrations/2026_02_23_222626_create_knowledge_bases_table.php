<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('knowledge_bases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('fk_company_id')->constrained('companies')->cascadeOnDelete();
            $table->text('raw_content');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE knowledge_bases ADD COLUMN embedding vector(768)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('knowledge_bases');
    }
};
