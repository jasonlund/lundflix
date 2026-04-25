<?php

use Flux\Flux;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component {
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|timezone:all')]
    public string $timezone = '';

    public string $email = '';

    public function mount(): void
    {
        $user = auth()->user();

        $this->name = $user->name;
        $this->timezone = $user->timezone;
        $this->email = $user->email;
    }

    /**
     * @return array<string, string>
     */
    public function timezoneOptions(): array
    {
        $zones = [
            'Pacific/Midway' => 'Midway Island, Samoa',
            'Pacific/Honolulu' => 'Hawaii',
            'America/Anchorage' => 'Alaska',
            'America/Los_Angeles' => 'Pacific Time (US & Canada)',
            'America/Denver' => 'Mountain Time (US & Canada)',
            'America/Phoenix' => 'Arizona',
            'America/Chicago' => 'Central Time (US & Canada)',
            'America/New_York' => 'Eastern Time (US & Canada)',
            'America/Halifax' => 'Atlantic Time (Canada)',
            'America/St_Johns' => 'Newfoundland',
            'America/Sao_Paulo' => 'Brasilia',
            'America/Argentina/Buenos_Aires' => 'Buenos Aires',
            'Atlantic/Reykjavik' => 'Reykjavik',
            'Europe/London' => 'London, Dublin',
            'Europe/Paris' => 'Paris, Berlin, Amsterdam',
            'Europe/Helsinki' => 'Helsinki, Kyiv, Riga',
            'Europe/Athens' => 'Athens, Bucharest, Istanbul',
            'Europe/Moscow' => 'Moscow, St. Petersburg',
            'Africa/Cairo' => 'Cairo',
            'Africa/Lagos' => 'West Africa',
            'Africa/Johannesburg' => 'Johannesburg, Pretoria',
            'Asia/Dubai' => 'Dubai, Abu Dhabi',
            'Asia/Kolkata' => 'Mumbai, Kolkata, New Delhi',
            'Asia/Dhaka' => 'Dhaka',
            'Asia/Bangkok' => 'Bangkok, Hanoi, Jakarta',
            'Asia/Shanghai' => 'Beijing, Shanghai, Hong Kong',
            'Asia/Singapore' => 'Singapore, Kuala Lumpur',
            'Asia/Tokyo' => 'Tokyo, Osaka',
            'Asia/Seoul' => 'Seoul',
            'Australia/Perth' => 'Perth',
            'Australia/Adelaide' => 'Adelaide',
            'Australia/Sydney' => 'Sydney, Melbourne',
            'Pacific/Auckland' => 'Auckland, Wellington',
        ];

        if (! isset($zones[$this->timezone]) && in_array($this->timezone, \DateTimeZone::listIdentifiers())) {
            $zones[$this->timezone] = str_replace(['/', '_'], [' / ', ' '], $this->timezone);
        }

        $now = new \DateTimeImmutable();
        $options = [];

        foreach ($zones as $identifier => $label) {
            $offset = (new \DateTimeZone($identifier))->getOffset($now);
            $hours = intdiv($offset, 3600);
            $minutes = abs(($offset % 3600) / 60);
            $options[$identifier] = [
                'label' => sprintf('(UTC%+03d:%02d) %s', $hours, $minutes, $label),
                'offset' => $offset,
            ];
        }

        uasort($options, fn ($a, $b) => $a['offset'] <=> $b['offset']);

        return array_map(fn ($item) => $item['label'], $options);
    }

    public function save(): void
    {
        $validated = $this->validate();

        auth()
            ->user()
            ->update([
                'name' => $validated['name'],
                'timezone' => $validated['timezone'],
            ]);

        $this->modal('profile')->close();

        Flux::toast(text: __('lundbergh.toast.profile_updated'), variant: 'success');
    }
};
?>

<div>
    <flux:modal name="profile" :bubble="false" :dismissible="false" size="sm">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">Your Profile</flux:heading>

            <flux:input label="Email" type="email" :value="$email" disabled />

            <flux:field>
                <flux:label>Name</flux:label>
                <flux:input wire:model.blur="name" required />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>Timezone</flux:label>
                <flux:select variant="listbox" searchable wire:model="timezone" placeholder="Select timezone...">
                    @foreach ($this->timezoneOptions() as $value => $label)
                        <flux:select.option :$value>{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="timezone" />
            </flux:field>

            <x-lundbergh-bubble :message="__('lundbergh.form.profile_password_hint')" />

            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">Save</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
