<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class LegalController extends Controller
{
    public function privacy(): View
    {
        return $this->page(
            view: 'marketplace.legal.privacy',
            title: 'Privacy Policy',
            description: 'Learn what information FinACourt collects, why it is used, when it is shared, and the choices available to players and court owners.',
            routeName: 'marketplace.privacy',
        );
    }

    public function terms(): View
    {
        return $this->page(
            view: 'marketplace.legal.terms',
            title: 'Terms of Service',
            description: 'Read the rules that apply when players, court owners, staff, and partners use FinACourt discovery, booking, payment, and venue tools.',
            routeName: 'marketplace.terms',
        );
    }

    private function page(string $view, string $title, string $description, string $routeName): View
    {
        $canonical = route($routeName);

        return view($view, [
            'operatorName' => (string) config('legal.operator_name'),
            'contactEmail' => (string) config('legal.contact_email'),
            'effectiveDate' => (string) config('legal.effective_date'),
            'seo' => [
                'title' => $title,
                'description' => $description,
                'canonical' => $canonical,
                'robots' => 'index,follow',
                'type' => 'website',
            ],
            'structuredData' => [[
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'name' => $title,
                'description' => $description,
                'url' => $canonical,
            ]],
        ]);
    }
}
