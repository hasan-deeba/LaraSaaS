<?php

namespace LaraSaaS\Support;

use LogicException;

/** Developer-owned catalog; plan records contain values, never executable definitions. */
class PlanFeatures
{
    public function definitions(): array
    {
        $definitions = config('larasaas.plan_features', []);

        foreach ($definitions as $key => $definition) {
            if (! preg_match('/^[a-z][a-z0-9_]*$/', $key)
                || ! in_array($definition['type'] ?? null, ['boolean', 'integer', 'number'], true)) {
                throw new LogicException("Invalid LaraSaaS plan feature definition [{$key}]. Use a snake_case key and boolean, integer or number type.");
            }
        }

        return $definitions;
    }

    public function rules(): array
    {
        $definitions = $this->definitions();
        $rules = ['features' => ['bail', 'present', 'array', function ($attribute, $values, $fail) use ($definitions) {
            if (array_diff(array_keys($values), array_keys($definitions))) {
                $fail('Only developer-defined plan features may be assigned.');
            }
        }]];
        foreach ($definitions as $key => $definition) {
            $type = $definition['type'];
            $rules["features.{$key}"] = ['sometimes', $type === 'number' ? 'numeric' : $type];
            if ($type !== 'boolean') {
                $rules["features.{$key}"][] = 'min:0';
                if ($definition['allow_unlimited'] ?? false) {
                    array_pop($rules["features.{$key}"]);
                    $rules["features.{$key}"][] = function ($attribute, $value, $fail) {
                        if (is_numeric($value) && (float) $value < 0 && (float) $value !== -1.0) {
                            $fail('The limit must be non-negative or -1 for unlimited.');
                        }
                    };
                }
            }
        }

        return $rules;
    }

    public function values(array $stored): array
    {
        $values = [];
        foreach ($this->definitions() as $key => $definition) {
            $value = $stored[$key] ?? $definition['default'] ?? 0;
            $values[$key] = match ($definition['type']) {
                'boolean' => (bool) $value,
                'integer' => (int) $value,
                'number' => (float) $value,
            };
        }

        return $values;
    }

    public function labels(array $values): array
    {
        $labels = [];
        foreach ($this->definitions() as $key => $definition) {
            $value = $values[$key];
            $label = $definition['label'] ?? $key;
            if ($definition['type'] === 'boolean') {
                if ($value) {
                    $labels[] = $label;
                }
            } else {
                $labels[] = $label . ': ' . ($value == -1 ? 'Unlimited' : $value . (isset($definition['unit']) ? ' ' . $definition['unit'] : ''));
            }
        }

        return $labels;
    }
}
