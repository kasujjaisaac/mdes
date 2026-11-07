<?php

namespace App\Support;

class Navigation
{
    public static function submissionLabel(): string
    {
        $user = auth()->user();

        return $user && $user->isSchoolRepresentative() && ! $user->hasPermission('returns.manage.all')
            ? 'Submissions' : 'School Submissions';
    }

    /**
     * @return array<int, array{label: string, items: array<int, array{label: string, key: string, icon: string, url: string, permission: string|null}>}>
     */
    public static function groups(): array
    {
        return [
            [
                'label' => 'Overview',
                'items' => [
                    self::item('Dashboard', 'dashboard', 'dashboard', auth()->user()?->isSchoolRepresentative() ? route('representative.home') : route('dashboard')),
                ],
            ],
            [
                'label' => 'School Directory',
                'items' => [
                    self::item('Parishes', 'parishes', 'hierarchy', route('parishes.index'), 'diocesan-structure.view'),
                    self::item(auth()->user()?->isSchoolRepresentative() ? 'School Profile' : 'Registered Schools', 'schools', 'school', auth()->user()?->isSchoolRepresentative() ? route('representative.profile') : route('schools.index'), 'schools.view'),
                ],
            ],
            [
                'label' => 'School Statistics',
                'items' => [
                    self::item(self::submissionLabel(), 'school-returns', 'clipboard', route('returns.index'), 'returns.view'),
                    self::item('Announcements', 'announcements', 'bell', route('announcements.index')),
                    self::item('Reports', 'reports', 'document', route('reports.index'), 'reports.view'),
                ],
            ],
            [
                'label' => 'Settings',
                'items' => [
                    self::item('Users', 'users', 'users', route('administration.users.index'), 'users.view'),
                    self::item('Change password', 'change-password', 'shield', route('profile.password.edit')),
                    ...(! auth()->user()?->isSchoolRepresentative() ? [self::item('Window settings', 'reporting-periods', 'calendar', route('reporting-periods.index'), 'reporting-periods.view')] : []),
                ],
            ],
        ];
    }

    /**
     * @return array{label: string, key: string, group: string, icon: string, url: string, permission: string|null}|null
     */
    public static function find(string $key): ?array
    {
        foreach (self::groups() as $group) {
            foreach ($group['items'] as $item) {
                if ($item['key'] === $key) {
                    return [...$item, 'group' => $group['label']];
                }
            }
        }

        return null;
    }

    /**
     * @return array{label: string, key: string, icon: string, url: string, permission: string|null}
     */
    private static function item(string $label, string $key, string $icon, string $url, ?string $permission = null): array
    {
        return [
            'label' => $label,
            'key' => $key,
            'icon' => $icon,
            'url' => $url,
            'permission' => $permission,
        ];
    }
}
