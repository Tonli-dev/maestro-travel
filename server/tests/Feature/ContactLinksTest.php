<?php

namespace Tests\Feature;

use App\Support\ContactLinks;
use Tests\TestCase;

class ContactLinksTest extends TestCase
{
    public function test_unconfigured_contacts_do_not_use_demo_numbers(): void
    {
        config(['contact.whatsapp_number' => null, 'contact.viber_number' => null]);

        $links = new ContactLinks;

        $this->assertNull($links->whatsapp('Ponuda'));
        $this->assertNull($links->viberNumber());
    }

    public function test_whatsapp_encodes_offer_text_and_viber_returns_copyable_number(): void
    {
        config(['contact.whatsapp_number' => '+387 60 000 0000', 'contact.viber_number' => '+387 60 000 0000']);

        $links = new ContactLinks;

        $this->assertSame('https://wa.me/387600000000?text=Hotel%20%26%20paket', $links->whatsapp('Hotel & paket'));
        $this->assertSame('+387600000000', $links->viberNumber());
    }

    public function test_invalid_phone_cannot_create_an_external_link(): void
    {
        config(['contact.whatsapp_number' => 'https://example.test', 'contact.viber_number' => '06123456']);

        $links = new ContactLinks;

        $this->assertNull($links->whatsapp('Ponuda'));
        $this->assertNull($links->viberNumber());
    }
}
