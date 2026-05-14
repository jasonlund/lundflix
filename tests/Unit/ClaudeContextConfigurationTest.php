<?php

it('keeps the root Claude file thin and verification-focused', function () {
    $claude = file_get_contents(base_path('CLAUDE.md'));

    expect($claude)
        ->toContain('@CLAUDE.project.md')
        ->toContain('VERIFICATION_SKIPPED')
        ->toContain('<laravel-boost-guidelines>')
        ->toContain('Laravel, Livewire, Filament, Tailwind, Pest')
        ->toContain('Keep the Laravel Boost block below intact');

    expect(str_starts_with($claude, '@CLAUDE.project.md'))->toBeTrue();
});

it('protects secrets via deny permissions', function () {
    $settings = json_decode(file_get_contents(base_path('.claude/settings.json')), true, flags: JSON_THROW_ON_ERROR);

    expect($settings['permissions']['deny'])
        ->toContain('Read(./.env)', 'Read(./.env.*)', 'Read(./auth.json)');
});

it('keeps custom Claude rules focused on behavior instead of replacing Boost package guidance', function () {
    expect(is_file(base_path('.claude/rules/behavior.md')))->toBeTrue();
    expect(is_file(base_path('.claude/rules/testing.md')))->toBeTrue();
    expect(is_file(base_path('.claude/rules/php-laravel.md')))->toBeFalse();
    expect(is_file(base_path('.claude/rules/frontend-livewire.md')))->toBeFalse();
});

it('ships a read-only verification reviewer', function () {
    $agent = file_get_contents(base_path('.claude/agents/verification-reviewer.md'));

    expect($agent)
        ->toContain('name: verification-reviewer')
        ->toContain('tools: Read, Glob, Grep, Bash')
        ->not->toContain('Edit')
        ->not->toContain('Write');
});
