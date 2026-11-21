<?php

namespace App\Livewire\Concerns;

use Flux\Flux;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

trait Page
{
    protected function page(string $view, array $data = [], ?string $title = null)
    {
        return view($view, $data)->layout('components.layouts.app', ['title' => $title]);
    }

    protected function guard(string $ability): void
    {
        Gate::authorize($ability);
    }

    protected function toast(string $text, string $variant = 'success'): void
    {
        Flux::toast(text: $text, variant: $variant);
    }

    /** Run a business action, turning rule violations into a visible error message. */
    protected function attempt(callable $action, ?string $success = null): bool
    {
        try {
            $action();
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (RuntimeException|InvalidArgumentException|LogicException $e) {
            if ($e instanceof \Illuminate\Database\QueryException) {
                report($e);
                $this->toast('The record could not be saved because of a database constraint.', 'danger');

                return false;
            }

            $this->toast($e->getMessage(), 'danger');

            return false;
        }

        if ($success) {
            $this->toast($success);
        }

        return true;
    }
}
