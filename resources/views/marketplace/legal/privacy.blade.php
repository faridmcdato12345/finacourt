@extends('layouts.marketplace')

@section('content')
    <section class="border-b border-slate-200 bg-white">
        <div class="page-shell py-14 sm:py-20">
            <p class="eyebrow">Your information</p>
            <h1 class="mt-4 max-w-4xl text-4xl font-bold tracking-[-0.04em] sm:text-5xl">Privacy Policy</h1>
            <p class="mt-5 max-w-3xl text-base leading-7 text-slate-600">This policy explains what FinACourt collects, why we use it, who may receive it, and the choices available to players, court owners, staff, and sales partners.</p>
            <p class="mt-4 text-sm text-slate-500">Effective {{ $effectiveDate }}</p>
        </div>
    </section>

    <section class="page-shell py-12 sm:py-16">
        <div class="grid gap-10 lg:grid-cols-[16rem_minmax(0,1fr)] lg:items-start">
            <aside class="rounded-2xl border border-slate-200 bg-white p-5 lg:sticky lg:top-24">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">On this page</p>
                <nav class="mt-4 space-y-3 text-sm text-slate-600" aria-label="Privacy policy sections">
                    <a class="block hover:text-court-700" href="#information-we-collect">Information we collect</a>
                    <a class="block hover:text-court-700" href="#how-we-use-information">How we use it</a>
                    <a class="block hover:text-court-700" href="#how-information-is-shared">How it is shared</a>
                    <a class="block hover:text-court-700" href="#payments">Payments</a>
                    <a class="block hover:text-court-700" href="#analytics-and-location">Analytics and location</a>
                    <a class="block hover:text-court-700" href="#retention-and-security">Retention and security</a>
                    <a class="block hover:text-court-700" href="#your-choices">Your choices</a>
                    <a class="block hover:text-court-700" href="#contact-us">Contact us</a>
                </nav>
            </aside>

            <article class="min-w-0 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-10">
                <div class="rounded-2xl border border-court-200 bg-court-50 p-5 text-sm leading-7 text-court-950">
                    <p class="font-semibold">The short version</p>
                    <p class="mt-1">FinACourt uses the information needed to help players find and book courts, help owners run their venues, process payments safely, and understand whether the marketplace is working. Court owners receive their own customer and booking information—not another venue's private data or individual marketplace search histories.</p>
                </div>

                <div class="mt-10 space-y-12 text-[0.95rem] leading-7 text-slate-600">
                    <section id="information-we-collect" class="scroll-mt-28">
                        <h2 class="text-2xl font-semibold tracking-tight text-slate-950">1. Information we collect</h2>
                        <div class="mt-4 space-y-4">
                            <p><strong class="text-slate-900">Account information.</strong> We collect details such as your name, email address, password credential, account role, verification state, and optional social sign-in identifier. Passwords are stored in protected hashed form. Social access tokens are not retained for normal player or owner sign-in.</p>
                            <p><strong class="text-slate-900">Player and booking information.</strong> When you reserve a court, we process contact details, selected venue and court, date and time, price, discounts, service fees, booking status, payment choice, booking source, and related messages or reviews.</p>
                            <p><strong class="text-slate-900">Owner and venue information.</strong> Owners and authorized staff may provide business details, public contact information, venue addresses, map coordinates, opening hours, courts, prices, amenities, photos, payout details, customer records, promotions, and staff permissions.</p>
                            <p><strong class="text-slate-900">Directory information.</strong> FinACourt may publish factual venue information gathered from lawful public sources. We keep source and verification details for these directory listings and clearly distinguish them from bookable partner venues.</p>
                            <p><strong class="text-slate-900">Technical and activity information.</strong> We process IP address, browser and device information, essential session and security data, page visits, referral source, QR or campaign identifiers, searches, filter choices, booking steps, and error or audit logs.</p>
                            <p><strong class="text-slate-900">Communications and preferences.</strong> We keep messages you send us, reports about listings, venue-claim information, notification preferences, marketing consent, unsubscribe state, and campaign delivery or click records where available.</p>
                        </div>
                    </section>

                    <section id="how-we-use-information" class="scroll-mt-28">
                        <h2 class="text-2xl font-semibold tracking-tight text-slate-950">2. How we use information</h2>
                        <p class="mt-4">We use information to:</p>
                        <ul class="mt-3 list-disc space-y-2 pl-6">
                            <li>create and protect accounts, organizations, permissions, and venue ownership;</li>
                            <li>show venues, prices, promotions, and server-checked availability;</li>
                            <li>create booking holds, prevent conflicts, confirm reservations, and send booking notices;</li>
                            <li>start secure online checkout, verify provider notifications, record refunds, and prepare owner settlements;</li>
                            <li>give owners information about their own bookings, customers, earnings, venue visits, and promotions;</li>
                            <li>measure marketplace demand and acquisition sources using privacy-conscious aggregation;</li>
                            <li>send transactional messages and, when permitted, owner-approved rebooking or comeback messages;</li>
                            <li>detect misuse, investigate disputes, secure the service, maintain audit history, and meet legal obligations; and</li>
                            <li>improve FinACourt's reliability, usability, marketplace supply, and player experience.</li>
                        </ul>
                    </section>

                    <section id="how-information-is-shared" class="scroll-mt-28">
                        <h2 class="text-2xl font-semibold tracking-tight text-slate-950">3. How information is shared</h2>
                        <div class="mt-4 space-y-4">
                            <p><strong class="text-slate-900">With the venue you book.</strong> The relevant court owner and authorized staff receive the booking and customer details needed to provide the reservation, collect pay-at-venue amounts, communicate about the booking, and handle legitimate support.</p>
                            <p><strong class="text-slate-900">With service providers.</strong> We may use providers for hosting, email delivery, payment processing, maps, social sign-in, error monitoring, and other infrastructure. They receive only the information needed to perform their service and are subject to their own obligations and policies.</p>
                            <p><strong class="text-slate-900">Public information.</strong> Published venue pages, directory listings, deals, operating hours, public contact details, photos, and eligible reviews can be seen by anyone. Owners choose which managed venue details are published, subject to marketplace review and safety rules.</p>
                            <p><strong class="text-slate-900">For safety or legal reasons.</strong> We may disclose information when reasonably necessary to comply with law, protect users or the public, enforce our terms, investigate fraud, or defend legal rights.</p>
                            <p><strong class="text-slate-900">Business changes.</strong> Information may be transferred as part of a merger, financing, acquisition, reorganization, or sale of all or part of the service, with appropriate confidentiality and notice where required.</p>
                        </div>
                        <p class="mt-4 font-medium text-slate-900">We do not sell an individual player's private search history to court owners. Owner demand reports use minimum group sizes and omit player names, contact details, account identifiers, and precise individual locations.</p>
                    </section>

                    <section id="payments" class="scroll-mt-28">
                        <h2 class="text-2xl font-semibold tracking-tight text-slate-950">4. Payments and payout information</h2>
                        <p class="mt-4">Online checkout is hosted by the configured payment provider, currently PayMongo where enabled. The provider collects the card, wallet, or QR payment details required to complete checkout. FinACourt stores payment references, amount, currency, status, payment method summary, provider fees where reported, refund state, and verification history. FinACourt does not intentionally store full card numbers, card security codes, or a player's wallet password.</p>
                        <p class="mt-4">Owner payout profiles and requests are visible only to authorized owner and platform accounts. Sensitive payout values are masked in normal displays and are used to prepare or record legitimate court-owner settlements.</p>
                    </section>

                    <section id="analytics-and-location" class="scroll-mt-28">
                        <h2 class="text-2xl font-semibold tracking-tight text-slate-950">5. Analytics, cookies, and location</h2>
                        <div class="mt-4 space-y-4">
                            <p>FinACourt uses essential cookies or similar browser storage for sign-in sessions, security protection, preferences, booking flow, anonymous session measurement, referral attribution, and PWA operation. Blocking essential storage may prevent sign-in or booking from working.</p>
                            <p>Marketplace analytics may record searched sport, city or area, requested date and time, price or setting filters, result count, whether availability existed, and the source that led to a booking. Owner reports are tenant-scoped and demand reports are aggregated before display.</p>
                            <p>If you choose <em>Use my current location</em> while setting up a venue, your browser asks permission and provides coordinates so you can position the venue pin. You can adjust the pin manually. FinACourt does not need continuous background location access.</p>
                        </div>
                    </section>

                    <section id="retention-and-security" class="scroll-mt-28">
                        <h2 class="text-2xl font-semibold tracking-tight text-slate-950">6. Retention and security</h2>
                        <p class="mt-4">We keep information only for as long as reasonably needed to provide the service, preserve booking and payment history, resolve disputes, prevent fraud, maintain required audit records, and meet legal obligations. Retention periods vary by record type. Deleting an account may not erase records that must be preserved for confirmed bookings, payments, refunds, settlements, fraud prevention, or legal compliance.</p>
                        <p class="mt-4">We use measures such as encrypted transport, hashed passwords, access controls, tenant isolation, secure webhook verification, protected secrets, audit history, and restricted owner/platform permissions. No internet service can guarantee absolute security, so please use a unique password and contact us if you suspect unauthorized access.</p>
                    </section>

                    <section id="your-choices" class="scroll-mt-28">
                        <h2 class="text-2xl font-semibold tracking-tight text-slate-950">7. Your choices and rights</h2>
                        <p class="mt-4">Depending on applicable law and the circumstances, you may ask to access, correct, update, export, object to, restrict, or delete personal information. You may withdraw marketing consent or change available notification preferences without stopping essential booking, security, or account messages.</p>
                        <p class="mt-4">Venue owners can update managed public venue details in their workspace. Anyone may report inaccurate public directory information. Venue ownership requests require independent verification and are not approved solely because someone submitted a request.</p>
                        <p class="mt-4">We may need to verify your identity and authority before fulfilling a privacy request. Some requests may be limited where retention or processing is required for contracts, security, other people's rights, or legal obligations.</p>
                    </section>

                    <section>
                        <h2 class="text-2xl font-semibold tracking-tight text-slate-950">8. Children and guardians</h2>
                        <p class="mt-4">A person who is not legally able to enter a binding booking or payment agreement should use FinACourt only with the involvement and permission of a parent or legal guardian. Please contact us if you believe a child provided personal information without appropriate permission.</p>
                    </section>

                    <section>
                        <h2 class="text-2xl font-semibold tracking-tight text-slate-950">9. Changes to this policy</h2>
                        <p class="mt-4">We may update this policy when FinACourt changes or when legal requirements develop. We will update the effective date and provide additional notice when a material change requires it. Continued use after an update is subject to the revised policy, without limiting rights that cannot legally be waived.</p>
                    </section>

                    <section id="contact-us" class="scroll-mt-28 rounded-2xl bg-slate-50 p-5 sm:p-6">
                        <h2 class="text-2xl font-semibold tracking-tight text-slate-950">10. Contact us</h2>
                        <p class="mt-3">{{ $operatorName }} is responsible for this policy. For privacy questions or requests, email <a class="font-semibold text-court-700 underline decoration-court-200 underline-offset-4" href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>.</p>
                        <p class="mt-3 text-sm">Please do not email passwords, card information, wallet credentials, or other highly sensitive payment details.</p>
                    </section>
                </div>
            </article>
        </div>
    </section>
@endsection
