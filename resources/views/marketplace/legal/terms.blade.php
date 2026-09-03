@extends('layouts.marketplace')

@section('content')
    <section class="border-b border-slate-200 bg-white">
        <div class="page-shell py-14 sm:py-20">
            <p class="eyebrow">Using FinACourt</p>
            <h1 class="mt-4 max-w-4xl text-4xl font-bold tracking-[-0.04em] sm:text-5xl">Terms of Service</h1>
            <p class="mt-5 max-w-3xl text-base leading-7 text-slate-600">These terms set the ground rules for using FinACourt as a player, court owner, authorized staff member, sales partner, or visitor.</p>
            <p class="mt-4 text-sm text-slate-500">Effective {{ $effectiveDate }}</p>
        </div>
    </section>

    <section class="page-shell py-12 sm:py-16">
        <div class="grid gap-10 lg:grid-cols-[16rem_minmax(0,1fr)] lg:items-start">
            <aside class="rounded-2xl border border-slate-200 bg-white p-5 lg:sticky lg:top-24">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">On this page</p>
                <nav class="mt-4 space-y-3 text-sm text-slate-600" aria-label="Terms of service sections">
                    <a class="block hover:text-court-700" href="#accepting-these-terms">Accepting these terms</a>
                    <a class="block hover:text-court-700" href="#accounts">Accounts and access</a>
                    <a class="block hover:text-court-700" href="#listings">Venues and listings</a>
                    <a class="block hover:text-court-700" href="#bookings">Bookings</a>
                    <a class="block hover:text-court-700" href="#payments">Payments and fees</a>
                    <a class="block hover:text-court-700" href="#owner-rules">Court-owner rules</a>
                    <a class="block hover:text-court-700" href="#acceptable-use">Acceptable use</a>
                    <a class="block hover:text-court-700" href="#service-boundaries">Service boundaries</a>
                    <a class="block hover:text-court-700" href="#contact-us">Contact us</a>
                </nav>
            </aside>

            <article class="min-w-0 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-10">
                <div class="rounded-2xl border border-court-200 bg-court-50 p-5 text-sm leading-7 text-court-950">
                    <p class="font-semibold">A practical summary</p>
                    <p class="mt-1">Use accurate information, book only when you intend to play, respect venue rules, and pay the amount shown before confirmation. Owners must publish truthful court details and honor valid bookings. FinACourt protects the booking and payment record but cannot guarantee that every independent venue or third-party service will always operate without interruption.</p>
                </div>

                <div class="mt-10 space-y-12 text-[0.95rem] leading-7 text-slate-600">
                    <section id="accepting-these-terms" class="scroll-mt-28">
                        <h2 class="text-2xl font-semibold tracking-tight text-slate-950">1. Accepting these terms</h2>
                        <p class="mt-4">These Terms of Service are an agreement between you and {{ $operatorName }} for use of FinACourt's websites, PWA, booking marketplace, owner tools, and related services. By creating an account, making or managing a booking, publishing a venue, or otherwise using the service, you agree to these terms and our <a class="font-semibold text-court-700" href="{{ route('marketplace.privacy', [], false) }}">Privacy Policy</a>.</p>
                        <p class="mt-4">If you use FinACourt for a business or organization, you confirm that you are authorized to act for it. If you cannot legally enter this agreement, use the service only with the permission and involvement of a person who can.</p>
                    </section>

                    <section id="accounts" class="scroll-mt-28">
                        <h2 class="text-2xl font-semibold tracking-tight text-slate-950">2. Accounts and authorized access</h2>
                        <ul class="mt-4 list-disc space-y-2 pl-6">
                            <li>Provide accurate, current information and keep it updated.</li>
                            <li>Protect your password and social sign-in account. You are responsible for activity performed through your account unless applicable law says otherwise.</li>
                            <li>Use the correct player, owner, staff, sales-partner, or platform access. Do not attempt to enter another organization's private workspace.</li>
                            <li>Owner staff may act only within the permissions granted by their organization.</li>
                            <li>Tell us promptly if you suspect unauthorized access or misuse.</li>
                        </ul>
                        <p class="mt-4">Creating an owner account does not automatically verify venue ownership, approve a public listing, activate Google Business Profile access, or guarantee acceptance into a pilot or paid service.</p>
                    </section>

                    <section id="listings" class="scroll-mt-28">
                        <h2 class="text-2xl font-semibold tracking-tight text-slate-950">3. Venue pages and directory listings</h2>
                        <p class="mt-4"><strong class="text-slate-900">Bookable venues</strong> are managed by an owner organization and may show server-checked schedules, court prices, promotions, and booking options. Availability is checked again when a hold or booking is created.</p>
                        <p class="mt-4"><strong class="text-slate-900">Venue guide listings</strong> may use factual information from public sources and are clearly marked as not yet managed or bookable on FinACourt. Their hours, contact information, and availability may change, so players must confirm details directly with the venue.</p>
                        <p class="mt-4">A claim invitation or request does not prove ownership by itself. FinACourt may require account verification, independently sourced contact confirmation, business evidence, time for disputes, and platform review before attaching a listing to an owner.</p>
                    </section>

                    <section id="bookings" class="scroll-mt-28">
                        <h2 class="text-2xl font-semibold tracking-tight text-slate-950">4. Bookings, holds, and court use</h2>
                        <div class="mt-4 space-y-4">
                            <p>A displayed time is not reserved until FinACourt successfully creates a booking or temporary hold. Holds expire after the time shown and do not guarantee confirmation if payment or another required step is not completed.</p>
                            <p>Bookings apply to the selected venue, court, date, time, duration, and price snapshot. Players must review these details before confirmation and comply with reasonable venue rules concerning arrival, footwear, equipment, safety, conduct, and facility use.</p>
                            <p>Cancellation, rescheduling, no-show, late-arrival, and refund eligibility may depend on the venue's disclosed rules, booking state, payment method, and applicable law. Unless a specific policy says otherwise, do not assume every booking is automatically refundable or transferable.</p>
                            <p>FinACourt and the venue may cancel or refuse a booking when necessary for safety, fraud prevention, payment failure, a genuine availability conflict, facility closure, legal compliance, or a serious breach of these terms. Where appropriate, affected payments will be handled according to the payment and refund record.</p>
                        </div>
                    </section>

                    <section id="payments" class="scroll-mt-28">
                        <h2 class="text-2xl font-semibold tracking-tight text-slate-950">5. Prices, payments, fees, and refunds</h2>
                        <div class="mt-4 space-y-4">
                            <p>Before checkout, FinACourt shows the court price, eligible promotion, platform service fee, payment-provider fee when passed on, and total payable amount. The confirmed booking keeps a price snapshot so later price changes do not silently rewrite it.</p>
                            <p><strong class="text-slate-900">Pay at venue.</strong> The venue collects payment directly. FinACourt may record the expected or owner-reported payment status but does not hold those funds.</p>
                            <p><strong class="text-slate-900">Online checkout.</strong> A configured provider such as PayMongo hosts checkout. Returning to FinACourt does not prove payment. A booking is marked paid only after a verified provider notification or another authorized reconciliation process.</p>
                            <p>Provider processing charges, platform service fees, refunds, reversals, disputes, and chargebacks may affect the final amount received by the venue. Refunds must return through an authorized process and may take additional time to appear through the original payment method.</p>
                            <p>For online court earnings, owner payout availability is subject to the displayed settlement delay, minimum request amount, verified payout details, payment finality, refunds, reversals, and platform review. FinACourt's current payout workspace records manual transfers; a displayed payout request is not a promise of automatic bank or wallet disbursement.</p>
                        </div>
                    </section>

                    <section id="owner-rules" class="scroll-mt-28">
                        <h2 class="text-2xl font-semibold tracking-tight text-slate-950">6. Rules for court owners and authorized staff</h2>
                        <p class="mt-4">Owners and staff must:</p>
                        <ul class="mt-3 list-disc space-y-2 pl-6">
                            <li>publish truthful venue, court, price, photo, schedule, promotion, contact, and payout information;</li>
                            <li>maintain availability and promptly handle valid bookings, closures, refunds, and customer questions;</li>
                            <li>use player information only for legitimate venue service, support, consented communication, and lawful recordkeeping;</li>
                            <li>respect marketing choices, suppression rules, and reasonable message frequency;</li>
                            <li>avoid misleading discounts, fake availability, fabricated reviews, discriminatory conduct, and unauthorized claims to another venue;</li>
                            <li>ensure staff access and connected Google or social accounts belong to authorized people; and</li>
                            <li>comply with safety, consumer, tax, licensing, employment, privacy, and other laws that apply to their independent operation.</li>
                        </ul>
                        <p class="mt-4">Owners control their venue operations and remain responsible for the facility and service delivered to players. FinACourt may review, hide, pause, or remove content or booking access that is inaccurate, unsafe, unlawful, disputed, or harmful to marketplace trust.</p>
                    </section>

                    <section id="acceptable-use" class="scroll-mt-28">
                        <h2 class="text-2xl font-semibold tracking-tight text-slate-950">7. Acceptable use</h2>
                        <p class="mt-4">You may not:</p>
                        <ul class="mt-3 list-disc space-y-2 pl-6">
                            <li>break the law, commit fraud, impersonate another person or business, or submit false ownership evidence;</li>
                            <li>interfere with booking integrity, attempt duplicate or unauthorized payments, abuse promotions, or manipulate referrals and commissions;</li>
                            <li>probe, bypass, or disrupt security, tenant isolation, permissions, rate limits, webhooks, or infrastructure;</li>
                            <li>scrape, copy, resell, or republish marketplace data at scale without written permission or a lawful basis;</li>
                            <li>upload malware, infringing content, private information without authority, or material that is abusive or deceptive; or</li>
                            <li>use FinACourt to send spam or target people using sensitive or unrelated personal information.</li>
                        </ul>
                    </section>

                    <section>
                        <h2 class="text-2xl font-semibold tracking-tight text-slate-950">8. Your content and FinACourt materials</h2>
                        <p class="mt-4">You keep ownership of content you lawfully submit. You give FinACourt a non-exclusive, worldwide, royalty-free license to host, reproduce, format, display, and distribute that content only as reasonably needed to operate, secure, promote, and improve the service. You confirm you have the rights and permissions needed for anything you upload or publish.</p>
                        <p class="mt-4">FinACourt's software, branding, design, and original materials remain owned by {{ $operatorName }} or its licensors. These terms do not grant permission to copy the application, use the FinACourt name or logo deceptively, or create a competing data product from protected marketplace content.</p>
                    </section>

                    <section id="service-boundaries" class="scroll-mt-28">
                        <h2 class="text-2xl font-semibold tracking-tight text-slate-950">9. Service availability and third parties</h2>
                        <p class="mt-4">We work to keep FinACourt accurate and available, but the service is provided on an “as available” basis. Internet outages, maintenance, venue changes, payment networks, map providers, social sign-in services, email delivery, devices, or events outside our reasonable control may cause delays or interruptions.</p>
                        <p class="mt-4">Third-party services such as PayMongo, Google, Facebook, Apple, map providers, and venue websites have their own terms and privacy practices. FinACourt is not responsible for a third party's independent service, but we will not use these boundaries to remove rights or remedies that applicable law does not allow us to exclude.</p>
                    </section>

                    <section>
                        <h2 class="text-2xl font-semibold tracking-tight text-slate-950">10. Disclaimers and responsibility</h2>
                        <p class="mt-4">FinACourt provides marketplace and management technology. Unless expressly stated, FinACourt does not own, operate, inspect, supervise, or guarantee independent sports venues. Players are responsible for deciding whether a venue and activity are suitable for them, and venue operators are responsible for facility condition, staffing, safety, and service.</p>
                        <p class="mt-4">To the fullest extent permitted by law, {{ $operatorName }} is not responsible for indirect, incidental, special, or consequential loss arising from use of the service. Nothing in these terms excludes responsibility that cannot legally be excluded, including applicable consumer rights. Each party remains responsible for loss caused by its own fraud, unlawful conduct, or breach of these terms.</p>
                    </section>

                    <section>
                        <h2 class="text-2xl font-semibold tracking-tight text-slate-950">11. Suspension and ending use</h2>
                        <p class="mt-4">You may stop using FinACourt at any time. We may limit, suspend, or close access when reasonably necessary for security, fraud, nonpayment, legal compliance, marketplace safety, prolonged inactivity, or a serious or repeated breach. Where practical and safe, we will provide notice or an opportunity to address the issue.</p>
                        <p class="mt-4">Ending an account does not erase obligations, confirmed bookings, payment records, refunds, payouts, disputes, audit history, or provisions that reasonably need to continue.</p>
                    </section>

                    <section>
                        <h2 class="text-2xl font-semibold tracking-tight text-slate-950">12. Changes and governing law</h2>
                        <p class="mt-4">We may revise these terms as the service develops. We will update the effective date and provide additional notice for material changes when required. Changes do not silently rewrite completed booking or payment records.</p>
                        <p class="mt-4">These terms are governed by the laws of the Republic of the Philippines, without limiting mandatory rights that apply based on your location. Before starting formal proceedings, please contact us so we can try to resolve the concern fairly.</p>
                    </section>

                    <section id="contact-us" class="scroll-mt-28 rounded-2xl bg-slate-50 p-5 sm:p-6">
                        <h2 class="text-2xl font-semibold tracking-tight text-slate-950">13. Contact us</h2>
                        <p class="mt-3">Questions about these terms can be sent to <a class="font-semibold text-court-700 underline decoration-court-200 underline-offset-4" href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>.</p>
                    </section>
                </div>
            </article>
        </div>
    </section>
@endsection
