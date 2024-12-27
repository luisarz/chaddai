<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SaleResource\Pages;
use App\Filament\Resources\SaleResource\RelationManagers;
use App\Http\Controllers\DTEController;
use App\Http\Controllers\SenEmailDTEController;
use App\Models\CashBoxCorrelative;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\HistoryDte;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Tribute;
use App\Service\GetCashBoxOpenedService;
use App\Tables\Actions\dteActions;
use DeepCopy\TypeFilter\Date\DatePeriodFilter;
use Filament\Actions\Exports\Models\Export;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\IconSize;
use Filament\Support\View\Components\Modal;
use Filament\Tables;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Livewire\Component;
use Filament\Infolists\Components\IconEntry;
use Malzariey\FilamentDaterangepickerFilter\Fields\DateRangePicker;
use Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

function updateTotalSale(mixed $idItem, array $data): void
{
    $applyRetention = $data['have_retention'] ?? false;
    $applyTax = $data['is_taxed'] ?? false;

    $sale = Sale::find($idItem);

    if ($sale) {
        // Fetch tax rates with default values
        $ivaRate = Tribute::where('id', 1)->value('rate') ?? 0;
        $isrRate = Tribute::where('id', 3)->value('rate') ?? 0;

        $ivaRate /= 100;
        $isrRate /= 100;
        // Calculate total and net amounts
        $montoTotal = SaleItem::where('sale_id', $sale->id)->sum('total') ?? 0;
        $neto = $applyTax && $ivaRate > 0 ? $montoTotal / (1 + $ivaRate) : $montoTotal;

        // Calculate tax and retention conditionally
        $iva = $applyTax ? $montoTotal - $neto : 0;
        $retention = $applyRetention ? $neto * $isrRate : 0;

        // Round and save calculated values
        $sale->net_amount = round($neto, 2);
        $sale->taxe = round($iva, 2);
        $sale->retention = round($retention, 2);
        $sale->sale_total = round($montoTotal - $retention, 2);
        $sale->save();
    }
}

class SaleResource extends Resource
{
    protected static ?string $model = Sale::class;

    protected static ?string $label = 'Ventas';
    protected static ?string $navigationGroup = 'Facturación';
    protected static bool $softDelete = true;


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('')
                    ->schema([

                        Grid::make(12)
                            ->schema([

                                Section::make('Venta')
                                    ->icon('heroicon-o-user')
                                    ->iconColor('success')
                                    ->compact()
                                    ->schema([
                                        Forms\Components\DatePicker::make('operation_date')
                                            ->label('Fecha')
                                            ->required()
                                            ->inlineLabel(true)
                                            ->default(now()),
                                        Forms\Components\Select::make('wherehouse_id')
                                            ->label('Sucursal')
                                            ->live()
                                            ->relationship('wherehouse', 'name')
                                            ->preload()
                                            ->disabled()
                                            ->default(fn() => optional(Auth::user()->employee)->branch_id), // Null-safe check
                                        Forms\Components\Select::make('document_type_id')
                                            ->label('Comprobante')
//                                            ->relationship('documenttype', 'name')
                                            ->options(function (callable $get) {
                                                $openedCashBox = (new GetCashBoxOpenedService())->getOpenCashBoxId(true);
                                                if ($openedCashBox > 0) {
                                                    return CashBoxCorrelative::with('document_type')
                                                        ->where('cash_box_id', $openedCashBox)
                                                        ->get()
                                                        ->mapWithKeys(function ($item) {
                                                            return [$item->id => $item->document_type->name];
                                                        })
                                                        ->toArray(); // Asegúrate de devolver un array
                                                }

                                                return []; // Retorna un array vacío si no hay una caja abierta
                                            })
                                            ->preload()
                                            ->reactive() // Permite reaccionar a cambios en el campo
                                            ->afterStateUpdated(function ($state, callable $set) {
                                                if ($state) {
                                                    $lastIssuedDocument = CashBoxCorrelative::where('document_type_id', $state)
                                                        ->first();
                                                    if ($lastIssuedDocument) {
                                                        // Establece el número del último documento emitido en otro campo
                                                        $set('document_internal_number', $lastIssuedDocument->current_number+1);
                                                    }
                                                }
                                            })
                                            ->required(),
                                        Forms\Components\TextInput::make('document_internal_number')
                                            ->label('#   Comprobante')
                                            ->required()
                                            ->maxLength(255),


                                        Forms\Components\Select::make('seller_id')
                                            ->label('Vendedor')
                                            ->preload()
                                            ->searchable()
                                            ->live()
                                            ->options(function (callable $get) {
                                                $wherehouse = $get('wherehouse_id');
                                                if ($wherehouse) {
                                                    return Employee::where('branch_id', $wherehouse)->pluck('name', 'id');
                                                }
                                                return []; // Return an empty array if no wherehouse selected
                                            })
                                            ->required()
                                            ->disabled(fn(callable $get) => !$get('wherehouse_id')), // Disable if no wherehouse selected

                                        Forms\Components\Select::make('customer_id')
                                            ->relationship('customer', 'name')
                                            ->options(function (callable $get) {
                                                $documentType = $get('document_type_id');
                                                if ($documentType == 2) {
                                                    return Customer::whereNotNull('departamento_id')
                                                        ->whereNotNull('distrito_id')//MUnicipio
                                                        ->whereNotNull('economicactivity_id')
                                                        ->whereNotNull('nrc')
                                                        ->whereNotNull('dui')
                                                        ->orderBy('name')
                                                        ->pluck('name', 'id');
                                                }
                                                return Customer::orderBy('name')->pluck('name', 'id');
                                            })
                                            ->preload()
                                            ->searchable()
                                            ->label('Cliente')
//                                                    ->inlineLabel(false)
//                                                    ->columnSpanFull()
                                            ->createOptionForm([


                                                Section::make('Nuevo Cliente')
                                                    ->schema([
                                                        Select::make('wherehouse_id')
                                                            ->label('Sucursal')
                                                            ->inlineLabel(false)
                                                            ->relationship('wherehouse', 'name')
                                                            ->preload()
                                                            ->default(fn() => optional(Auth::user()->employee)->branch_id)
                                                            ->columnSpanFull(),


                                                        // Null-safe check
                                                        Forms\Components\TextInput::make('name')
                                                            ->required()
                                                            ->label('Nombre'),
                                                        Forms\Components\TextInput::make('last_name')
                                                            ->required()
                                                            ->label('Apellido'),
                                                    ])->columns(2),
                                            ])
                                        ,

                                        Forms\Components\Select::make('sales_payment_status')
                                            ->options(['Pagado' => 'Pagado',
                                                'Pendiente' => 'Pendiente',
                                                'Abono' => 'Abono',])
                                            ->label('Estado de pago')
                                            ->default('Pendiente')
                                            ->hidden()
                                            ->disabled(),
                                        Forms\Components\Select::make('status')
                                            ->options(['Nuevo' => 'Nuevo',
                                                'Procesando' => 'Procesando',
                                                'Cancelado' => 'Cancelado',
                                                'Facturado' => 'Facturado',
                                                'Anulado' => 'Anulado',])
                                            ->default('Nuevo')
                                            ->hidden()
                                            ->required(),
                                        Section::make('')//Resumen Venta
                                        ->description('Resumen Venta')
                                            ->compact()
                                            ->schema([
                                                Forms\Components\Placeholder::make('net_amount')
                                                    ->content(fn(?Sale $record) => new HtmlString('<span style="font-weight: bold;  font-size: 15px;">$ ' . number_format($record->net_amount ?? 0, 2) . '</span>'))
                                                    ->inlineLabel()
                                                    ->label('Neto'),

                                                Forms\Components\Placeholder::make('taxe')
                                                    ->content(fn(?Sale $record) => new HtmlString('<span style="font-weight: bold;  font-size: 15px;">$ ' . number_format($record->taxe ?? 0, 2) . '</span>'))
                                                    ->inlineLabel()
                                                    ->label('IVA'),

                                                Forms\Components\Placeholder::make('retention')
                                                    ->content(fn(?Sale $record) => $record->retention ?? 0)
                                                    ->inlineLabel()
                                                    ->content(fn(?Sale $record) => new HtmlString('<span style="font-weight: bold;  font-size: 15px;">$ ' . number_format($record->retention ?? 0, 2) . '</span>'))
                                                    ->label('ISR -1%'),
                                                Forms\Components\Placeholder::make('total')
                                                    ->label('Total')
                                                    ->content(fn(?Sale $record) => new HtmlString('<span style="font-weight: bold; color: red; font-size: 18px;">$ ' . number_format($record->sale_total ?? 0, 2) . '</span>'))
                                                    ->inlineLabel()
                                                    ->extraAttributes(['class' => 'p-0 text-lg']) // Tailwind classes for padding and font size
//                                    ->columnSpan('full'),
                                            ])->columnSpanFull()->columns(4),
                                    ])->columnSpan(9)
                                    ->extraAttributes([
                                        'class' => 'bg-blue-100 border border-blue-500 rounded-md p-2',
                                    ])
                                    ->columns(2),


                                Section::make('Caja')
                                    ->compact()
                                    ->schema([
                                        Forms\Components\Toggle::make('is_taxed')
                                            ->label('Gravado')
                                            ->default(true)
                                            ->onColor('danger')
                                            ->reactive()
                                            ->offColor('gray')
                                            ->required(),
                                        Forms\Components\Toggle::make('have_retention')
                                            ->label('Retención')
                                            ->onColor('danger')
                                            ->offColor('gray')
                                            ->default(true)
                                            ->required()
                                            ->reactive()
                                            ->afterStateUpdated(function ($set, $state, $get, Component $livewire) {
                                                $idItem = $get('id'); // ID del item de venta
                                                $data = [
                                                    'have_retention' => $state,
                                                    'is_taxed' => $get('is_taxed'),
                                                ];
                                                updateTotalSale($idItem, $data);
                                                $livewire->dispatch('refreshSale');
                                            }),
                                        Forms\Components\Select::make('operation_condition_id')
                                            ->relationship('salescondition', 'name')
                                            ->label('Condición')
                                            ->required()
                                            ->default(1),
                                        Forms\Components\Select::make('payment_method_id')
                                            ->label('Pago')
                                            ->relationship('paymentmethod', 'name')
                                            ->preload()
                                            ->searchable()
                                            ->default(1),
                                        Forms\Components\TextInput::make('cash')
                                            ->label('Efectivo')
                                            ->required()
                                            ->numeric()
                                            ->default(0.00),
                                        Forms\Components\TextInput::make('change')
                                            ->label('Cambio')
                                            ->required()
                                            ->numeric()
                                            ->default(0.00),
                                    ])
                                    ->extraAttributes([
                                        'class' => 'bg-blue-100 border border-blue-500 rounded-md p-2',
                                    ])
                                    ->columnSpan(3)->columns(1),
                            ]),
                    ]),
            ]);
    }

    public static function getTableActions(): array
    {
        return [
            // Eliminar la acción de edición
//            EditAction::make()->hidden(),
        ];
    }

    public
    static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('operation_date')
                    ->label('Fecha de venta')
                    ->date()
                    ->timezone('America/El_Salvador') // Zona horaria (opcional)
                    ->sortable(),
//                Tables\Columns\TextColumn::make('created_at')
//                    ->label('Hora Registro')
//                    ->dateTime('H:i:s A')
//                    ->timezone('America/El_Salvador') // Zona horaria (opcional)
//                    ->sortable(),
                Tables\Columns\TextColumn::make('documenttype.name')
                    ->label('Comprobante')
                    ->sortable(),
                Tables\Columns\TextColumn::make('document_internal_number')
                    ->label('#')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_dte')
                    ->boolean()
                    ->tooltip('DTE')
                    ->trueIcon('heroicon-o-shield-check')
                    ->falseIcon('heroicon-o-shield-exclamation')
                    ->label('DTE')
                    ->sortable(),
                Tables\Columns\TextColumn::make('wherehouse.name')
                    ->label('Sucursal')
                    ->numeric()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                Tables\Columns\TextColumn::make('seller.name')
                    ->label('Vendedor')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('salescondition.name')
                    ->label('Condición')
                    ->sortable(),
                Tables\Columns\TextColumn::make('paymentmethod.name')
                    ->label('Método de pago')
                    ->sortable(),
                Tables\Columns\TextColumn::make('sales_payment_status')
                    ->label('Pago'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado'),
                Tables\Columns\IconColumn::make('is_taxed')
                    ->label('Gravado')
                    ->boolean(),
                Tables\Columns\TextColumn::make('net_amount')
                    ->label('Neto')
                    ->toggleable()
                    ->money('USD', locale: 'en_US')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                Tables\Columns\TextColumn::make('taxe')
                    ->label('IVA')
                    ->toggleable()
                    ->money('USD', locale: 'en_US')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                Tables\Columns\TextColumn::make('discount')
                    ->label('Descuento')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->money('USD', locale: 'en_US')
                    ->sortable(),
                Tables\Columns\TextColumn::make('retention')
                    ->label('Retención')
                    ->toggleable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->money('USD', locale: 'en_US')
                    ->sortable(),
                Tables\Columns\TextColumn::make('sale_total')
                    ->label('Total')
                    ->money('USD', locale: 'en_US')
                    ->sortable(),
                Tables\Columns\TextColumn::make('cash')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('change')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('casher.name')
                    ->label('Cajero')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

//                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->modifyQueryUsing(function ($query) {
                $query->where('is_invoiced_order', true);
            })
            ->recordUrl(null)
            ->filters([
                DateRangeFilter::make('operation_date')->timePicker24()
                    ->label('Fecha de venta')
                    ->default([
                        'start' => now()->subDays(30)->format('Y-m-d'),
                        'end' => now()->format('Y-m-d'),
                    ]),

            ])
            ->actions([
                dteActions::generarDTE(),
                dteActions::imprimirDTE(),
                dteActions::enviarDTE(),
                dteActions::anularDTE(),
                dteActions::historialDTE(),
            ], position: ActionsPosition::BeforeCells)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    ExportAction::make()
                        ->exports([
                            ExcelExport::make()
                                ->fromTable()
                                ->withFilename(fn($resource) => $resource::getModelLabel() . '-' . date('Y-m-d'))
                                ->withWriterType(\Maatwebsite\Excel\Excel::CSV)
                                ->withColumns([
                                    Column::make('created_at'),
                                ]),

                        ]),
                ]),
            ]);
    }

    public
    static function getRelations(): array
    {
        return [
            RelationManagers\SaleItemsRelationManager::class,
        ];
    }

    public
    static function getPages(): array
    {
        return [
            'index' => Pages\ListSales::route('/'),
            'create' => Pages\CreateSale::route('/create'),
            'edit' => Pages\EditSale::route('/{record}/edit'),
        ];
    }


}
