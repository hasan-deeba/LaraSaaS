<?php

namespace LaraSaaS\Tests;

use LaraSaaS\LaraSaaS;

class TextDirectionTest extends TestCase
{
    public function test_rtl_languages_and_their_region_variants(): void
    {
        $larasaas = app(LaraSaaS::class);

        foreach (['ar', 'ar_SA', 'ar-EG', 'AR', 'he', 'fa', 'ur'] as $locale) {
            $this->assertSame('rtl', $larasaas->textDirection($locale), $locale);
        }

        foreach (['en', 'en_US', 'fr', 'nl'] as $locale) {
            $this->assertSame('ltr', $larasaas->textDirection($locale), $locale);
        }
    }

    public function test_defaults_to_the_app_locale(): void
    {
        app()->setLocale('ar');
        $this->assertSame('rtl', app(LaraSaaS::class)->textDirection());

        app()->setLocale('en');
        $this->assertSame('ltr', app(LaraSaaS::class)->textDirection());
    }

    public function test_rtl_locales_are_configurable(): void
    {
        config(['larasaas.rtl_locales' => ['ps']]);

        $this->assertSame('rtl', app(LaraSaaS::class)->textDirection('ps_AF'));
        $this->assertSame('ltr', app(LaraSaaS::class)->textDirection('ar'));
    }
}
