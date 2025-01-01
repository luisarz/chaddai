<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransferResource\Pages;
use App\Filament\Resources\TransferResource\RelationManagers;
use App\Models\Transfer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TransferResource extends Resource
{
    protected static ?string $model = Transfer::class;

    protected static ?string $navigationGroup = "Inventario";
    protected static ?string $label = 'Traslados';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()->compact()
                    ->schema([
                        Forms\Components\TextInput::make('transfer_number')
                            ->maxLength(255)
                            ->default(0),
                        Forms\Components\Select::make('wherehouse_from')
                            ->label('Sucursal Origen')
                            ->relationship('wherehouse_from', 'name', function ($query) {
                                $actualbranch = auth()->user()->employee->branch_id;
                                $query->where('id', $actualbranch); // Filtrar por la sucursal actual
                            })
                            ->default(function () {
                                return \App\Models\Branch::where('id', auth()->user()->employee->branch_id)->first()?->id;
                            })
                            ->required(),


                        Forms\Components\Select::make('user_send')
                            ->label('Empleado Envia')
                            ->relationship('user_send', 'name')
                            ->searchable(),

                        Forms\Components\Select::make('wherehouse_to')
                            ->label('Sucursal Destino')
                            ->relationship('wherehouse_to', 'name', function ($query) {
                                $actualbranch = auth()->user()->employee->branch_id;
                                $query->where('id','!=', $actualbranch); // Filtrar por la sucursal actual
                            })
                           
                            ->required(),


                        Forms\Components\Select::make('user_recive')
                            ->label('Empleado Recibe')
                            ->relationship('user_recive', 'name')
                        ,
                        Forms\Components\DateTimePicker::make('transfer_date')
                            ->required(),
                        Forms\Components\DateTimePicker::make('received_date')
                            ->required(),
                        Forms\Components\TextInput::make('total')
                            ->required()
                            ->numeric(),
                        Forms\Components\TextInput::make('status_send')
                            ->required()
                            ->maxLength(255)
                            ->default('pendiente'),
                        Forms\Components\TextInput::make('status_received')
                            ->required()
                            ->maxLength(255)
                            ->default('pendiente'),
                    ])->columns(2)

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('transfer_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('wherehouse_from')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user_send')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('wherehouse_to')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user_recive')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('transfer_date')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('received_date')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status_send')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status_received')
                    ->searchable(),
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
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
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
            'index' => Pages\ListTransfers::route('/'),
            'create' => Pages\CreateTransfer::route('/create'),
            'edit' => Pages\EditTransfer::route('/{record}/edit'),
        ];
    }
}
