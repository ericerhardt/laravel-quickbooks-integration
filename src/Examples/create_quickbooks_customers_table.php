<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Example migration for QuickBooks Customer model
 * 
 * This is an example showing the proper database structure for a QuickBooks-synced entity.
 * Use this as a reference when creating your own QuickBooks entity migrations.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('quickbooks_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // QuickBooks sync fields
            $table->string('quickbooks_id')->nullable();
            $table->string('sync_token')->nullable();
            
            // Customer basic information
            $table->string('name');
            $table->string('company_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            
            // Billing address
            $table->string('billing_address_line1')->nullable();
            $table->string('billing_address_line2')->nullable();
            $table->string('billing_address_city')->nullable();
            $table->string('billing_address_state')->nullable();
            $table->string('billing_address_postal_code')->nullable();
            $table->string('billing_address_country')->nullable();
            
            // Shipping address
            $table->string('shipping_address_line1')->nullable();
            $table->string('shipping_address_line2')->nullable();
            $table->string('shipping_address_city')->nullable();
            $table->string('shipping_address_state')->nullable();
            $table->string('shipping_address_postal_code')->nullable();
            $table->string('shipping_address_country')->nullable();
            
            // Sync tracking
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['user_id', 'quickbooks_id']);
            $table->index(['user_id', 'name']);
            $table->index(['user_id', 'company_name']);
            $table->index(['user_id', 'email']);
            $table->index('last_synced_at');
            
            // Ensure unique QuickBooks ID per user
            $table->unique(['user_id', 'quickbooks_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quickbooks_customers');
    }
};

