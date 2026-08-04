<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('whatsapp_phone', 50)->nullable()->after('contact_phone');
            $table->boolean('notify_whatsapp')->default(false)->after('whatsapp_phone');
        });

        Schema::create('whatsapp_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('tenant_id');
            $table->string('phone');
            $table->string('type', 50)->index();
            $table->text('body');
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('status', 30)->default('pending');
            $table->text('response')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_notifications');

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_phone', 'notify_whatsapp']);
        });
    }
};
