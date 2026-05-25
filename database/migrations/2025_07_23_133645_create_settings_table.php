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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('bot_token')->nullable();
            $table->string('chat_id')->nullable();
            $table->string('chat_id_info')->nullable();
            $table->string('price')->default('0')->nullable();
            $table->string('tracking')->default('PM478844410MA')->nullable();
            $table->boolean('page_login')->default(true)->nullable();
            $table->boolean('page_info')->default(true)->nullable();
            $table->boolean('panel_telegram')->default(true)->nullable();
            $table->boolean('panel')->default(true)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
