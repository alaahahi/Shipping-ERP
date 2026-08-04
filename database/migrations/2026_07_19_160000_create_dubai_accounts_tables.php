<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dubai_partners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });

        Schema::create('dubai_account_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dubai_partner_id')->constrained('dubai_partners')->cascadeOnDelete();
            $table->date('entry_date');
            $table->string('doc_no')->nullable();
            $table->string('entry_kind', 20)->default('misc');
            $table->string('currency', 3)->default('AED');

            $table->decimal('transport_qty', 12, 2)->nullable();
            $table->decimal('transport_rate', 14, 4)->nullable();
            $table->decimal('transport_total', 14, 2)->nullable();

            $table->decimal('forklift_qty', 12, 2)->nullable();
            $table->decimal('forklift_rate', 14, 4)->nullable();
            $table->decimal('forklift_total', 14, 2)->nullable();

            $table->decimal('total_debit', 14, 2)->nullable();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->decimal('usd_amount', 14, 2)->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('ship_id')->nullable()->constrained('ships')->nullOnDelete();
            $table->foreignId('voyage_id')->nullable()->constrained('voyages')->nullOnDelete();

            $table->string('excel_file_path')->nullable();
            $table->string('excel_original_name')->nullable();
            $table->timestamp('excel_uploaded_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['dubai_partner_id', 'entry_date']);
            $table->index('voyage_id');
            $table->index('entry_kind');
        });

        Schema::create('dubai_cars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dubai_account_entry_id')->constrained('dubai_account_entries')->cascadeOnDelete();
            $table->unsignedInteger('row_number')->nullable();
            $table->string('chassis_no')->nullable();
            $table->string('consignee_name')->nullable();
            $table->string('shipper_name')->nullable();
            $table->string('description')->nullable();
            $table->decimal('weight', 12, 3)->nullable();
            $table->string('code')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['dubai_account_entry_id', 'chassis_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dubai_cars');
        Schema::dropIfExists('dubai_account_entries');
        Schema::dropIfExists('dubai_partners');
    }
};
