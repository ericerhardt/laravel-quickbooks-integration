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
        Schema::create('quickbooks_oauth_states', function (Blueprint $table) {
            $table->id();
            $table->string('state_token', 64)->unique();
            $table->unsignedBigInteger('user_id');
            $table->timestamp('expires_at');
            $table->timestamps();
            
            $table->index(['state_token', 'expires_at']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quickbooks_oauth_states');
    }
};

