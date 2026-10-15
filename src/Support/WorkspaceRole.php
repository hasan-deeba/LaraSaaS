<?php

namespace LaraSaaS\Support;

/**
 * Workspace-level roles: Owner, Admin, Member.
 *
 * Each role maps to a granular permission list (override per role via
 * `config('larasaas.workspaces.role_permissions')`). Role names themselves are
 * fixed — ownership transfer, not assignment, grants "owner".
 */
enum WorkspaceRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';

    /** The full, stable permission catalog. */
    public const PERMISSIONS = [
        'workspace.update',
        'workspace.delete',
        'workspace.billing',
        'members.view',
        'members.invite',
        'members.update',
        'members.remove',
        'invitations.view',
        'invitations.manage',
        'ownership.transfer',
    ];

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Admin => 'Admin',
            self::Member => 'Member',
        };
    }

    /** Higher wins; used for "cannot manage someone above you" checks. */
    public function rank(): int
    {
        return match ($this) {
            self::Owner => 3,
            self::Admin => 2,
            self::Member => 1,
        };
    }

    public function isAtLeast(self $other): bool
    {
        return $this->rank() >= $other->rank();
    }

    /** @return array<int, string> */
    public function permissions(): array
    {
        $defaults = match ($this) {
            self::Owner => self::PERMISSIONS,
            self::Admin => [
                'workspace.update',
                'members.view',
                'members.invite',
                'members.update',
                'members.remove',
                'invitations.view',
                'invitations.manage',
            ],
            self::Member => [
                'members.view',
            ],
        };

        $overrides = config('larasaas.workspaces.role_permissions', []);

        return array_values(array_unique($overrides[$this->value] ?? $defaults));
    }

    public function allows(string $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    /** Roles that may be granted to members (ownership is transferred separately). */
    public static function assignable(): array
    {
        $roles = [];

        foreach ((array) config('larasaas.workspaces.assignable_roles', ['admin', 'member']) as $value) {
            if ($role = self::tryFrom((string) $value)) {
                if ($role !== self::Owner) {
                    $roles[] = $role;
                }
            }
        }

        return $roles;
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $role) => $role->value, self::cases());
    }

    public static function tryFromLoose(?string $value): ?self
    {
        return is_string($value) ? self::tryFrom(strtolower(trim($value))) : null;
    }
}
