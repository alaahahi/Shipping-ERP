<?php

namespace App\Enums;

enum Permission: string
{
    case UsersView = 'users.view';
    case UsersManage = 'users.manage';

    case RolesView = 'roles.view';
    case RolesManage = 'roles.manage';

    case SettingsView = 'settings.view';
    case SettingsManage = 'settings.manage';

    case ShipsView = 'ships.view';
    case ShipsManage = 'ships.manage';

    case VoyagesView = 'voyages.view';
    case VoyagesManage = 'voyages.manage';

    case CarsView = 'cars.view';
    case CarsManage = 'cars.manage';

    case AccountingView = 'accounting.view';
    case AccountingManage = 'accounting.manage';

    case ExcelImport = 'excel.import';

    case ReportsView = 'reports.view';

    case DubaiAccountsView = 'dubai_accounts.view';
    case DubaiAccountsManage = 'dubai_accounts.manage';

    case LandTripsView = 'land_trips.view';
    case LandTripsManage = 'land_trips.manage';

    public function label(): string
    {
        return match ($this) {
            self::UsersView => 'View users',
            self::UsersManage => 'Manage users',
            self::RolesView => 'View roles',
            self::RolesManage => 'Manage roles',
            self::SettingsView => 'View settings',
            self::SettingsManage => 'Manage settings',
            self::ShipsView => 'View ships',
            self::ShipsManage => 'Manage ships',
            self::VoyagesView => 'View voyages',
            self::VoyagesManage => 'Manage voyages',
            self::CarsView => 'View cars',
            self::CarsManage => 'Manage cars',
            self::AccountingView => 'View accounting',
            self::AccountingManage => 'Manage accounting',
            self::ExcelImport => 'Import Excel',
            self::ReportsView => 'View reports',
            self::DubaiAccountsView => 'View Dubai accounts',
            self::DubaiAccountsManage => 'Manage Dubai accounts',
            self::LandTripsView => 'View land trips',
            self::LandTripsManage => 'Manage land trips',
        };
    }

    public function group(): string
    {
        return explode('.', $this->value)[0];
    }

    /**
     * @return list<self>
     */
    public static function values(): array
    {
        return self::cases();
    }
}
