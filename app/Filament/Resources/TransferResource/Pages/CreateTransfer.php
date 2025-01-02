<?php

namespace App\Filament\Resources\TransferResource\Pages;

use App\Filament\Resources\TransferResource;
use App\Models\SaleItem;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateTransfer extends CreateRecord
{
    protected static bool $canCreateAnother = false;
    protected static string $resource = TransferResource::class;
    public function mutateFormDataBeforeCreate(array $data): array
    {
        $data['wherehouse_id'] = auth()->user()->employee->branch_id;
        $data['is_order'] = false;
        $data['is_invoiced_order'] = false;
        return $data;

    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Iniciar Traslado')
                ->color('success')
                ->icon('heroicon-o-check')
                ->action('create')
                ->extraAttributes([
                    'class' => 'alig', // Tailwind para ajustar el margen alinearlo a la derecha

                ]),

            Action::make('cancelSale')
                ->label('Cancelar proceso')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Confirmación!!')
                ->modalSubheading('¿Estás seguro de que deseas cancelar esta venta? Esta acción no se puede deshacer.')
                ->modalButton('Sí, cancelar venta')
                ->action(function (Actions\DeleteAction $delete) {
                    if (!$this->record) {
                        $this->redirect(static::getResource()::getUrl('index'));
                        return;
                    }

                    if ($this->record->is_order) {
                        Notification::make()
                            ->title('Error al anular venta')
                            ->body('No se puede cancelar una orden')
                            ->danger()
                            ->send();
                        return;
                    }

// Elimina la venta y los elementos relacionados
                    SaleItem::where('sale_id', $this->record->id)->delete();
                    $this->record->delete();

// Redirige al índice
                    $this->redirect(static::getResource()::getUrl('index'));

                }),
        ];
    }
}
