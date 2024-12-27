<?php

namespace App\Filament\Resources\SaleResource\RelationManagers;

use App\Models\Inventory;
use App\Models\Price;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\RetentionTaxe;
use App\Models\SaleItem;
use App\Models\Tribute;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Sale;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class SaleItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'saleDetails';
    protected static ?string $label = 'Prodúctos agregados';
    protected static ?string $pollingInterval = '1s';


    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información del producto a vender')
//                    ->description('Agregue los productos que desea vender')
                    ->icon('heroicon-o-shopping-cart')
                    ->columns(3)
                    ->schema([
                        Select::make('inventory_id')
                            ->label('Producto')
                            ->searchable()
                            ->live()
                            ->debounce(300)
                            ->columnSpanFull()
                            ->inlineLabel(false)
                            ->getSearchResultsUsing(function (string $query) {
                                $whereHouse = \Auth::user()->employee->branch_id;

                                if (strlen($query) < 3) {
                                    return []; // No cargar resultados hasta que haya al menos 3 letras
                                }
                                return Inventory::with('product')
                                    ->where('branch_id', $whereHouse)
                                    ->whereHas('product', function ($q) use ($query) {
                                        $q->where('name', 'like', "%{$query}%")
                                            ->orWhere('sku', 'like', "%{$query}%")
                                            ->orWhere('bar_code', 'like', "%{$query}%");
                                    })
                                    ->limit(50) // Limita el número de resultados para evitar cargas pesadas
                                    ->get()
                                    ->mapWithKeys(function ($inventory) {
                                        $displayText = "{$inventory->product->name} - SKU: {$inventory->product->sku} - Codigo: {$inventory->product->bar_code}";
                                        return [$inventory->id => $displayText];
                                    });
                            })
                            ->getOptionLabelUsing(function ($value) {
                                $inventory = Inventory::with('product')->find($value);
                                return $inventory
                                    ? "{$inventory->product->name} - SKU: {$inventory->product->sku} - Codigo: {$inventory->product->bar_code}"
                                    : 'Producto no encontrado';
                            })
                            ->required()
                            ->afterStateUpdated(function (callable $get, callable $set) {
                                $invetory_id = $get('inventory_id');

                                $price = Price::with('inventory')->where('inventory_id', $invetory_id)->Where('is_default', true)->first();
                                if ($price && $price->inventory) {
                                    $set('price', $price->price);
                                    $set('quantity', 1);
                                    $set('discount', 0);
                                    $set('minprice', $price->inventory->cost_with_taxes);
                                    $this->calculateTotal($get, $set);
                                } else {
                                    $set('price', $price->price??0);
                                    $set('quantity', 1);
                                    $set('discount', 0);
                                    $this->calculateTotal($get, $set);
                                }
                            }),

                        Forms\Components\TextInput::make('quantity')
                            ->label('Cantidad')
                            ->step(1)
                            ->numeric()
                            ->live()
                            ->debounce(300)
                            ->columnSpan(1)
                            ->required()
                            ->live()
                            ->extraAttributes(['onkeyup' => 'this.dispatchEvent(new Event("input"))'])
                            ->afterStateUpdated(function (callable $get, callable $set) {
                                $this->calculateTotal($get, $set);
                            }),

                        Forms\Components\TextInput::make('price')
                            ->label('Precio')
                            ->step(0.01)
                            ->numeric()
                            ->columnSpan(1)
                            ->required()
                            ->live()
                            ->debounce(300)

                            ->afterStateUpdated(function (callable $get, callable $set) {
                                $this->calculateTotal($get, $set);
                            }),

                        Forms\Components\TextInput::make('discount')
                            ->label('Descuento')
                            ->step(0.01)
                            ->prefix('%')
                            ->numeric()
                            ->live()
                            ->columnSpan(1)
                            ->required()
                            ->debounce(300)

                            ->afterStateUpdated(function (callable $get, callable $set) {
                                $this->calculateTotal($get, $set);
                            }),

                        Forms\Components\TextInput::make('total')
                            ->label('Total')
                            ->step(0.01)
                            ->readOnly()
                            ->columnSpan(1)
                            ->required(),

                        Forms\Components\Toggle::make('is_except')
                            ->label('Exento de IVA')
                            ->columnSpan(1)
                            ->live()
                            ->afterStateUpdated(function (callable $get, callable $set) {
                                $this->calculateTotal($get, $set);
                            }),
                        Forms\Components\TextInput::make('minprice')
                            ->label('Tributos')
                            ->hidden(true)
                            ->columnSpan(3)
                            ->afterStateUpdated(function (callable $get, callable $set) {

                            }),
                    ]),


            ])
        ;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('Sales Item')
            ->columns([
                Tables\Columns\TextColumn::make('inventory.product.name')
                    ->wrap()
//                    ->searchable()
                    ->label('Producto'),
                Tables\Columns\BooleanColumn::make('inventory.product.is_service')
                    ->label('Producto/Servicio')
                    ->trueIcon('heroicon-o-bug-ant') // Icono cuando `is_service` es true
                    ->falseIcon('heroicon-o-cog-8-tooth') // Icono cuando `is_service` es false

                    ->tooltip(function ($record) {
                        return $record->inventory->product->is_service ? 'Es un servicio' : 'No es un servicio';
                    }),



                Tables\Columns\TextColumn::make('quantity')
                    ->label('Cantidad')
                    ->numeric()
                    ->columnSpan(1),
                Tables\Columns\TextColumn::make('price')
                    ->label('Precio')
                    ->money('USD', locale: 'en_US')
                    ->columnSpan(1),
                Tables\Columns\TextColumn::make('discount')
                    ->label('Descuento')
                    ->numeric()
                    ->columnSpan(1),
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('USD', locale: 'en_US')
                    ->columnSpan(1),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->modalWidth('7xl')
                    ->modalHeading('Agregar Producto a venta')
                    ->label('Agregar Producto')
                    ->after(function (SaleItem $record,Component $livewire) {
                        $this->updateTotalSale($record);
                        $livewire->dispatch('refreshSale');
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->modalWidth('7xl')
                    ->after(function (SaleItem $record, Component $livewire) {
                        $this->updateTotalSale($record);
                        $livewire->dispatch('refreshSale');

                    }),
                Tables\Actions\DeleteAction::make()
                    ->label('Quitar')
                    ->after(function (SaleItem $record,Component $livewire) {
                        $this->updateTotalSale($record);
                        $livewire->dispatch('refreshSale');

                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->after(function (SaleItem $record,Component $livewire) {
                        $selectedRecords = $livewire->getSelectedTableRecords();
                        foreach ($selectedRecords as $record) {
                            $this->updateTotalSale($record);
                        }
                        $livewire->dispatch('refreshSale');
                    }),

                ]),
            ]);
    }

    protected function calculateTotal(callable $get, callable $set)
    {
        try {
            $quantity = ($get('quantity') !== "" && $get('quantity') !== null) ? $get('quantity') : 0;
            $price = ($get('price') !== "" && $get('price') !== null) ? $get('price') : 0;
            $discount = $get('discount') / 100 ?? 0;
            $is_except = $get('is_except');

            $total = $quantity * $price;

            if ($discount > 0) {
                $total -= $total * $discount;
            }
            if ($is_except) {
                $total -= ($total * 0.13);
            }

            // Formatear precio y total a dos decimales
            $price = round($price, 2);
            $total = round($total, 2);

            $set('price', $price);
            $set('total', $total);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }


    }
    protected function updateTotalSale(SaleItem $record)
    {
        $idSale=$record->sale_id;
        $sale = Sale::where('id',$idSale)->first();

//        dd($sale);
        if ($sale) {
            try {
                $ivaRate = Tribute::where('code', 20)->value('rate') ?? 0;
                $isrRate = RetentionTaxe::where('code', 22)->value('rate') ?? 0;

                $ivaRate = is_numeric($ivaRate) ? $ivaRate / 100 : 0;
                $isrRate = is_numeric($isrRate) ? $isrRate / 100 : 0;
                $montoTotal = SaleItem::where('sale_id', $sale->id)->sum('total') ?? 0;
//            dd($montoTotal);
                $neto = $ivaRate > 0 ? $montoTotal / (1 + $ivaRate) : $montoTotal;
                $iva = $montoTotal - $neto;
                $retention = $sale->have_retention ? $neto * 0.1 : 0;
                $sale->net_amount = round($neto, 2);
                $sale->taxe = round($iva, 2);
                $sale->retention = round($retention, 2);
                $sale->sale_total = round($montoTotal-$retention, 2);
                $sale->save();
            }
            catch (\Exception $e){
                Log::error($e->getMessage());
            }


        }
    }

}
