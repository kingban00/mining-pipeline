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
        Schema::table('companies', function (Blueprint $table) {
            $table->index('name');
            $table->index('status');
        });

        Schema::table('executives', function (Blueprint $table) {
            $table->index('fk_company_id');
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->index('fk_company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex(['name']);
            $table->dropIndex(['status']);
        });

        Schema::table('executives', function (Blueprint $table) {
            $table->dropIndex(['fk_company_id']);
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->dropIndex(['fk_company_id']);
        });
    }
};
