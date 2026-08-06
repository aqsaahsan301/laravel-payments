<?php

declare(strict_types=1);

use Aqsaahsan301\LaravelPayments\LaravelPayments;

it('resolves the singleton', function () {
    expect(app(LaravelPayments::class))->toBeInstanceOf(LaravelPayments::class);
});

it('returns the same instance from the container', function () {
    expect(app(LaravelPayments::class))->toBe(app(LaravelPayments::class));
});

it('merges the package config', function () {
    expect(config('laravel-payments.placeholder'))->toBe('default');
});
