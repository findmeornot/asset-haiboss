<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ApprovalRequestResource\Pages;
use App\Models\ApprovalRequest;
use App\Models\Asset;
use Filament\Schemas\Schema;
use Filament\Forms\Components;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ApprovalRequestResource extends Resource
{
    protected static ?string $model = ApprovalRequest::class;

    public static function getNavigationIcon(): string | \Illuminate\Contracts\Support\Htmlable | null
    {
        return 'heroicon-o-check-badge';
    }
    
    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        return 'System Administration';
    }

    public static function getModelLabel(): string
    {
        return 'Approval Request';
    }
    
    public static function canCreate(): bool { return false; }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Components\TextInput::make('request_type')->label('Tipe Request')->disabled(),
                Components\Select::make('requested_by')->relationship('requestedBy', 'name')->label('Pemohon')->disabled(),
                Components\Textarea::make('reason')->label('Alasan')->disabled(),
                Components\KeyValue::make('payload')->label('Detail Perubahan')->formatStateUsing(fn($state) => json_decode($state, true) ?? [])->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Tanggal')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('request_type')->label('Tipe')->badge(),
                Tables\Columns\TextColumn::make('requestedBy.name')->label('Pemohon'),
                Tables\Columns\TextColumn::make('status')->label('Status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('reason')->label('Alasan')->limit(30),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                \Filament\Actions\Action::make('approve')
                    ->label('Setujui')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (ApprovalRequest $record) => $record->status === 'pending' && Auth::user()->hasPermissionTo('status.approve'))
                    ->requiresConfirmation()
                    ->action(function (ApprovalRequest $record) {
                        try {
                            \Illuminate\Support\Facades\DB::transaction(function () use ($record) {
                                $lockedRecord = ApprovalRequest::where('id', $record->id)->lockForUpdate()->first();
                                if ($lockedRecord->status !== 'pending') {
                                    throw new \Exception('Pengajuan ini sudah diproses.');
                                }
                                
                                $lockedRecord->update([
                                    'status' => 'approved',
                                    'approved_by' => Auth::id(),
                                    'approved_at' => now(),
                                ]);
                                
                                $payload = json_decode($lockedRecord->payload, true);
                                if ($lockedRecord->request_type === 'status_change' && isset($payload['asset_id']) && isset($payload['new_status'])) {
                                    $asset = Asset::where('id', $payload['asset_id'])->lockForUpdate()->first();
                                    if ($asset) {
                                        request()->merge(['status_change_reason' => 'Approved: ' . $lockedRecord->reason]);
                                        $asset->update(['status' => $payload['new_status']]);
                                    }
                                }
                            });
                            \Filament\Notifications\Notification::make()->title('Pengajuan Disetujui')->success()->send();
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()->title('Gagal Memproses')->body($e->getMessage())->danger()->send();
                        }
                    }),
                \Filament\Actions\Action::make('reject')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (ApprovalRequest $record) => $record->status === 'pending' && Auth::user()->hasPermissionTo('status.approve'))
                    ->requiresConfirmation()
                    ->action(function (ApprovalRequest $record) {
                        try {
                            \Illuminate\Support\Facades\DB::transaction(function () use ($record) {
                                $lockedRecord = ApprovalRequest::where('id', $record->id)->lockForUpdate()->first();
                                if ($lockedRecord->status !== 'pending') {
                                    throw new \Exception('Pengajuan ini sudah diproses.');
                                }
                                $lockedRecord->update([
                                    'status' => 'rejected',
                                    'approved_by' => Auth::id(),
                                    'approved_at' => now(),
                                ]);
                            });
                            \Filament\Notifications\Notification::make()->title('Pengajuan Ditolak')->success()->send();
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()->title('Gagal Memproses')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListApprovalRequests::route('/'),
            'view' => Pages\ViewApprovalRequest::route('/{record}'),
        ];
    }
}
