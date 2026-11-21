<?php

namespace App\Livewire\Site;

use App\Models\ContactMessage;
use App\Services\Notifier;
use App\Support\Seo;
use App\Support\SiteContent;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class Contact extends Component
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $organisation = '';

    public string $topic = 'general';

    public string $message = '';

    /** Honeypot: real visitors never fill this in. */
    public string $website = '';

    public bool $sent = false;

    public function send(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:160', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:30', 'required_without:email'],
            'organisation' => ['nullable', 'string', 'max:160'],
            'topic' => ['required', 'in:'.implode(',', array_keys(ContactMessage::TOPICS))],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
        ], ['email.required_without' => 'Please give an email address or a phone number so we can reply.', 'phone.required_without' => 'Please give a phone number or an email address so we can reply.']);

        if ($this->website !== '') {
            $this->sent = true; // silently drop bot submissions

            return;
        }

        $key = 'contact:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('message', 'Too many messages sent. Please try again in a few minutes.');

            return;
        }

        RateLimiter::hit($key, 600);

        $contact = ContactMessage::create([
            'name' => $this->name, 'email' => $this->email ?: null, 'phone' => $this->phone ?: null, 'organisation' => $this->organisation ?: null,
            'topic' => $this->topic, 'message' => $this->message, 'ip_address' => request()->ip(),
        ]);

        Notifier::send('inquiries.view', 'New website enquiry', $contact->name.' — '.ContactMessage::TOPICS[$contact->topic], route('inquiries.index'), 'envelope');

        $this->reset(['name', 'email', 'phone', 'organisation', 'message', 'website']);
        $this->sent = true;
    }

    public function render()
    {
        return view('livewire.site.contact', [
            'topics' => ContactMessage::TOPICS,
            'heroTitle' => SiteContent::get('contact.hero_title'),
            'heroText' => SiteContent::get('contact.hero_text'),
        ])->layout('components.layouts.site', ['seo' => Seo::page('contact', ['breadcrumbs' => [['Home', Seo::url('/')], ['Contact', null]]])]);
    }
}
