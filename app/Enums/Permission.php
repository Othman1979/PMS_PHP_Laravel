<?php

namespace App\Enums;

/** Fine-grained abilities granted to user groups; Admin accounts bypass all checks. */
enum Permission: string
{
    use HasLabel;

    private const LABEL_PREFIX = 'Permission_';

    case RequestsViewAll = 'requests.view_all';
    case RequestsCreate = 'requests.create';
    case RequestsReview = 'requests.review';
    case RequestsAssign = 'requests.assign';
    case RequestsClose = 'requests.close';
    case RequestsCancel = 'requests.cancel';
    case RequestsCancelOwn = 'requests.cancel_own';
    case RequestsReopen = 'requests.reopen';
    case RequestsRelease = 'requests.release';
    case RequestsFoodSafety = 'requests.food_safety';
    case RequestsArchiveView = 'requests.archive_view';
    case DashboardStats = 'dashboard.stats';
    case EquipmentView = 'equipment.view';
    case EquipmentManage = 'equipment.manage';
    case EquipmentDelete = 'equipment.delete';
    case EquipmentCalibrate = 'equipment.calibrate';
    case EquipmentCommission = 'equipment.commission';
    case PartsView = 'parts.view';
    case PartsManage = 'parts.manage';
    case PartsDelete = 'parts.delete';
    case PmManage = 'pm.manage';
    case PurchasesManage = 'purchases.manage';
    case PurchasesApprove = 'purchases.approve';
    case ReportsView = 'reports.view';
    case UsersManage = 'users.manage';
    case SettingsManage = 'settings.manage';

    /** Module key used to group the permission matrix on the groups screen. */
    public function module(): string
    {
        return explode('.', $this->value, 2)[0];
    }

    /** @return array<string, list<self>> */
    public static function byModule(): array
    {
        $grouped = [];
        foreach (self::cases() as $permission) {
            $grouped[$permission->module()][] = $permission;
        }

        return $grouped;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $p) => $p->value, self::cases());
    }

    /** Abilities each built-in role starts with; mirrors the access rules that existed before groups. */
    public static function defaultsFor(Role $role): array
    {
        return match ($role) {
            Role::Admin => self::cases(),
            Role::Coordinator => array_values(array_filter(self::cases(), fn (self $p) => ! in_array($p, [
                self::UsersManage, self::SettingsManage, self::EquipmentDelete, self::PartsDelete,
                self::PurchasesApprove, self::EquipmentCommission, self::RequestsFoodSafety, self::RequestsCancelOwn,
            ], true))),
            Role::Technician => [],
            Role::DepartmentManager => [self::RequestsCreate, self::RequestsCancelOwn, self::RequestsRelease, self::DashboardStats, self::EquipmentView],
            Role::Employee => [self::RequestsCreate, self::RequestsCancelOwn],
            Role::FoodSafety => [self::RequestsViewAll, self::RequestsCreate, self::RequestsRelease, self::RequestsFoodSafety, self::DashboardStats,
                self::EquipmentView, self::EquipmentCalibrate, self::EquipmentCommission, self::PartsView, self::ReportsView],
        };
    }
}
