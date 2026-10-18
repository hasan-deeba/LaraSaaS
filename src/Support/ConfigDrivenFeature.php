<?php

namespace LaraSaaS\Support;

use Illuminate\Support\Arr;

/**
 * Resolves a Pennant feature definition straight from config/larasaas.php.
 *
 * Supported strategies (evaluated in order, first match wins):
 *
 *   'new-dashboard' => ['enabled' => true]                       // static on/off
 *   'new-dashboard' => ['percentage' => 25]                      // stable user bucketing
 *   'beta-reports'  => ['workspaces' => [1, 5]]                  // workspace rollout
 *   'concierge'     => ['emails' => ['vip@yourapp.test']]        // user targeting
 *   'labs'          => ['resolve' => fn ($scope) => true]        // full control
 *
 * Any definition may carry 'default' as the final fallback.
 */
class ConfigDrivenFeature
{
    public function __construct(
        protected string $name,
        protected array $definition,
    ) {}

    public function resolve(mixed $scope): bool
    {
        $definition = $this->definition;

        if (is_bool($definition['enabled'] ?? null) && count($definition) === 1) {
            return $definition['enabled'];
        }

        if (isset($definition['resolve']) && is_callable($definition['resolve'])) {
            return (bool) call_user_func($definition['resolve'], $scope);
        }

        if (array_key_exists('enabled', $definition) && ! $definition['enabled']) {
            return false;
        }

        if ($this->matchesWorkspaces($scope)) {
            return true;
        }

        if ($this->matchesEmails($scope)) {
            return true;
        }

        if (isset($definition['percentage'])) {
            return $this->withinPercentage((float) $definition['percentage'], $scope);
        }

        return (bool) ($definition['default'] ?? $definition['enabled'] ?? false);
    }

    protected function matchesWorkspaces(mixed $scope): bool
    {
        $workspaces = $this->definition['workspaces'] ?? [];

        if (empty($workspaces)) {
            return false;
        }

        $workspace = $this->workspaceFor($scope);

        return $workspace && in_array($workspace->getKey(), $workspaces);
    }

    protected function matchesEmails(mixed $scope): bool
    {
        $emails = $this->definition['emails'] ?? [];

        return ! empty($emails) && $scope && in_array($scope->email ?? null, $emails, true);
    }

    /**
     * Stable bucketing: the same scope key always lands in the same bucket,
     * so raising the percentage gradually enrols more users/monotone.
     */
    protected function withinPercentage(float $percentage, mixed $scope): bool
    {
        if ($percentage >= 100) {
            return true;
        }

        if ($percentage <= 0) {
            return false;
        }

        $key = $this->scopeKey($scope);

        return ((int) sprintf('%u', crc32($key))) % 100 < $percentage;
    }

    protected function scopeKey(mixed $scope): string
    {
        if ($scope === null) {
            return $this->name . ':guest';
        }

        if (is_object($scope) && method_exists($scope, 'getKey')) {
            return $this->name . ':' . $scope::class . ':' . $scope->getKey();
        }

        return $this->name . ':' . (string) (Arr::get((array) $scope, 'id', spl_object_id((object) $scope)));
    }

    protected function workspaceFor(mixed $scope): mixed
    {
        // Scope may already be the workspace…
        $workspaceModel = config('larasaas.billing.workspace_model');

        if ($workspaceModel && $scope instanceof $workspaceModel) {
            return $scope;
        }

        // …or a user with a resolvable workspace.
        $resolver = config('larasaas.billing.workspace_resolver');

        if (is_callable($resolver) && $scope) {
            return $resolver($scope);
        }

        return $scope && method_exists($scope, 'currentWorkspace') ? $scope->currentWorkspace() : null;
    }
}
