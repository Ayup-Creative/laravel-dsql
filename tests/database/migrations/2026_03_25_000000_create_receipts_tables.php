<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number');
            $table->decimal('amount', 10, 2);
            $table->date('purchased_at');
            $table->unsignedBigInteger('registration_id')->nullable();
            $table->timestamps();
        });

        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('receipt_id')->nullable();
            $table->timestamps();
        });

        Schema::create('appliance_models', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('standard_warranty_years');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('registrations');
        Schema::dropIfExists('appliance_models');
    }
};
