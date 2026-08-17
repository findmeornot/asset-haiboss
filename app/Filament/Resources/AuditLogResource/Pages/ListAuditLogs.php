<?php
namespace App\Filament\Resources\AuditLogResource\Pages;
use App\Filament\Resources\AuditLogResource;
use Filament\Resources\Pages\ListRecords;
class ListAuditLogs extends ListRecords
{
    protected static string $resource = AuditLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('export_excel')
                ->label('Export Excel')
                ->color('success')
                ->icon('heroicon-o-document-arrow-down')
                ->authorize(fn () => \Illuminate\Support\Facades\Auth::user()?->hasRole('Superadmin') ?? false)
                ->action(function () {
                    $query = clone $this->getFilteredTableQuery();
                    $count = $query->count();
                    
                    \App\Services\AuditLogger::log(
                        \App\Enums\AuditAction::EXPORT_EXCEL,
                        null, null, null, null, null, null,
                        [
                            'format' => 'xlsx',
                            'record_count' => $count,
                            'ip_address' => request()->ip(),
                            'user_agent' => request()->userAgent(),
                            'filters' => $this->tableFilters ?? [],
                            'search' => $this->tableSearchQuery ?? '',
                        ]
                    );

                    return response()->streamDownload(function () use ($query) {
                        $options = new \OpenSpout\Writer\XLSX\Options();
                        $writer = new \OpenSpout\Writer\XLSX\Writer($options);
                        $writer->openToFile('php://output');
                        
                        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                            'Waktu', 'Pengguna (Email)', 'Aktivitas', 'Objek / Ringkasan', 'Batch UUID', 'IP Address', 'Metadata'
                        ]));

                        foreach ($query->lazy(500) as $record) {
                            $objectSummary = '-';
                            if (isset($record->metadata['snapshot']['inventory_number'])) {
                                $objectSummary = implode(' - ', array_filter([$record->metadata['snapshot']['inventory_number'], $record->metadata['snapshot']['asset_name'] ?? null]));
                            } else {
                                $objectSummary = $record->auditable_type ? class_basename($record->auditable_type) . ' #' . $record->auditable_id : '-';
                            }

                            $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                $record->created_at->format('Y-m-d H:i:s'),
                                $record->user?->name ?? ($record->metadata['credentials']['email'] ?? 'System/Unknown'),
                                str($record->action->value)->replace('_', ' ')->title()->toString(),
                                $objectSummary,
                                $record->batch_uuid,
                                $record->metadata['ip_address'] ?? '-',
                                json_encode($record->metadata)
                            ]));
                        }
                        $writer->close();
                    }, 'Audit_Trail_' . date('Y-m-d_H-i') . '.xlsx');
                }),

            \Filament\Actions\Action::make('export_pdf')
                ->label('Export PDF')
                ->color('danger')
                ->icon('heroicon-o-document-text')
                ->authorize(fn () => \Illuminate\Support\Facades\Auth::user()?->hasRole('Superadmin') ?? false)
                ->action(function () {
                    $query = clone $this->getFilteredTableQuery();
                    $count = $query->count();
                    
                    \App\Services\AuditLogger::log(
                        \App\Enums\AuditAction::EXPORT_PDF,
                        null, null, null, null, null, null,
                        [
                            'format' => 'pdf',
                            'record_count' => $count,
                            'ip_address' => request()->ip(),
                            'user_agent' => request()->userAgent(),
                            'filters' => $this->tableFilters ?? [],
                            'search' => $this->tableSearchQuery ?? '',
                        ]
                    );

                    // Prevent memory exhaustion
                    $records = $query->take(2000)->get();
                    $isFiltered = !empty($this->tableFilters) || !empty($this->tableSearchQuery);
                    
                    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.audit-pdf', [
                        'records' => $records,
                        'isFiltered' => $isFiltered,
                        'user' => \Illuminate\Support\Facades\Auth::user()
                    ]);

                    return response()->streamDownload(function () use ($pdf) {
                        echo $pdf->output();
                    }, 'audit-trail-'.now()->format('Y-m-d-His').'.pdf');
                }),
        ];
    }
}
