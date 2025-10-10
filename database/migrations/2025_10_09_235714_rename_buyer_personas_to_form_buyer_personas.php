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
        Schema::rename('buyer_personas', 'form_buyer_personas');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('form_buyer_personas', 'buyer_personas');
    }
};
