@php
    $iconName = (string) ($name ?? 'sport');
    $iconClass = (string) ($class ?? 'size-5');
@endphp

<svg
    data-icon="{{ $iconName }}"
    viewBox="0 0 24 24"
    class="{{ $iconClass }}"
    fill="none"
    stroke="currentColor"
    stroke-width="1.8"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
>
    @switch($iconName)
        @case('search')
            <circle cx="11" cy="11" r="7" />
            <path d="m20 20-4-4" />
            @break

        @case('location')
            <path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z" />
            <circle cx="12" cy="10" r="2.5" />
            @break

        @case('calendar')
            <rect x="3" y="5" width="18" height="16" rx="2" />
            <path d="M16 3v4M8 3v4M3 10h18M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01" />
            @break

        @case('clock')
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7v5l3 2" />
            @break

        @case('check-circle')
            <circle cx="12" cy="12" r="9" />
            <path d="m8 12 2.5 2.5L16.5 9" />
            @break

        @case('arrow-right')
            <path d="M5 12h14M14 7l5 5-5 5" />
            @break

        @case('chevron-right')
            <path d="m9 6 6 6-6 6" />
            @break

        @case('chevron-left')
            <path d="m15 6-6 6 6 6" />
            @break

        @case('grid-dots')
            <circle cx="6" cy="6" r="1" fill="currentColor" stroke="none" />
            <circle cx="12" cy="6" r="1" fill="currentColor" stroke="none" />
            <circle cx="18" cy="6" r="1" fill="currentColor" stroke="none" />
            <circle cx="6" cy="12" r="1" fill="currentColor" stroke="none" />
            <circle cx="12" cy="12" r="1" fill="currentColor" stroke="none" />
            <circle cx="18" cy="12" r="1" fill="currentColor" stroke="none" />
            <circle cx="6" cy="18" r="1" fill="currentColor" stroke="none" />
            <circle cx="12" cy="18" r="1" fill="currentColor" stroke="none" />
            <circle cx="18" cy="18" r="1" fill="currentColor" stroke="none" />
            @break

        @case('share')
            <circle cx="18" cy="5" r="2.5" />
            <circle cx="6" cy="12" r="2.5" />
            <circle cx="18" cy="19" r="2.5" />
            <path d="m8.2 10.8 7.6-4.6M8.2 13.2l7.6 4.6" />
            @break

        @case('court')
            <rect x="3" y="4" width="18" height="16" rx="2" />
            <path d="M12 4v16M3 12h18M8 4v4M16 16v4" />
            @break

        @case('tag')
            <path d="M20 13 13 20 4 11V4h7l9 9Z" />
            <circle cx="8.5" cy="8.5" r="1" />
            @break

        @case('user')
            <circle cx="12" cy="8" r="4" />
            <path d="M4 21a8 8 0 0 1 16 0" />
            @break

        @case('verified')
            <path d="m12 3 2.2 1.5 2.7-.1.8 2.6 2.2 1.6-.9 2.5.9 2.5-2.2 1.6-.8 2.6-2.7-.1L12 21l-2.2-1.5-2.7.1-.8-2.6-2.2-1.6.9-2.5-.9-2.5L6.3 7l.8-2.6 2.7.1L12 3Z" />
            <path d="m8.5 12 2.2 2.2 4.8-4.8" />
            @break

        @case('sport-badminton')
            <path d="m8 4 8 8M5.5 6.5l4-4M7 9l4-4M10.5 14.5l5-5" />
            <path d="m14.5 13.5 3 3-4.8 4.2-2.4-2.4 4.2-4.8Z" />
            <path d="m13 15 2.5 2.5M11.7 16.8l2 2" />
            @break

        @case('sport-basketball')
            <circle cx="12" cy="12" r="9" />
            <path d="M3.3 10.2c5.3.2 9.2 4.1 9.5 9.8M20.7 13.8c-5.3-.2-9.2-4.1-9.5-9.8M4.3 17.2 19.7 6.8M6.8 4.3l10.4 15.4" />
            @break

        @case('sport-futsal')
        @case('sport-football')
        @case('sport-soccer')
            <circle cx="12" cy="12" r="9" />
            <path d="m12 8 3 2.2-1.1 3.5h-3.8L9 10.2 12 8ZM12 8V3M15 10.2l4.6-1.5M13.9 13.7l2.8 3.8M10.1 13.7l-2.8 3.8M9 10.2 4.4 8.7M7.3 17.5l-2.2-.2M16.7 17.5l2.2-.2" />
            @break

        @case('sport-pickleball')
            <path d="M14.7 4.3a5.5 5.5 0 0 1 0 7.8l-2.6 2.6-3.9-3.9 2.6-2.6a5.5 5.5 0 0 1 3.9-3.9Z" />
            <path d="m9.5 13.5-5.2 5.2a1.4 1.4 0 0 0 2 2l5.2-5.2M13 7h.01M16 8.5h.01M13.5 10.5h.01" />
            <circle cx="19" cy="17" r="2" />
            @break

        @case('sport-tennis')
            <circle cx="12" cy="12" r="9" />
            <path d="M5.7 5.7c4.4 2 8.6 6.2 12.6 12.6M18.3 5.7c-4.4 2-8.6 6.2-12.6 12.6" />
            @break

        @case('sport-volleyball')
            <circle cx="12" cy="12" r="9" />
            <path d="M12 3c2.2 2.3 3.5 4.8 3.8 7.5M20.6 9.5c-3.1-.8-5.9-.6-8.4.6M17.7 18.3c.9-3 .7-5.8-.5-8.3M11.6 21c-2.2-2.3-3.4-4.8-3.7-7.5M3.4 14.4c3.1.8 5.9.6 8.4-.6M6.3 5.7c-.9 3-.7 5.8.5 8.3" />
            @break

        @default
            <circle cx="12" cy="12" r="9" />
            <path d="M5.8 5.8c3.4 2.2 6.2 5 8.4 8.4M18.2 18.2c-3.4-2.2-6.2-5-8.4-8.4M5.8 18.2c2.2-3.4 5-6.2 8.4-8.4M18.2 5.8c-2.2 3.4-5 6.2-8.4 8.4" />
    @endswitch
</svg>
