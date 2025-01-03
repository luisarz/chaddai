<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class TransferController extends Controller
{
 public function printTransfer($id)


        if (Storage::disk('public')->exists($fileName)) {
            $fileContent = Storage::disk('public')->get($fileName);
            $DTE = json_decode($fileContent, true); // Decodificar JSON en un array asociativo
            $tipoDocumento = $DTE['identificacion']['tipoDte'] ?? 'DESCONOCIDO';
            $logo = auth()->user()->employee->wherehouse->logo;
            $tiposDTE = [
                '03' => 'COMPROBANTE DE CREDITO  FISCAL',
                '01' => 'FACTURA',
                '02' => 'NOTA DE DEBITO',
                '04' => 'NOTA DE CREDITO',
                '05' => 'LIQUIDACION DE FACTURA',
                '06' => 'LIQUIDACION DE FACTURA SIMPLIFICADA'
            ];
            $tipoDocumento = $this->searchInArray($tipoDocumento, $tiposDTE);
            $contenidoQR = "https://admin.factura.gob.sv/consultaPublica?ambiente=00&codGen=" . $DTE['identificacion']['codigoGeneracion'] . "&fechaEmi=" . $DTE['identificacion']['fecEmi'];

            $datos = [
                'empresa' => $DTE["emisor"], // O la función correspondiente para cargar datos globales de la empresa.
                'DTE' => $DTE,
                'tipoDocumento' => $tipoDocumento,
                'logo' => Storage::url($logo),
            ];
            $directory = storage_path('app/public/QR');

            if (!file_exists($directory)) {
                mkdir($directory, 0755, true); // Create the directory with proper permissions
            }
            $path = $directory . '/' . $DTE['identificacion']['codigoGeneracion'] . '.png';

            QrCode::size(300)->generate($contenidoQR, $path);

            $qr = Storage::url("QR/{$DTE['identificacion']['codigoGeneracion']}.png");

            $pdf = Pdf::loadView('DTE.dte-print-pdf', compact('datos', 'qr')); // Cargar vista y pasar datos
            $path = storage_path("app/public/DTEs/{$codGeneracion}.pdf");
            if (file_exists($path)) {
                return response()->file($path);
            } else {
                $pdf->save($path);
            }

            $empresa = $this->getConfiguracion();

            return $pdf->stream("{$codGeneracion}.pdf"); // El PDF se abre en una nueva pestaña
        } else {
            return response()->json(['error' => 'El archivo no existe.'], 404); // Retornar error 404
        }


    }
}
