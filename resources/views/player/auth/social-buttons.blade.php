@if (($socialProviders ?? []) !== [])
    <div class="mt-7 grid gap-3 sm:grid-cols-2">
        @foreach ($socialProviders as $provider)
            <a href="{{ $provider['url'] }}" class="flex min-h-12 items-center justify-center gap-3 rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-800 shadow-sm transition hover:-translate-y-0.5 hover:border-court-400 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-court-600 focus:ring-offset-2">
                @if ($provider['key'] === 'google')
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-5"><path fill="#4285F4" d="M21.6 12.23c0-.71-.06-1.4-.18-2.07H12v3.91h5.38a4.6 4.6 0 0 1-2 3.02v2.54h3.24c1.9-1.75 2.98-4.33 2.98-7.4Z"/><path fill="#34A853" d="M12 22c2.7 0 4.98-.9 6.64-2.43l-3.24-2.54c-.9.6-2.05.96-3.4.96-2.61 0-4.82-1.76-5.61-4.13H3.04v2.62A10 10 0 0 0 12 22Z"/><path fill="#FBBC05" d="M6.39 13.86A6 6 0 0 1 6.08 12c0-.65.11-1.28.31-1.86V7.52H3.04A10 10 0 0 0 2 12c0 1.61.39 3.13 1.04 4.48l3.35-2.62Z"/><path fill="#EA4335" d="M12 6.01c1.47 0 2.79.5 3.83 1.5l2.87-2.87A9.63 9.63 0 0 0 12 2a10 10 0 0 0-8.96 5.52l3.35 2.62C7.18 7.77 9.39 6.01 12 6.01Z"/></svg>
                @elseif ($provider['key'] === 'facebook')
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-5 fill-[#1877F2]"><path d="M24 12a12 12 0 1 0-13.88 11.85v-8.47H7.08V12h3.04V9.42c0-3.01 1.8-4.67 4.53-4.67 1.31 0 2.68.23 2.68.23v2.95h-1.51c-1.49 0-1.95.92-1.95 1.87V12h3.32l-.53 3.38h-2.79v8.47A12 12 0 0 0 24 12Z"/></svg>
                @else
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-5 fill-slate-950"><path d="M17.05 20.28c-.98.95-2.05.8-3.08.35-1.09-.46-2.09-.48-3.24 0-1.44.62-2.2.44-3.06-.35C2.79 15.25 3.51 7.59 9.05 7.31c1.35.07 2.29.74 3.08.79 1.18-.24 2.31-.93 3.57-.84 1.51.12 2.65.72 3.4 1.8-3.12 1.87-2.38 5.98.48 7.13-.57 1.5-1.31 2.99-2.53 4.1ZM12.03 7.25C11.88 5.02 13.69 3.18 15.77 3c.29 2.58-2.34 4.5-3.74 4.25Z"/></svg>
                @endif
                Continue with {{ $provider['label'] }}
            </a>
        @endforeach
    </div>
    <div class="my-6 flex items-center gap-3 text-xs font-medium uppercase tracking-[0.14em] text-slate-400"><span class="h-px flex-1 bg-slate-200"></span>or use email<span class="h-px flex-1 bg-slate-200"></span></div>
@endif
