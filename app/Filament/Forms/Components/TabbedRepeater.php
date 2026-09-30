<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Repeater;
use Filament\Support\View\ComponentAttributeBag as FilamentComponentAttributeBag;
use Illuminate\Support\Js;

/**
 * Repeater yang tiap item-nya tampil sebagai tab (hanya satu item terbuka),
 * supaya form dengan banyak baris tidak memanjang ke bawah. Tombol tambah
 * ada di kanan baris tab.
 *
 * Tab aktif disimpan di Alpine. Tab baru hasil morph Livewire langsung
 * diaktifkan lewat x-init (flag `ready` mencegah efek itu saat load awal).
 * Error validasi di tab tersembunyi tetap ketemu: handler Filament
 * men-dispatch `expand` ke leluhur field yang error, panel menangkapnya.
 */
class TabbedRepeater extends Repeater
{
    public function toEmbeddedHtml(): string
    {
        $items = $this->getItems();
        $itemKeys = array_keys($items);

        $addAction = $this->getAction($this->getAddActionName());
        $deleteAction = $this->getAction($this->getDeleteActionName());

        $isAddable = $this->isAddable();
        $isDeletable = $this->isDeletable();

        $id = $this->getId();

        $outerAttributes = (new FilamentComponentAttributeBag)
            ->merge($this->getExtraAttributes(), escape: false)
            ->merge([
                'aria-labelledby' => "{$id}-label",
                'id' => $id,
                'role' => 'group',
            ], escape: false)
            ->class(['fi-fo-repeater', 'flex', 'flex-col', 'gap-4']);

        ob_start(); ?>

        <div
            wire:ignore.self
            x-data="{ active: <?= e(Js::from($itemKeys[0] ?? null)) ?>, ready: false }"
            x-init="$nextTick(() => ready = true)"
            <?= $outerAttributes->toHtml() ?>
        >
            <div class="flex flex-wrap items-end justify-between gap-3 border-b border-gray-200 dark:border-white/10">
                <div role="tablist" class="-mb-px flex flex-wrap gap-1">
                    <?php foreach ($itemKeys as $index => $itemKey) { ?>
                        <?php $itemLabel = $this->getItemLabel($itemKey, $index); ?>
                        <button
                            type="button"
                            role="tab"
                            wire:key="<?= e($items[$itemKey]->getLivewireKey()) ?>.tab"
                            x-init="ready && (active = <?= e(Js::from($itemKey)) ?>)"
                            x-on:click="active = <?= e(Js::from($itemKey)) ?>"
                            x-bind:aria-selected="active === <?= e(Js::from($itemKey)) ?>"
                            x-bind:class="active === <?= e(Js::from($itemKey)) ?>
                                ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400'
                                : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                            class="inline-flex max-w-xs items-center gap-2 border-b-2 px-3 py-2 text-sm font-medium transition"
                        >
                            <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-gray-100 px-1.5 text-xs text-gray-600 dark:bg-white/10 dark:text-gray-300"><?= e($index + 1) ?></span>
                            <span class="truncate"><?= e($itemLabel) ?></span>
                        </button>
                    <?php } ?>
                </div>

                <?php if ($isAddable && $addAction->isVisible()) { ?>
                    <div class="pb-2">
                        <?= $addAction->toHtml() ?>
                    </div>
                <?php } ?>
            </div>

            <?php foreach ($itemKeys as $index => $itemKey) { ?>
                <?php
                    $item = $items[$itemKey];
                $itemLabel = $this->getItemLabel($itemKey, $index);
                $itemDeleteAction = $deleteAction(['item' => $itemKey]);
                $deleteActionIsVisible = $isDeletable && $itemDeleteAction->isVisible();
                // Tab tetangga yang diaktifkan kalau tab ini dihapus.
                $fallbackKey = $itemKeys[$index + 1] ?? $itemKeys[$index - 1] ?? null;
                ?>

                <div
                    role="tabpanel"
                    wire:key="<?= e($item->getLivewireKey()) ?>.item"
                    x-show="active === <?= e(Js::from($itemKey)) ?>"
                    x-on:expand="active = <?= e(Js::from($itemKey)) ?>"
                    x-cloak
                    class="fi-fo-repeater-item rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10"
                >
                    <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 dark:border-white/5">
                        <h4 class="truncate text-sm font-medium text-gray-950 dark:text-white"><?= e($itemLabel) ?></h4>

                        <?php if ($deleteActionIsVisible) { ?>
                            <div x-on:click="active = <?= e(Js::from($fallbackKey)) ?>">
                                <?= $itemDeleteAction->toHtml() ?>
                            </div>
                        <?php } ?>
                    </div>

                    <div class="p-4">
                        <?= $item->toHtml() ?>
                    </div>
                </div>
            <?php } ?>
        </div>

        <?php return $this->wrapEmbeddedHtml(ob_get_clean(), labelTag: 'div');
    }
}
