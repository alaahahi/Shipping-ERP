<?php

use App\Models\Company;
use App\Services\AccountService;
use App\Services\CompanyReceivableAccountService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->foreignId('ar_account_id')
                ->nullable()
                ->after('is_active')
                ->constrained('accounts')
                ->nullOnDelete();
        });

        Schema::create('company_direct_charges', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_number', 40)->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->date('charge_date');
            $table->string('currency', 3)->default('USD');
            $table->decimal('amount', 14, 2);
            $table->foreignId('credit_account_id')->constrained('accounts')->restrictOnDelete();
            $table->string('reference', 120)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('posted');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('journal_entry_id');
            $table->index(['company_id', 'charge_date']);
            $table->index('status');
        });

        if (! Schema::hasTable('accounts')) {
            return;
        }

        $hasControl = \App\Models\Account::query()->where('code', '1600')->exists();
        if (! $hasControl) {
            app(AccountService::class)->seedChartOfAccounts();
        }

        $service = app(CompanyReceivableAccountService::class);

        Company::query()
            ->withTrashed()
            ->orderBy('id')
            ->each(function (Company $company) use ($service): void {
                $service->ensureFor($company);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_direct_charges');

        Schema::table('companies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ar_account_id');
        });
    }
};
