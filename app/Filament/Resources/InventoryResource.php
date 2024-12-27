<?php

namespace App\Filament\Resources;

use App\Filament\Exports\InventoryExporter;
use App\Filament\Resources\InventoryResource\Pages;
use App\Filament\Resources\InventoryResource\RelationManagers;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Tribute;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\ReplicateAction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Actions\Action;
use Filament\Tables\Actions;
use Filament\Forms\Components\TextInput;

class InventoryResource extends Resource
{
    protected static function getWhereHouse(): string
    {
        return \Auth::user()->employee->wherehouse->name ?? 'N/A'; // Si no hay valor, usa 'N/A'
    }

    protected static ?string $model = Inventory::class;
    protected static ?string $navigationGroup = 'Inventario';

    protected static ?string $label = 'Inventario'; // Singular
    protected static ?string $pluralLabel = null;


    public static function getPluralLabel(): string
    {
        $label = self::getWhereHouse();
        return self::getLabel() . ' - ' . $label; // Usar la misma lógica para plural o ajustarlo según sea necesario
    }

    public static function form(Form $form): Form
    {
        $tax = Tribute::find(1)->select('rate', 'is_percentage')->first();
        if (!$tax) {
            $tax = (object)['rate' => 0, 'is_percentage' => false];
        }
        $divider = ($tax->is_percentage) ? 100 : 1;
        $iva = $tax->rate / $divider;
        return $form
            ->schema([
                Forms\Components\Section::make('Informacion del Inventario')
                    ->columns(2)
                    ->compact()
                    ->schema([
                        Forms\Components\Select::make('product_id')
                            ->required()
                            ->inlineLabel(false)
                            ->preload()
                            ->columnSpanFull()
                            ->relationship('product', 'name')
                            ->searchable(['name', 'sku'])
                            ->placeholder('Seleccionar producto')
                            ->loadingMessage('Cargando productos...')
                            ->getOptionLabelsUsing(function ($record) {
                                return "{$record->name} (SKU: {$record->sku})";  // Formato de la etiqueta
                            }),

                        Forms\Components\Select::make('branch_id')
                            ->label('Sucursal')
                            ->placeholder('Seleccionar sucursal')
                            ->relationship('branch', 'name')
                            ->preload()
                            ->searchable(['name'])
                            ->required(),

                        Forms\Components\TextInput::make('stock')
                            ->required()
                            ->numeric()
                            ->default(0),
                        Forms\Components\Hidden::make('stock_actual')
                            ->default(0) // Valor predeterminado para nuevos registros
                            ->afterStateHydrated(function (Forms\Components\Hidden $component, $state, $record) {
                                if ($record) {
                                    $component->state($record->stock);
                                }
                            }),

                        Forms\Components\TextInput::make('stock_min')
                            ->label('Stock Minimo')
                            ->required()
                            ->numeric()
                            ->default(0),
                        Forms\Components\TextInput::make('stock_max')
                            ->label('Stock Maximo')
                            ->required()
                            ->numeric()
                            ->default(0),
                        Forms\Components\TextInput::make('cost_without_taxes')
                            ->required()
                            ->prefix('$')
                            ->label('Costo sin impuestos')
                            ->numeric()
                            ->inputMode('decimal')
                            ->hintColor('red')
                            ->debounce(500) // Espera 500 ms después de que el usuario deje de escribir
                            ->afterStateUpdated(function ($state, callable $set) use ($iva) {
                                $costWithoutTaxes = $state ?: 0; // Valor predeterminado en 0 si está vacío
                                $costWithTaxes = round($costWithoutTaxes * $iva, 2); // Cálculo del costo con impuestos
                                $costWithTaxes += $costWithoutTaxes; // Suma el costo sin impuestos
                                $set('cost_with_taxes', $costWithTaxes); // Actualiza el campo
                            })
                            ->default(0.00),
                        Forms\Components\TextInput::make('cost_with_taxes')
                            ->label('Costo con impuestos')
                            ->required()
                            ->readOnly()
                            ->numeric()
                            ->prefix('$')
                            ->default(0.00),
                        Forms\Components\Section::make('Configuración')
                            ->columns(3)
                            ->compact()
                            ->schema([
                                Forms\Components\Toggle::make('is_stock_alert')
                                    ->label('Alerta de stock minimo')
                                    ->default(true)
                                    ->required(),
                                Forms\Components\Toggle::make('is_expiration_date')
                                    ->label('Tiene vencimiento')
                                    ->default(true)
                                    ->required(),
                                Forms\Components\Toggle::make('is_active')
                                    ->default(true)
                                    ->label('Activo')
                                    ->required(),
                            ]) // Fin de la sección de configuración

                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Producto')
                    ->wrap()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.aplications')
                    ->label('Aplicaciones')
                    ->badge()
                    ->searchable()
                    ->separator(';'),
                Tables\Columns\TextColumn::make('product.sku')
                    ->label('SKU')
                    ->copyable()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->sortable(),
                Tables\Columns\TextColumn::make('stock')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('stock_min')
                    ->label('Stock Minimo')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                Tables\Columns\TextColumn::make('stock_max')
                    ->label('Stock Maximo')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('cost_without_taxes')
                    ->label('Costo')
                    ->numeric()
                    ->money('USD', locale: 'en_US')
                    ->sortable(),
                Tables\Columns\TextColumn::make('cost_with_taxes')
                    ->label('C.+    IVA')
                    ->numeric()
                    ->money('USD', locale: 'en_US')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_stock_alert')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_expiration_date')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->boolean(),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
//            ->deferLoading()
            ->striped()
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('branch_id')
                    ->relationship('branch', 'name')
                    ->label('Sucursal')
                    ->preload()
                    ->placeholder('Buscar por sucursal'),
//                Tables\Filters\Filter::make('product_name') // Filtro para el nombre del producto
//                ->form([
//                    TextInput::make('product_name') // Nombre del campo del filtro
//                    ->label('Producto')
//                        ->placeholder('Enter part of the product name'),
//                ])->query(function ($query, array $data) {
//                        // Usar la relación con la tabla de productos para buscar por nombre
//                        return $query->whereHas('product', function ($query) use ($data) {
//                            $query->where('name', 'like', '%' . $data['product_name'] . '%');
//                        });
//                    }),
//                Tables\Filters\Filter::make('product_aplications')
//                    ->form([TextInput::make('product_aplications')
//                        ->label('Aplicaciones')
//                        ->placeholder('Enter part of the product name'),
//                    ])
//                    ->query(function ($query, array $data) {
//                        return $query->whereHas('product', function ($query) use ($data) {
//                            $query->where('aplications', 'like', '%' . $data['product_aplications'] . '%');
//                        });
//                    }),
            ])->filtersFormColumns(2)
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\ReplicateAction::make()
                        ->form([
                            Forms\Components\Select::make('branch_did')
                                ->relationship('branch', 'name')
                                ->label('Sucursal Destino')
                                ->required()
                                ->placeholder('Ingresa el ID de la sucursal'),
                        ])
                        ->beforeReplicaSaved(function (Inventory $record, Actions\Action $action, $replica, array $data): void {
                            try {
                                $existencia = Inventory::withTrashed()
                                    ->where('product_id', $record->product_id)
                                    ->where('branch_id', $data['branch_did'])
                                    ->first();
                                if ($existencia) {
                                    // Si el registro está eliminado
                                    if ($existencia->trashed()) {
                                        Notification::make('Inventario Eliminado')
                                            ->title('Replicar Inventario')
                                            ->danger()
                                            ->body('El inventario ya existe en la sucursal destino, pero el estado es eliminado, restarualo para poder replicarlo')
                                            ->send();
                                        $action->halt(); // Detener la acción si el inventario está eliminado
                                    } else {
                                        // Si el registro existe y no está eliminado
                                        Notification::make('Registro Duplicado')
                                            ->danger()
                                            ->body('Ya existe un registro con el producto ' . $record->product->name . ' en la sucursal ' . $record->branch->name . '.')
                                            ->send();
                                        $action->halt(); // Detener la acción si se encuentra un registro duplicado
                                    }
                                }
                            } catch (\Exception $e) {
                                $action->halt(); // Detener la acción en caso de error
                            }
                        }),


                    Tables\Actions\DeleteAction::make(),
                    Tables\Actions\ForceDeleteAction::make(),
                ]),
            ])
            ->headerActions([

            ])
            ->searchable('product.name', 'product.sku', 'branch.name', 'product.aplications')
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    ExportAction::make()
                        ->exporter(InventoryExporter::class)
                        ->formats([
                            ExportFormat::Csv,
                        ])
                        ->formats([
                            ExportFormat::Xlsx,
                        ])
                        // or
                        ->formats([
                            ExportFormat::Xlsx,
                            ExportFormat::Csv,
                        ])

                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PricesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventories::route('/'),
            'create' => Pages\CreateInventory::route('/create'),
//            'replicate' => Pages\ReplicateInventory::route('/{record}/replicate'),
            'edit' => Pages\EditInventory::route('/{record}/edit'),
        ];
    }

    public static function afterUpdate(): void
    {
        dd('Hola');

    }

    protected function beforeSave(): void
    {
        dd('Hola');
    }
}
