<?php

use App\Models\Company;
use App\Models\VoyageCompany;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });

        Schema::table('voyage_companies', function (Blueprint $table) {
            $table->foreignId('company_id')
                ->nullable()
                ->after('voyage_id')
                ->constrained('companies')
                ->restrictOnDelete();

            $table->index(['voyage_id', 'company_id']);
        });

        // Link existing voyage companies to a reusable master record.
        VoyageCompany::query()
            ->orderBy('id')
            ->each(function (VoyageCompany $voyageCompany): void {
                $name = trim((string) $voyageCompany->company_name);
                if ($name === '') {
                    return;
                }

                $company = Company::query()->firstOrCreate(
                    ['name' => $name],
                    [
                        'contact_name' => $voyageCompany->contact_name,
                        'contact_phone' => $voyageCompany->contact_phone,
                        'is_active' => true,
                    ]
                );

                $voyageCompany->forceFill(['company_id' => $company->id])->saveQuietly();
            });
    }

    public function down(): void
    {
        Schema::table('voyage_companies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });

        Schema::dropIfExists('companies');
    }
};
