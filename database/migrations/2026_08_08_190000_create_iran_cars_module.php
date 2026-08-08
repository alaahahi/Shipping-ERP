<?php

use App\Services\AccountService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->foreignId('iran_ar_account_id')
                ->nullable()
                ->after('ar_account_id')
                ->constrained('accounts')
                ->nullOnDelete();
        });

        Schema::create('iran_cars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('border', 32);
            $table->string('vin', 64);
            $table->string('model_name');
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('color', 80)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('open');
            $table->foreignId('invoice_journal_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('vin');
            $table->index(['company_id', 'border']);
            $table->index('status');
        });

        Schema::create('iran_car_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('iran_car_id')->constrained('iran_cars')->restrictOnDelete();
            $table->string('voucher_number', 40)->unique();
            $table->date('payment_date');
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('USD');
            $table->foreignId('debit_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->unique()->constrained('journal_entries')->nullOnDelete();
            $table->string('reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['iran_car_id', 'payment_date']);
        });

        if (Schema::hasTable('accounts')) {
            app(AccountService::class)->seedChartOfAccounts();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('iran_car_payments');
        Schema::dropIfExists('iran_cars');

        Schema::table('companies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('iran_ar_account_id');
        });
    }
};
