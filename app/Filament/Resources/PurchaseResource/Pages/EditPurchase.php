<?php

namespace App\Filament\Resources\PurchaseResource\Pages;

use App\Filament\Resources\PurchaseResource;
use App\Helpers\KardexHelper;
use App\Models\Inventory;
use App\Models\Provider;
use App\Models\PurchaseItem;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\On;

class EditPurchase extends EditRecord
{
    protected static string $resource = PurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function getTitle(): string|Htmlable
    {
        return '';// TODO: Change the autogenerated stub
    }


    #[On('refreshPurchase')]
    public function refresh(): void
    {
    }


        public function aftersave()
        {
            $purchase = $this->record; // Obtener el registro de la compra
            $purchaseItems = PurchaseItem::where('purchase_id', $purchase->id)->get();
            $provider = Provider::with('pais')->find($purchase->provider_id);
            $entity = $provider->comercial_name;
            $pais = $provider->pais->name;

            foreach ($purchaseItems as $item) {
                $inventory = Inventory::find($item->inventory_id);

                // Verifica si el inventario existe
                if (!$inventory) {
                    \Log::error("Inventario no encontrado para el item de compra: {$item->id}");
                    continue; // Si no se encuentra el inventario, continua con el siguiente item
                }

                // Actualiza el stock del inventario
                $newStock = $inventory->stock + $item->quantity;
                $inventory->update(['stock' => $newStock]);

                // Crear el Kardex
                $kardex = KardexHelper::createKardexFromInventory(
                    $inventory->branch_id, // Se pasa solo el valor de branch_id (entero)
                    $purchase->purchase_date, // date
                    'Compra', // operation_type
                    $purchase->id, // operation_id
                    $item->id, // operation_detail_id
                    'CCF', // document_type
                    $purchase->document_number, // document_number
                    $entity, // entity
                    $pais, // nationality
                    $inventory->id, // inventory_id
                    $inventory->stock - $item->quantity, // previous_stock
                    $item->quantity, // stock_in
                    0, // stock_out
                    $inventory->stock, // stock_actual
                    $item->quantity * $item->price, // money_in
                    0, // money_out
                    $inventory->stock * $item->price, // money_actual
                    0, // sale_price
                    $item->price // purchase_price
                );

                // Verifica si la creación del Kardex fue exitosa
                if (!$kardex) {
                    \Log::error("Error al crear Kardex para el item de compra: {$item->id}");
                }
            }

            // Redirigir después de completar el proceso
            $this->redirect(static::getResource()::getUrl('index'));
        }


}