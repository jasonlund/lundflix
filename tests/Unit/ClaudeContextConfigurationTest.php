<?php

use Symfony\Component\Process\Process;

function runHook(string $path, array $payload): Process
{
    $process = new Process([base_path($path)], base_path());
    $process->setInput(json_encode($payload, JSON_THROW_ON_ERROR));
    $process->run();

    return $process;
}

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

it('protects secrets and wires the verification hooks', function () {
    $settings = json_decode(file_get_contents(base_path('.claude/settings.json')), true, flags: JSON_THROW_ON_ERROR);

    expect($settings['permissions']['deny'])
        ->toContain('Read(./.env)', 'Read(./.env.*)', 'Read(./auth.json)');

    expect(array_keys($settings['hooks']))
        ->toContain('SessionStart', 'PostToolUse', 'InstructionsLoaded', 'UserPromptSubmit', 'Stop');
});

it('keeps custom Claude rules focused on behavior instead of replacing Boost package guidance', function () {
    expect(is_file(base_path('.claude/rules/behavior.md')))->toBeTrue();
    expect(is_file(base_path('.claude/rules/testing.md')))->toBeTrue();
    expect(is_file(base_path('.claude/rules/php-laravel.md')))->toBeFalse();
    expect(is_file(base_path('.claude/rules/frontend-livewire.md')))->toBeFalse();
});

it('ships a read-only verification reviewer and executable hooks', function () {
    $agent = file_get_contents(base_path('.claude/agents/verification-reviewer.md'));

    expect($agent)
        ->toContain('name: verification-reviewer')
        ->toContain('tools: Read, Glob, Grep, Bash')
        ->not->toContain('Edit')
        ->not->toContain('Write');

    collect([
        '.claude/hooks/reset-session-state.sh',
        '.claude/hooks/record-edit.sh',
        '.claude/hooks/record-verification.sh',
        '.claude/hooks/log-instructions-loaded.sh',
        '.claude/hooks/inject-implementation-reminder.sh',
        '.claude/hooks/require-verification-before-stop.sh',
    ])->each(function (string $path): void {
        expect(is_file(base_path($path)))->toBeTrue();
        expect(is_executable(base_path($path)))->toBeTrue();
    });
});

it('injects a verification reminder for implementation prompts', function () {
    $process = runHook('.claude/hooks/inject-implementation-reminder.sh', [
        'prompt' => 'Fix the failing validation tests and update the hook config.',
    ]);

    expect($process->isSuccessful())->toBeTrue();

    $output = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

    expect($output['hookSpecificOutput']['hookEventName'])->toBe('UserPromptSubmit');
    expect($output['hookSpecificOutput']['additionalContext'])->toContain('VERIFICATION_SKIPPED');
});

it('blocks stopping when repo changes have not been reverified', function () {
    $runtimeDirectory = base_path('.claude/runtime');
    $temporaryFile = base_path('.claude-stop-hook-test.tmp');

    if (! is_dir($runtimeDirectory)) {
        mkdir($runtimeDirectory, 0777, true);
    }

    file_put_contents($temporaryFile, 'dirty');
    @unlink($runtimeDirectory.'/last_edit_epoch');
    @unlink($runtimeDirectory.'/last_verification_epoch');

    try {
        $process = runHook('.claude/hooks/require-verification-before-stop.sh', [
            'stop_hook_active' => false,
            'last_assistant_message' => 'Done.',
        ]);

        expect($process->isSuccessful())->toBeTrue();

        $output = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

        expect($output['decision'])->toBe('block');
        expect($output['reason'])->toContain('Run the smallest relevant verification before stopping');
    } finally {
        @unlink($temporaryFile);
    }
});

it('allows stopping after explicit verification skip language', function () {
    $temporaryFile = base_path('.claude-stop-hook-test.tmp');
    file_put_contents($temporaryFile, 'dirty');

    try {
        $process = runHook('.claude/hooks/require-verification-before-stop.sh', [
            'stop_hook_active' => false,
            'last_assistant_message' => 'VERIFICATION_SKIPPED: prompt-only change',
        ]);

        expect($process->isSuccessful())->toBeTrue();
        expect($process->getOutput())->toBeEmpty();
    } finally {
        @unlink($temporaryFile);
    }
});
