# Shipping ERP Architecture

## Stack

- Laravel 12
- Inertia.js + Vue 3 (Composition API)
- Pinia
- Bootstrap 5
- Vite
- Queue (database driver by default)
- Soft deletes preferred for business entities

## Layering

```
HTTP Request
  -> Form Request (validation)
  -> Policy (authorization)
  -> Controller (thin orchestration)
  -> Service (business logic + DB transactions)
  -> Eloquent Model / Relationships
  -> API Resource or Inertia props (response shaping)
```

Controllers must stay thin. Business logic belongs in Services.
Heavy work (Excel import, reports, notifications) must use Jobs/Queues.

Repository pattern is optional and only when query complexity justifies it.

## Backend folder map

| Path | Purpose |
|------|---------|
| `app/Http/Controllers` | Thin controllers |
| `app/Http/Requests` | Form Request validation |
| `app/Http/Resources` | API / payload transformers when needed |
| `app/Policies` | Authorization policies |
| `app/Services` | Business services |
| `app/Jobs` | Queued workloads |
| `app/Models` | Eloquent models |
| `app/Enums` | Typed enums |
| `app/Domain` | Domain helpers shared across modules |

## Frontend folder map

| Path | Purpose |
|------|---------|
| `resources/js/Pages` | Inertia pages |
| `resources/js/Layouts` | Layout shells (`AppLayout` = Bootstrap ERP shell) |
| `resources/js/Components` | Reusable Vue components |
| `resources/js/composables` | Composition API composables |
| `resources/js/stores` | Pinia stores |

## Authorization

- Package: `spatie/laravel-permission`
- Roles: `admin`, `accountant`, `operator`, `viewer`
- Permissions use `{module}.{action}` naming (example: `voyages.manage`)
- Business authorization is enforced with Policies + Form Requests
- Frontend checks use `usePermissions()` composable for UI visibility only
- Never trust frontend checks alone

Default admin account (local development):
- Email: `admin@shipping.local`
- Password: `password`

## Localization

- Frontend i18n via `vue-i18n`
- Locales: `ar` (Arabic, default), `en` (English), `ckb` (Kurdish Sorani)
- Arabic and Kurdish Sorani are RTL
- Locale is stored in settings (`app.locale`) and shared as `appSettings.locale`
- Translation files: `resources/js/lang/{ar,en,ckb}.json`
- Kurdish Sorani falls back to Arabic for any missing keys

## Settings

- Key/value settings table with typed keys in `App\Enums\SettingKey`
- Reads/writes go through `SettingService` with cache invalidation
- Shared to Inertia as `appSettings` for branding and defaults
- Groups in Phase 1: company profile + localization

## Accounting rules

- Double-entry only
- Never update balances directly
- Every financial operation creates journal entries
- Balances are derived from journal entries
- Account and voucher currencies: `USD`, `AED`, `IQD`, `EUR`
- System defaults: Dubai Account (`1300`) and Ship Clearing (`1500`) use **AED**
- Ship Expenses (`5200`) also seeded as **AED**
- Each journal voucher has one currency; all lines must use matching accounts
- FX conversion between currencies can be added later as dedicated transfer vouchers


## Module roadmap

1. Auth / Permissions / Settings
2. Accounting core
3. Ships / Voyages / Cars
4. Excel Import
5. Reports / Dashboard
6. Notifications

## Source reuse policy

Reusable business rules and Excel parsing logic may be adapted from the legacy `C:\xampp\htdocs\shipping` project.
Do not copy fat controllers or direct-balance updates.
Extract duplicated logic into Services before reuse.
