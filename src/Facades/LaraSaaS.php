<?php

namespace LaraSaaS\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string userModel()
 * @method static \LaraSaaS\LaraSaaS useUserModel(string $model)
 * @method static \Illuminate\Database\Eloquent\Model|null billable(\Illuminate\Http\Request $request)
 * @method static string subscriptionName()
 * @method static array planFeatureDefinitions()
 * @method static bool|int|float|null entitlement(\Illuminate\Http\Request $request, string $feature)
 * @method static bool allows(\Illuminate\Http\Request $request, string $feature, float $usage = 0)
 * @method static \LaraSaaS\Models\Plan createPlan(array $attributes)
 * @method static \LaraSaaS\Models\Plan updatePlan(\LaraSaaS\Models\Plan $plan, array $attributes)
 * @method static \LaraSaaS\Models\Plan archivePlan(\LaraSaaS\Models\Plan $plan)
 * @method static array plans()
 * @method static array|null plan(?string $key)
 * @method static array planKeys()
 * @method static array pricingForUi()
 * @method static array subscriptionForUi(\Illuminate\Http\Request $request)
 * @method static string|null planKeyForPrice(?string $priceId)
 * @method static array|null priceDetails(?string $priceId)
 * @method static array announcementTypes()
 * @method static array announcementType(string $type)
 * @method static string textDirection(?string $locale = null)
 * @method static bool isAdmin(\Illuminate\Contracts\Auth\Authenticatable $user)
 * @method static string routeName(string $name)
 * @method static string apiBase()
 * @method static string accentColor()
 * @method static string integrationMode()
 * @method static bool servesInertia()
 * @method static bool servesApi()
 * @method static bool servesEmbed()
 * @method static string embedRouteName(string $page)
 * @method static string embedUrl($user = null, string $page = 'pricing', array $params = [], \DateTimeInterface|int|null $minutes = null)
 * @method static ?string embedCredentialHash($user)
 *
 * @see \LaraSaaS\LaraSaaS
 */
class LaraSaaS extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \LaraSaaS\LaraSaaS::class;
    }
}
