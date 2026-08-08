<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\SettingKey;
use App\Jobs\SendWhatsappNotification;
use App\Models\WhatsappNotification;
use App\Services\SettingService;
use App\Services\WhatsappNotificationService;
use App\Support\ApplicationTimezone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WhatsappNotificationController extends Controller
{
    public function __construct(
        private readonly WhatsappNotificationService $service,
        private readonly SettingService $settings
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize(Permission::SettingsView->value);

        $query = WhatsappNotification::query()
            ->with('company:id,name')
            ->latest('id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->integer('company_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->toString());
        }

        return Inertia::render('WhatsappNotifications/Index', [
            'notifications' => $query->paginate(25)->withQueryString()->through(fn (WhatsappNotification $row) => [
                'id' => $row->id,
                'company' => $row->company,
                'phone' => $row->phone,
                'type' => $row->type,
                'status' => $row->status,
                'created_at' => ApplicationTimezone::formatDateTime($row->created_at),
            ]),
            'filters' => [
                'status' => $request->string('status')->toString(),
                'company_id' => $request->integer('company_id'),
                'type' => $request->string('type')->toString(),
            ],
            'settings' => [
                'enabled' => $this->service->isEnabled(),
                'tenant_id' => $this->service->tenantId(),
            ],
            'canManage' => $request->user()?->can(Permission::SettingsManage->value) ?? false,
        ]);
    }

    public function retry(WhatsappNotification $notification): RedirectResponse
    {
        Gate::authorize(Permission::SettingsManage->value);

        if (! in_array($notification->status, ['pending', 'failed'], true)) {
            return back()->with('error', 'Only pending or failed notifications can be retried.');
        }

        $notification->status = 'pending';
        $notification->response = null;
        $notification->failed_at = null;
        $notification->save();

        SendWhatsappNotification::dispatch($notification)->onQueue('whatsapp');

        return back()->with('success', 'Notification queued for retry.');
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        Gate::authorize(Permission::SettingsManage->value);

        $validated = $request->validate([
            'whatsapp.tenant_id' => ['required', 'string', 'max:120'],
            'whatsapp.enabled' => ['boolean'],
        ]);

        $this->settings->updateMany([
            SettingKey::WhatsappTenantId->value => $validated['whatsapp.tenant_id'],
            SettingKey::WhatsappEnabled->value => ($validated['whatsapp.enabled'] ?? false) ? '1' : '0',
        ]);

        return back()->with('success', 'WhatsApp settings updated.');
    }
}
