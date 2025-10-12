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
        Schema::table('copy_generations', function (Blueprint $table) {
            $table->unsignedBigInteger('buyer_persona_id')->nullable()->change();
            $table->string('buyer_persona_type')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('copy_generations', function (Blueprint $table) {
            $table->unsignedBigInteger('buyer_persona_id')->nullable(false)->change();
            $table->string('buyer_persona_type')->nullable(false)->change();
        });
    }
};
