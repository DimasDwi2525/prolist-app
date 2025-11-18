<?php

namespace App\Livewire;

use Livewire\Component;

class PhcNotifications extends Component
{
    protected $listeners = [
        'echo:phc.created,phc.created' => '$refresh',
    ];

    public function render()
    {
        return view('livewire.phc-notifications');
    }
}
