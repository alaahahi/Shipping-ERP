<?php

namespace App\Jobs;

use App\Models\Voyage;
use App\Models\VoyageCompany;
use App\Notifications\VoyageCarsImportedNotification;
use App\Services\NotificationDispatchService;
use App\Services\VoyageCarExcelImportService;
use App\Enums\Permission;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ImportVoyageCarsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public int $voyageId,
        public int $companyId,
        public ?int $requestedBy = null,
        public ?string $path = null
    ) {
        $this->onQueue('imports');
    }

    public function handle(
        VoyageCarExcelImportService $importService,
        NotificationDispatchService $notificationDispatchService
    ): void {
        $voyage = Voyage::query()->findOrFail($this->voyageId);
        $company = VoyageCompany::query()->findOrFail($this->companyId);

        $result = $importService->importFromStoredPath($voyage, $company, $this->path);

        Log::info('Voyage car import finished', [
            'voyage_id' => $this->voyageId,
            'company_id' => $this->companyId,
            'requested_by' => $this->requestedBy,
            'result' => $result,
        ]);

        $notification = new VoyageCarsImportedNotification(
            $voyage,
            $company->company_name,
            is_array($result) ? $result : []
        );

        $notificationDispatchService->notifyUser($this->requestedBy, $notification);
        $notificationDispatchService->notifyByPermissions(
            [Permission::VoyagesManage->value, Permission::ExcelImport->value],
            $notification,
            $this->requestedBy
        );
    }
}
