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
        Schema::create('quickbooks_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('access_token');
            $table->text('refresh_token');
            $table->string('realm_id');
            $table->timestamp('access_token_expires_at');
            $table->timestamp('refresh_token_expires_at');
            $table->string('company_name')->nullable();
            $table->string('company_email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes for performance
            $table->index(['user_id', 'is_active']);
            $table->index(['user_id', 'realm_id']);
            $table->index('access_token_expires_at');
            $table->index('refresh_token_expires_at');
            
            // Ensure one active token per user per realm
            $table->unique(['user_id', 'realm_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quickbooks_tokens');
    }
};

