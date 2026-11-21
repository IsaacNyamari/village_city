<?php

namespace App\Livewire\Pages;

use App\Livewire\Concerns\Page;
use App\Support\Docs;
use Livewire\Component;

/** Help & Documentation. Open to every signed-in user; guides shown depend on the user's role. */
class Help extends Component
{
    use Page;

    public ?string $doc = null;

    public string $search = '';

    public function mount(?string $doc = null): void
    {
        abort_if($doc !== null && ! Docs::exists($doc), 404);

        if ($doc !== null) {
            abort_unless(isset(Docs::available(auth()->user())[$doc]), 403);
        }

        $this->doc = $doc;
    }

    public function render()
    {
        $user = auth()->user();
        $available = Docs::available($user);
        $current = $this->doc ? Docs::render($this->doc) : null;

        return $this->page('livewire.pages.help', [
            'available' => $available,
            'currentDoc' => $this->doc ? $available[$this->doc] : null,
            'content' => $current,
            'quickStart' => $this->doc === null ? Docs::roleQuickStart($user) : null,
            'role' => $user->roleName(),
            'results' => Docs::search($this->search, $user),
        ], $this->doc ? $available[$this->doc]['title'] : 'Help & Documentation');
    }
}
