<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static ?string $label = 'Prodúctos';
    protected static ?string $navigationGroup = 'Almacén';
    protected static ?string $recordTitleAttribute = 'name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'sku', 'bar_code'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Prodúcto' => $record->name,
            'sku' => $record->sku,
            'Codigo de Barra' => $record->bar_code,
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información del prodúcto')
                    ->compact()
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->inlineLabel(false)
//                            ->columnSpanFull()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('aplications')
                            ->placeholder('Separar con punto y comas (;)')
//                            ->columnSpanFull()
                            ->inlineLabel(false)
                            ->label('Aplicaciones'),
                        Forms\Components\TextInput::make('sku')
                            ->label('SKU')
                            ->maxLength(255)
                            ->default(null),
                        Forms\Components\TextInput::make('bar_code')
                            ->label('Código de barras')
                            ->maxLength(255)
                            ->default(null),

                        Forms\Components\Select::make('category_id')
                            ->label('Categoría')
                            ->relationship('category', 'name')
                            ->preload()
                            ->searchable()
                            ->required(),
                        Forms\Components\Select::make('marca_id')
                            ->label('Marca')
                            ->preload()
                            ->searchable()
                            ->relationship('marca', 'nombre')
                            ->required(),
                        Forms\Components\Select::make('unit_measurement_id')
                            ->label('Unidad de medida')
                            ->preload()
                            ->searchable()
                            ->relationship('unitMeasurement', 'description')
                            ->required(),
//                        Forms\Components\MultiSelect::make('tribute_id')
//                            ->label('Impuestos')
//                            ->preload()
//                            ->searchable()
//                            ->relationship('tributes', 'name'),

                        Forms\Components\Section::make('Configuración')
                            ->schema([
                                Forms\Components\Toggle::make('is_service')
                                    ->label('Es un servicio')
                                    ->required(),
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Activo')
                                    ->default(true)
                                    ->required(),
                                Forms\Components\Toggle::make('is_taxed')
                                    ->label('Gravado')
                                    ->default(true)
                                    ->required(),
                            ])->columns(3),

                        Forms\Components\FileUpload::make('images')
                            ->directory('products')
                            ->image()
//                            ->avatar()
//                            ->multiple()
                            ->columnSpanFull(),

                    ])->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\Layout\Grid::make()
                    ->columns(1)
                    ->schema([
                        Tables\Columns\Layout\Split::make([
                            Tables\Columns\Layout\Grid::make()
                                ->columns(1)
                                ->schema([
                                    Tables\Columns\ImageColumn::make('images')
                                        ->placeholder('Sin imagen')
                                        ->defaultImageUrl(url('storage/products/noimage.jpg'))
                                        ->openUrlInNewTab()
                                        ->height(150)
                                        ->square()
                                        ->width(150)
                                        ->extraAttributes([
                                            'class' => 'rounded-md',
                                            'loading' => 'lazy'
                                        ])

                                ])->grow(false),
                            Tables\Columns\Layout\Stack::make([
                                Tables\Columns\TextColumn::make('name')
                                    ->label('Producto')
                                    ->weight(FontWeight::SemiBold)
                                    ->sortable()
                                    ->icon('heroicon-s-cube')
                                    ->wrap()
                                    ->formatStateUsing(fn($state, $record) => $record->deleted_at ? "<span style='text-decoration: line-through; color: red;'>$state</span>" : $state)
                                    ->html()
                                    ->searchable(),
                                Tables\Columns\TextColumn::make('aplications')
                                    ->label('Aplicaicones')
                                    ->badge()
                                    ->icon('heroicon-s-cog')

                                    ->sortable()
                                    ->separator(';')
                                    ->searchable(),
                                Tables\Columns\TextColumn::make('sku')
                                    ->label('SKU')
                                    ->copyable()
                                    ->icon('heroicon-s-qr-code')
                                    ->copyMessage('SKU  copied')
                                    ->searchable(),

                                Tables\Columns\TextColumn::make('bar_code')
                                    ->icon('heroicon-s-code-bracket-square')
                                    ->label('C. Barras')
                                    ->toggleable(isToggledHiddenByDefault: true)
                                    ->searchable(),
                                Tables\Columns\TextColumn::make('category.name')
                                    ->label('Linea')
                                    ->icon('heroicon-s-wrench-screwdriver')
                                    ->sortable(),
                                Tables\Columns\TextColumn::make('marca.nombre')
                                    ->icon('heroicon-s-check-badge')
                                    ->sortable(),
                                Tables\Columns\TextColumn::make('unitMeasurement.description')
                                    ->label('Presentación')
                                    ->icon('heroicon-s-scale')
                                    ->sortable(),

                            ])->extraAttributes([
                                'class' => 'space-y-2'
                            ])
                                ->grow(),


                        ]),

                    ]),


            ])
            ->contentGrid([
                'md' => 3,
                'xs' => 4,
            ])
            ->paginationPageOptions([
                5, 10, 25, 50, 100 // Define your specific pagination limits here
            ])
            ->filters([
                //
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Categoría')
                    ->searchable()
                    ->preload()
                    ->relationship('category', 'name')
                    ->options(fn() => \App\Models\Category::pluck('name', 'id')->toArray())
                    ->default(null),
                Tables\Filters\SelectFilter::make('marca_id')
                    ->label('Marca')
                    ->searchable()
                    ->preload()
                    ->relationship('marca', 'nombre')
                    ->options(fn() => \App\Models\Marca::pluck('nombre', 'id')->toArray())
                    ->default(null),
                Tables\Filters\TrashedFilter::make(),


            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\ReplicateAction::make(),
                    Tables\Actions\DeleteAction::make(),
                    Tables\Actions\RestoreAction::make(),
                ])->label('Acciones'),
            ], position: Tables\Enums\ActionsPosition::AfterContent)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
//                    ExportAction::make(),
                ])
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
//            'create' => Pages\CreateProduct::route('/create'),
//            'view' => Pages\CreateProduct::route('/view'),
//            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }

}
