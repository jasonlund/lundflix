<?php

namespace App\Filament\Resources\Requests\RelationManagers;

use App\Enums\RequestItemStatus;
use App\Filament\Resources\Requests\Tables\RequestItemsTable;
use App\Models\Request;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class RequestItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Request Items';

    public function table(Table $table): Table
    {
        return RequestItemsTable::configure($table)
            ->groupedBulkActions([
                BulkAction::make('markFulfilled')
                    ->label('Mark as Fulfilled')
                    ->icon('lucide-circle-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Mark items as fulfilled')
                    ->modalDescription('Are you sure you want to mark the selected items as fulfilled?')
                    ->modalSubmitActionLabel('Mark as Fulfilled')
                    ->action(function (Collection $records): void {
                        $this->authorizeRecordsForStatusChange($records, RequestItemStatus::Fulfilled);

                        /** @var Request $request */
                        $request = $this->getOwnerRecord();
                        $recordIds = $records->pluck('id')->toArray();

                        $request->markItemsFulfilled($recordIds, auth()->id());

                        $this->dispatch('$refresh')->self();

                        Notification::make()
                            ->title('Items marked as fulfilled')
                            ->body(count($recordIds).' item(s) marked as fulfilled.')
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),

                BulkAction::make('markRejected')
                    ->label('Mark as Rejected')
                    ->icon('lucide-circle-x')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Mark items as rejected')
                    ->modalDescription('Are you sure you want to mark the selected items as rejected?')
                    ->modalSubmitActionLabel('Mark as Rejected')
                    ->action(function (Collection $records): void {
                        $this->authorizeRecordsForStatusChange($records, RequestItemStatus::Rejected);

                        /** @var Request $request */
                        $request = $this->getOwnerRecord();
                        $recordIds = $records->pluck('id')->toArray();

                        $request->markItemsRejected($recordIds, auth()->id());

                        $this->dispatch('$refresh')->self();

                        Notification::make()
                            ->title('Items marked as rejected')
                            ->body(count($recordIds).' item(s) marked as rejected.')
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),

                BulkAction::make('markNotFound')
                    ->label('Mark as Not Found')
                    ->icon('lucide-circle-help')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Mark items as not found')
                    ->modalDescription('Are you sure you want to mark the selected items as not found?')
                    ->modalSubmitActionLabel('Mark as Not Found')
                    ->action(function (Collection $records): void {
                        $this->authorizeRecordsForStatusChange($records, RequestItemStatus::NotFound);

                        /** @var Request $request */
                        $request = $this->getOwnerRecord();
                        $recordIds = $records->pluck('id')->toArray();

                        $request->markItemsNotFound($recordIds, auth()->id());

                        $this->dispatch('$refresh')->self();

                        Notification::make()
                            ->title('Items marked as not found')
                            ->body(count($recordIds).' item(s) marked as not found.')
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),

                BulkAction::make('markPending')
                    ->label('Mark as Pending')
                    ->icon('lucide-refresh-cw')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Mark items as pending')
                    ->modalDescription('Are you sure you want to mark the selected items as pending?')
                    ->modalSubmitActionLabel('Mark as Pending')
                    ->action(function (Collection $records): void {
                        $this->authorizeRecordsForStatusChange($records, RequestItemStatus::Pending);

                        /** @var Request $request */
                        $request = $this->getOwnerRecord();
                        $recordIds = $records->pluck('id')->toArray();

                        $request->markItemsPending($recordIds);

                        $this->dispatch('$refresh')->self();

                        Notification::make()
                            ->title('Items marked as pending')
                            ->body(count($recordIds).' item(s) marked as pending.')
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    /**
     * @throws \Filament\Support\Exceptions\Halt
     */
    private function authorizeRecordsForStatusChange(Collection $records, RequestItemStatus $newStatus): void
    {
        $user = auth()->user();

        $unauthorized = $records->filter(
            function ($record) use ($newStatus, $user): bool {
                if (! $user) {
                    return true;
                }

                return $user->cannot('update', [$record, $newStatus]);
            }
        );

        if ($unauthorized->isNotEmpty()) {
            Notification::make()
                ->title('Authorization failed')
                ->body('You are not authorized to change the status of '.$unauthorized->count().' selected item(s).')
                ->danger()
                ->send();

            throw new \Filament\Support\Exceptions\Halt;
        }
    }
}
