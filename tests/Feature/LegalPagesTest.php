<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_read_the_public_privacy_policy(): void
    {
        config()->set('legal.operator_name', 'FinACourt Test Operator');
        config()->set('legal.contact_email', 'privacy@finacourt.test');
        config()->set('legal.effective_date', 'September 2, 2026');

        $this->get(route('marketplace.privacy'))
            ->assertOk()
            ->assertHeader('X-PWA-Cache', 'public-short')
            ->assertSee('<link rel="canonical" href="'.route('marketplace.privacy').'">', false)
            ->assertSee('<meta name="robots" content="index,follow">', false)
            ->assertSee('Privacy Policy')
            ->assertSee('Effective September 2, 2026')
            ->assertSee('privacy@finacourt.test')
            ->assertSee('PayMongo')
            ->assertSee('Google account and Business Profile data')
            ->assertSee('FinACourt receives your name, email address, and Google account identifier')
            ->assertSee('encrypted access and refresh tokens')
            ->assertSee('does not sell Google user data')
            ->assertSee('Google API Services User Data Policy')
            ->assertSee('including its Limited Use requirements')
            ->assertSee('removes the saved access and refresh tokens')
            ->assertSee("We do not sell an individual player's private search history to court owners.", false)
            ->assertSee('href="/terms">Terms of Service', false)
            ->assertSee('application/ld+json', false)
            ->assertDontSee('data-page=', false);
    }

    public function test_guests_can_read_the_public_terms_of_service(): void
    {
        config()->set('legal.operator_name', 'FinACourt Test Operator');
        config()->set('legal.contact_email', 'legal@finacourt.test');
        config()->set('legal.effective_date', 'September 2, 2026');

        $this->get(route('marketplace.terms'))
            ->assertOk()
            ->assertHeader('X-PWA-Cache', 'public-short')
            ->assertSee('<link rel="canonical" href="'.route('marketplace.terms').'">', false)
            ->assertSee('<meta name="robots" content="index,follow">', false)
            ->assertSee('Terms of Service')
            ->assertSee('FinACourt Test Operator')
            ->assertSee('legal@finacourt.test')
            ->assertSee('Pay at venue')
            ->assertSee('platform service fee')
            ->assertSee('verified provider notification')
            ->assertSee('href="/privacy">Privacy Policy', false)
            ->assertSee('application/ld+json', false)
            ->assertDontSee('data-page=', false);
    }

    public function test_legal_pages_are_in_the_public_sitemap(): void
    {
        $this->get(route('marketplace.sitemap'))
            ->assertOk()
            ->assertSee(route('marketplace.privacy'), false)
            ->assertSee(route('marketplace.terms'), false);
    }
}
