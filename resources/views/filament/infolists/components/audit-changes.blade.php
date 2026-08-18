<div>
    @php
        $record = $getRecord();
        $changesList = [];

        if ($record->children()->exists()) {
            foreach ($record->children as $child) {
                $changesList[] = [
                    'label' => str($child->action->value)->replace('_', ' ')->title(),
                    'old' => $child->old_values ?? [],
                    'new' => $child->new_values ?? [],
                ];
            }
        } else if (!empty($record->old_values) || !empty($record->new_values)) {
            $changesList[] = [
                'label' => 'Perubahan',
                'old' => $record->old_values ?? [],
                'new' => $record->new_values ?? [],
            ];
        }
    @endphp

    @if(empty($changesList))
        <span class="text-gray-500 italic">Tidak ada perubahan field</span>
    @else
        <div class="space-y-6">
            @foreach($changesList as $changeItem)
                @php
                    $old = $changeItem['old'];
                    $new = $changeItem['new'];
                    $keys = array_unique(array_merge(array_keys($old), array_keys($new)));
                @endphp
                
                <div class="space-y-2">
                    <h4 class="font-medium text-sm text-gray-700 dark:text-gray-300">{{ $changeItem['label'] }}</h4>
                    
                    @if(empty($keys))
                        <span class="text-xs text-gray-400 italic">Tidak ada detail perubahan</span>
                    @else
                        <div class="space-y-4">
                            @foreach($keys as $key)
                                @php
                                    $oldVal = $old[$key] ?? null;
                                    $newVal = $new[$key] ?? null;
                                    if ($oldVal === $newVal) continue;
                                    
                                    if (is_array($oldVal)) $oldVal = json_encode($oldVal);
                                    if (is_array($newVal)) $newVal = json_encode($newVal);
                                @endphp
                                <div class="border rounded-lg overflow-hidden bg-white dark:bg-gray-900 border-gray-200 dark:border-white/10">
                                    <div class="bg-gray-50 dark:bg-white/5 px-4 py-2 border-b border-gray-200 dark:border-white/10">
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ str($key)->replace('_', ' ')->title() }}</span>
                                    </div>
                                    <div class="p-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <span class="text-xs text-gray-500 uppercase tracking-wider block mb-1">Sebelum</span>
                                            <div class="text-sm text-gray-700 dark:text-gray-300 bg-red-50 dark:bg-red-500/10 p-2 rounded line-through decoration-red-500">
                                                {{ $oldVal === null || $oldVal === '' ? '-' : $oldVal }}
                                            </div>
                                        </div>
                                        <div>
                                            <span class="text-xs text-gray-500 uppercase tracking-wider block mb-1">Sesudah</span>
                                            <div class="text-sm text-gray-900 dark:text-white bg-green-50 dark:bg-green-500/10 p-2 rounded">
                                                {{ $newVal === null || $newVal === '' ? '-' : $newVal }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
