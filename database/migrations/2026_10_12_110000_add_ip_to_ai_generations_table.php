<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Guests share a daily quota per IP; only guest rows store it. */
    public function up(): void
    {
        Schema::table('ai_generations', function (Blueprint $table) {
            $table->string('ip', 45)->nullable()->after('kind');
            $table->index(['ip', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('ai_generations', function (Blueprint $table) {
            $table->dropIndex(['ip', 'created_at']);
            $table->dropColumn('ip');
        });
    }
};
