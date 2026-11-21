<?php

namespace App\Livewire\Pages;

use App\Livewire\Concerns\Page;
use Livewire\Component;

/**
 * Read-only profile page: header, key figures, details and history tables.
 * Subclasses return the structure from data().
 *
 * @phpstan-type Section array{title:string, columns:list<string>, rows:list<list<mixed>>, empty:string}
 */
abstract class DetailPage extends Component
{
    use Page;

    abstract protected function permission(): string;

    /** @return array{title:string, subtitle:?string, back:string, status:?string, details:array, stats:array, sections:list<array>, edit?:?string} */
    abstract protected function data(): array;

    protected function extraView(): ?string
    {
        return null;
    }

    public function render()
    {
        $this->guard($this->permission());
        $data = $this->data();

        return $this->page('livewire.pages.detail', ['d' => $data, 'extra' => $this->extraView()], $data['title']);
    }
}
