<?php

namespace App\Tables\Actions;

use Filament\Forms\Components\Select;
use Filament\Tables\Actions\Action;
use Filament\Support\Enums\IconSize;
use App\Http\Controllers\DTEController;
use App\Http\Controllers\SenEmailDTEController;
use App\Models\HistoryDte;
use Filament\Notifications\Notification;

class dteActions
{
    public static function generarDTE(): Action
    {
        return Action::make('dte')
            ->label('')
            ->tooltip('Generar DTE')
            ->visible(fn($record) => !$record->is_dte)
            ->icon('heroicon-o-rocket-launch')
            ->iconSize(IconSize::Large)
            ->requiresConfirmation()
            ->modalHeading('¿Está seguro de enviar el DTE?')
            ->color('danger')
            ->form([
                Select::make('tipoEnvio')
                    ->label('Tipo de Envío')
                    ->options(['normal' => 'Envío Normal'])
                    ->default('normal')
                    ->required(),
                Select::make('confirmacion')
                    ->label('Enviar por Email')
                    ->options(['si' => 'Sí, deseo enviar', 'no' => 'No, no enviar'])
                    ->required(),
            ])
            ->action(function ($record, array $data) {


                if ($data['confirmacion'] === 'si') {
                    $dteController = new DTEController();
                    $resultado = $dteController->generarDTE($record->id);
                    if ($resultado['estado'] === 'EXITO') {
                        Notification::make()
                            ->title('Envío Exitoso')
                            ->success()
                            ->send();
                        if($data['confirmacion'] === 'si') {
                            self::imprimirDTE()->action($record);
                            self::enviarDTE()->action($record);
                        }
                    } else {
                        Notification::make()
                            ->title('Fallo en envío')
                            ->danger()
                            ->body($resultado["mensaje"])
                            ->send();
                    }
                } else {
                    Notification::make()
                        ->title('Se canceló el envío')
                        ->warning()
                        ->send();
                }
            });
    }

    public static function anularDTE(): Action
    {
        return Action::make('anularDTE')
            ->label('')
            ->tooltip('Anular DTE')
            ->icon('heroicon-o-shield-exclamation')
            ->iconSize(IconSize::Large)
            ->visible(fn($record) => $record->is_dte && $record->status != 'Anulado')
            ->requiresConfirmation()
            ->modalHeading('¿Está seguro de Anular el DTE?')
            ->modalDescription('Al anular el DTE no se podrá recuperar')
            ->color('danger')
            ->form([
                Select::make('ConfirmacionAnular')
                    ->label('Confirmar')
                    ->options(['confirmacion' => 'Estoy seguro, si Anular'])
                    ->placeholder('Seleccione una opción')
                    ->required(),
            ])
            ->action(function ($record, array $data) {
                if ($data['ConfirmacionAnular'] === 'confirmacion') {
                    $dteController = new DTEController();
                    $resultado = $dteController->anularDTE($record->id);
                    if ($resultado['estado'] === 'EXITO') {
                        Notification::make()
                            ->title('Anulación Exitosa')
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Fallo en anulación')
                            ->danger()
                            ->body($resultado["mensaje"])
                            ->send();
                    }
                }
            });
    }

    public static function historialDTE(): Action
    {
        return Action::make('Historial')
            ->label('')
            ->icon('heroicon-o-finger-print')
            ->tooltip('Bitácora DTE')
            ->iconSize(IconSize::Large)
            ->color('primary')
            ->modalHeading('Bitácora procesos DTE')
            ->modalContent(function ($record) {
                $historial = HistoryDte::where('sales_invoice_id', $record->id)->get();
                return view('DTE.historial-dte', [
                    'record' => $record,
                    'historial' => $historial,
                ]);
            })
            ->modalDescription('Historial de envío de DTEs')
            ->modalWidth('7xl');
    }

    public static function enviarDTE(): Action
    {
        return Action::make('send')
            ->label('')
            ->icon('heroicon-o-envelope')
            ->iconSize(IconSize::Large)
            ->tooltip('Enviar DTE')
            ->visible(fn($record) => $record->is_dte)
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('¿Está seguro de enviar el DTE?')
            ->modalDescription('Al enviar el DTE, se enviará al correo del cliente!')
            ->action(function ($record) {
                $responseSendEmail = new SenEmailDTEController();
                $response = $responseSendEmail->SenEmailDTEController($record->id);
                $responseData = $response->getData(true);
                if ($responseData['status']) {
                    Notification::make()
                        ->title('Envío Exitoso')
                        ->body($responseData['message'])
                        ->success()
                        ->send();
                } else {
                    Notification::make()
                        ->title('Fallo en envío')
                        ->body($responseData['message'])
                        ->danger()
                        ->send();
                }
            });
    }

    public static function imprimirDTE(): Action
    {
        return Action::make('pdf')
            ->label('')
            ->icon('heroicon-o-printer')
            ->tooltip('Imprimir DTE')
            ->iconSize(IconSize::Large)
            ->visible(fn($record) => $record->is_dte)
            ->color('default')
            ->action(function ($record) {
                return redirect()->route('printDTE', ['idVenta' => $record->generationCode]);
            });
    }
}
