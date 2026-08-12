@props([
    'position' => null,
])

@php
    use BezhanSalleh\PanelSwitch\PanelSwitch;
    use Filament\Actions\Action;
    use Filament\Enums\UserMenuPosition;
    use Illuminate\Support\Arr;

    $panelSwitch = PanelSwitch::make();
    $panelSwitchPanels = $panelSwitch->getPanels();
    $panelSwitchCurrentPanel = $panelSwitch->getCurrentPanel();
    $panelSwitchLabels = $panelSwitch->getLabels();
    $panelSwitchIcons = $panelSwitch->getIcons();

    $user = filament()->auth()->user();

    $userName = filament()->getUserName($user);
    $userEmail = strtolower($user?->email ?? '');

    $items = $this->getUserMenuItems();

    $itemsBeforeAndAfterThemeSwitcher = collect($items)
        ->groupBy(fn (Action $item): bool => $item->getSort() < 0, preserveKeys: true)
        ->all();
    $itemsBeforeThemeSwitcher = $itemsBeforeAndAfterThemeSwitcher[true] ?? collect();
    $itemsAfterThemeSwitcher = $itemsBeforeAndAfterThemeSwitcher[false] ?? collect();

    $hasProfileHeader = $itemsBeforeThemeSwitcher->has('profile') &&
        blank(($item = Arr::first($itemsBeforeThemeSwitcher))->getUrl()) &&
        (! $item->hasAction());

    if ($itemsBeforeThemeSwitcher->has('profile')) {
        $itemsBeforeThemeSwitcher = $itemsBeforeThemeSwitcher->prepend($itemsBeforeThemeSwitcher->pull('profile'), 'profile');
    }

    $multiGroupAfterTheme = $this->hasMultipleUserMenuItemGroups();
    $afterThemeItemGroups = $multiGroupAfterTheme ? $this->getUserMenuItemGroupsAfterTheme() : [];

    $position ??= filament()->getUserMenuPosition();

    $isSidebarCollapsibleOnDesktop = filament()->isSidebarCollapsibleOnDesktop();
@endphp

{{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_BEFORE) }}

<x-filament::dropdown
    :placement="($position === UserMenuPosition::Topbar) ? 'bottom-end' : 'top-end'"
    :teleport="$position === UserMenuPosition::Topbar"
    :attributes="
        \Filament\Support\prepare_inherited_attributes($attributes)
            ->class(['fi-user-menu'])
    "
>
    <x-slot name="trigger">
        @if ($position === UserMenuPosition::Topbar)
            <button
                aria-label="{{ __('filament-panels::layout.actions.open_user_menu.label') }}"
                type="button"
                class="flex items-center gap-x-3 focus:outline-none group rounded-lg p-1.5 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
            >
                {{-- Vertical Separator Line --}}
                <div class="h-6 w-px bg-gray-300 dark:bg-gray-700 me-1"></div>

                {{-- Topbar User Profile Display --}}
                <div class="flex flex-col items-end leading-tight text-right hidden sm:flex">
                    <span class="font-bold text-xs sm:text-sm tracking-wide text-gray-900 dark:text-gray-100 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                        {{ $userName }}
                    </span>
                    @if ($userEmail)
                        <span class="text-xs text-primary-600 dark:text-primary-400 font-medium">
                            {{ $userEmail }}
                        </span>
                    @endif
                </div>

                <x-filament-panels::avatar.user :user="$user" loading="lazy" class="ring-2 ring-primary-500/20 shadow-sm" />
            </button>
        @else
            <button
                aria-label="{{ filled($userName) ? $userName : __('filament-panels::layout.actions.open_user_menu.label') }}"
                type="button"
                class="fi-user-menu-trigger"
            >
                <x-filament-panels::avatar.user :user="$user" loading="lazy" />

                <span
                    @if ($isSidebarCollapsibleOnDesktop)
                        x-show="$store.sidebar.isOpen"
                    @endif
                    class="fi-user-menu-trigger-text"
                >
                    {{ $userName }}
                </span>

                {{
                    \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::ChevronUp, alias: \Filament\View\PanelsIconAlias::USER_MENU_TOGGLE_BUTTON, attributes: new \Filament\Support\View\ComponentAttributeBag([
                        'x-show' => $isSidebarCollapsibleOnDesktop ? '$store.sidebar.isOpen' : null,
                    ]))
                }}
            </button>
        @endif
    </x-slot>

    @if ($hasProfileHeader)
        @php
            $item = $itemsBeforeThemeSwitcher['profile'];
            $itemColor = $item->getColor();
            $itemIcon = $item->getIcon() ?? 'heroicon-o-user-circle';
            $profileUrl = filament()->hasProfile() ? filament()->getProfileUrl() : null;

            unset($itemsBeforeThemeSwitcher['profile']);
        @endphp

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_PROFILE_BEFORE) }}

        @if ($profileUrl)
            <x-filament::dropdown.list>
                <x-filament::dropdown.list.item
                    :color="$itemColor"
                    :icon="$itemIcon"
                    :href="$profileUrl"
                    tag="a"
                >
                    {{ $item->getLabel() }}
                </x-filament::dropdown.list.item>
            </x-filament::dropdown.list>
        @else
            <x-filament::dropdown.header :color="$itemColor" :icon="$itemIcon">
                {{ $item->getLabel() }}
            </x-filament::dropdown.header>
        @endif

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_PROFILE_AFTER) }}
    @endif

    @if ($itemsBeforeThemeSwitcher->isNotEmpty())
        <x-filament::dropdown.list>
            @foreach ($itemsBeforeThemeSwitcher as $key => $item)
                @if ($key === 'profile')
                    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_PROFILE_BEFORE) }}

                    {{ $item }}

                    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_PROFILE_AFTER) }}
                @else
                    {{ $item }}
                @endif
            @endforeach
        </x-filament::dropdown.list>
    @endif

    @if (filament()->hasDarkMode() && (! filament()->hasDarkModeForced()) && filament()->hasThemeSwitcher())
        <x-filament::dropdown.list>
            <x-filament-panels::theme-switcher />
        </x-filament::dropdown.list>
    @endif

    @if ($panelSwitch->isVisible())
        <x-filament::dropdown.list>
            @foreach ($panelSwitchPanels as $id => $url)
                @php
                    $isCurrentPanel = $id === $panelSwitchCurrentPanel->getId();
                    $panelLabel = $panelSwitchLabels[$id] ?? str($id)->ucfirst();
                    $panelIcon = $panelSwitchIcons[$id] ?? 'heroicon-o-square-2-stack';
                @endphp

                @if ($isCurrentPanel)
                    <x-filament::dropdown.list.item
                        :icon="$panelIcon"
                        icon-color="primary"
                        color="primary"
                        tag="div"
                        class="pointer-events-none"
                    >
                        <span class="flex items-center justify-between gap-x-2 w-full">
                            <span class="text-primary-600 dark:text-primary-400">{{ $panelLabel }}</span>
                            <x-filament::icon icon="heroicon-m-check" class="h-4 w-4 text-primary-600 dark:text-primary-400" />
                        </span>
                    </x-filament::dropdown.list.item>
                @else
                    <x-filament::dropdown.list.item :href="$url" :icon="$panelIcon" tag="a">
                        {{ $panelLabel }}
                    </x-filament::dropdown.list.item>
                @endif
            @endforeach
        </x-filament::dropdown.list>
    @endif

    @if ($multiGroupAfterTheme && $afterThemeItemGroups !== [])
        @foreach ($afterThemeItemGroups as $afterThemeGroup)
            <x-filament::dropdown.list>
                @foreach ($afterThemeGroup as $key => $item)
                    @if ($key === 'profile')
                        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_PROFILE_BEFORE) }}

                        {{ $item }}

                        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_PROFILE_AFTER) }}
                    @else
                        {{ $item }}
                    @endif
                @endforeach
            </x-filament::dropdown.list>
        @endforeach
    @elseif ($itemsAfterThemeSwitcher->isNotEmpty())
        <x-filament::dropdown.list>
            @foreach ($itemsAfterThemeSwitcher as $key => $item)
                @if ($key === 'profile')
                    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_PROFILE_BEFORE) }}

                    {{ $item }}

                    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_PROFILE_AFTER) }}
                @else
                    {{ $item }}
                @endif
            @endforeach
        </x-filament::dropdown.list>
    @endif
</x-filament::dropdown>

{{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_AFTER) }}
