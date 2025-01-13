<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\SaleResource;
use App\Helpers\KardexHelper;
use App\Models\CashBox;
use App\Models\CashBoxCorrelative;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Provider;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Service\GetCashBoxOpenedService;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use http\Client;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\On;

class EditSale extends EditRecord
{
    protected static string $resource = SaleResource::class;

    public function getTitle(): string|Htmlable
    {
        return '';
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Finalizar Venta')
                ->color('primary')
                ->icon('heroicon-o-check')
                ->action('save')
                ->before(function (Actions\EditAction $action, Sale $record) {
                    if ($this->data['operation_condition_id'] == "1") {
                        $sale_total = isset($this->data['sale_total'])
                            ? doubleval($this->data['sale_total'])
                            : 0.0;
                        $cash = isset($this->data['cash'])
                            ? doubleval($this->data['cash'])
                            : 0.0;

                        if ($cash < $sale_total) {
                            Notification::make('No se puede finalizar la venta')
                                ->title('Error al finalizar venta')
                                ->body('El monto en efectivo es menor al total de la venta')
                                ->danger()
                                ->send();
                            $action->halt();

                        }
                    }
                })
                ->extraAttributes([
                    'class' => 'alig', // Tailwind para ajustar el margen alinearlo a la derecha

                ]),

            Action::make('cancelSale')
                ->label('Cancelar venta')
                ->icon('heroicon-o-no-symbol')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Confirmación!!')
                ->modalSubheading('¿Estás seguro de que deseas cancelar esta venta? Esta acción no se puede deshacer.')
                ->modalButton('Sí, cancelar venta')
                ->action(function (Actions\DeleteAction $delete) {
                    if ($this->record->is_dte) {
                        Notification::make('No se puede cancelar una venta con DTE')
                            ->title('Error al anular venta')
                            ->body('No se puede cancelar una venta con DTE')
                            ->danger()
                            ->send();
                        return;
                    }
                    $this->record->delete();
                    SaleItem::where('sale_id', $this->record->id)->delete();
                    $this->redirect(static::getResource()::getUrl('index'));
                }),
        ];
    }

    #[On('refreshSale')]
    public function refresh(): void
    {
    }


    public function aftersave()//Disminuir el inventario
    {
        $openedCashBox = (new GetCashBoxOpenedService())->getOpenCashBoxId(false);
        if (!$openedCashBox) {
            Notification::make('No se puede finalizar la venta')
                ->title('Caja cerrada')
                ->body('No se puede finalizar la venta porque no hay caja abierta')
                ->danger()
                ->send();
            return;
        }

        $id_sale = $this->record->id; // Obtener el registro de la compra
        $sale = Sale::with('documenttype', 'customer', 'customer.country')->find($id_sale);
        $salesItem = SaleItem::where('sale_id', $sale->id)->get();
        $client = $sale->customer;
        $documnetType = $sale->documenttype->name;
        $entity = $client->name . ' ' . $client->last_name;
        $pais = $client->country->name ?? 'Salvadoreña';
        foreach ($salesItem as $item) {
            $inventory = Inventory::find($item->inventory_id);

            // Verifica si el inventario existe
            if (!$inventory) {
                \Log::error("Inventario no encontrado para el item de compra: {$item->id}");
                continue; // Si no se encuentra el inventario, continua con el siguiente item
            }

            // Actualiza el stock del inventario
            $newStock = $inventory->stock - $item->quantity;
            $inventory->update(['stock' => $newStock]);

            // Crear el Kardex
            $kardex = KardexHelper::createKardexFromInventory(
                $inventory->branch_id, // Se pasa solo el valor de branch_id (entero)
                $sale->created_at, // date
                'Venta', // operation_type
                $sale->id, // operation_id
                $item->id, // operation_detail_id
                $documnetType, // document_type
                $sale->document_internal_number, // document_number
                $entity, // entity
                $pais, // nationality
                $inventory->id, // inventory_id
                $inventory->stock + $item->quantity, // previous_stock
                0, // stock_in
                $item->quantity, // stock_out
                $newStock, // stock_actual
                0, // money_in
                $item->quantity * $item->price, // money_out
                $inventory->stock * $item->price, // money_actual
                $item->price, // sale_price
                0 // purchase_price
            );

            // Verifica si la creación del Kardex fue exitosa
            if (!$kardex) {
                \Log::error("Error al crear Kardex para el item de compra: {$item->id}");
            }
        }


        $sale->update([
            'cashbox_open_id' => $openedCashBox,
            'is_invoiced_order' => true,
            'sales_payment_status'=>'Pagada',
            'sale_status' => 'Facturada',
        ]);

        //obtener id de la caja y buscar la caja
        $idCajaAbierta = (new GetCashBoxOpenedService())->getOpenCashBoxId(true);
        $correlativo = CashBoxCorrelative::where('cash_box_id', $idCajaAbierta)->where('document_type_id', $sale->document_type_id)->first();
        $correlativo->current_number = $sale->document_internal_number;
        $correlativo->save();

        // Redirigir después de completar el proceso
        $this->redirect(static::getResource()::getUrl('index'));
    }

}
