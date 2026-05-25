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
        Schema::create('antibots', function (Blueprint $table) {
            $table->id();
            $table->json('allowed_countries')->nullable();
            $table->json('allowed_operators')->nullable();
            $table->json('blocker_operators')->nullable();
            $table->boolean('proxy_protection')->default(true)->nullable();
            $table->boolean('antibots_protection')->default(true)->nullable();
            $table->boolean('captcha_protection')->default(true)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('antibots');
    }
};
