<?php

namespace App\View\Components\Layouts;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\View\Component;

class App extends Component
{
    public string $defaultBackground;

    public function __construct(public ?string $backgroundImage = null, public ?string $title = null)
    {
        $this->defaultBackground = Vite::image('default-background.svg');
        $this->backgroundImage ??= $this->defaultBackground;
    }

    public function render(): View
    {
        return view('components.layouts.app');
    }
}
