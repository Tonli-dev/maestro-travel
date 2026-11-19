<?php

namespace App\Support;

class ContactLinks
{
    public function whatsapp(string $message): ?string
    {
        $number = $this->normalizeNumber(config('contact.whatsapp_number'));

        return $number ? 'https://wa.me/'.$number.'?text='.rawurlencode($message) : null;
    }

    public function viberNumber(): ?string
    {
        $number = $this->normalizeNumber(config('contact.viber_number'));

        return $number ? '+'.$number : null;
    }

    private function normalizeNumber(?string $number): ?string
    {
        $number = preg_replace('/[\s()+-]/', '', $number ?? '');

        return preg_match('/^[1-9][0-9]{6,14}$/D', $number) ? $number : null;
    }
}
