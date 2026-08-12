@php
    $user = auth()->user();
    $name = strtoupper($user?->name ?? 'USER');
    $email = strtolower($user?->email ?? '');

    $roleName = strtoupper($user?->roles()->first()?->name ?? 'USER');

    $words = explode(' ', trim($name));
    $initials = '';
    if (count($words) >= 2) {
        $initials = mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1);
    } elseif (count($words) === 1 && strlen($words[0]) > 0) {
        $initials = mb_substr($words[0], 0, 2);
    } else {
        $initials = 'US';
    }

    $profileUrl = filament()->hasProfile() ? filament()->getProfileUrl() : null;
    $avatarUrl = $user?->getFilamentAvatarUrl();
@endphp

<div class="mt-auto border-t border-gray-200 dark:border-gray-800 px-4 py-3 flex flex-col gap-y-2">
    <!-- Role Label (shown when sidebar is expanded) -->
    <div
        x-show="$store.sidebar.isOpen"
        class="text-[10px] font-bold tracking-wider text-gray-400 dark:text-gray-500 uppercase text-center"
    >
        {{ $roleName }}
    </div>

    <!-- User Profile Box -->
    <a
        @if($profileUrl) href="{{ $profileUrl }}" @endif
        class="flex items-center gap-x-3 p-1.5 -mx-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800/60 transition-colors group cursor-pointer"
        title="Edit Profile"
    >
        <!-- Avatar Circle -->
        @if ($avatarUrl)
            <img
                src="{{ $avatarUrl }}"
                alt="{{ $name }}"
                class="w-9 h-9 rounded-full object-cover shadow-sm shrink-0 ring-2 ring-primary-500/20 group-hover:ring-primary-500/50 transition-all"
            />
        @else
            <div class="w-9 h-9 rounded-full bg-primary-600 text-white font-bold text-xs flex items-center justify-center shadow-sm shrink-0 ring-2 ring-primary-500/20 group-hover:ring-primary-500/50 transition-all">
                {{ strtoupper($initials) }}
            </div>
        @endif

        <!-- Name and Email (hidden when sidebar is collapsed) -->
        <div
            x-show="$store.sidebar.isOpen"
            class="flex flex-col min-w-0 overflow-hidden leading-tight"
        >
            <span class="font-bold text-xs sm:text-sm text-gray-900 dark:text-gray-100 group-hover:text-primary-600 dark:group-hover:text-primary-400 truncate transition-colors">
                {{ $name }}
            </span>
            <span class="text-xs text-primary-600 dark:text-primary-400 font-medium truncate">
                {{ $email }}
            </span>
        </div>
    </a>
</div>
