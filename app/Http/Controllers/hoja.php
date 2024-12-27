<?php
namespace App\Http\Controllers;

use App\Models\hoja1;
use App\Models\Inventory;
use App\Models\Price;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class hoja extends Controller
{
    //
    public function ejecutar()
    {
        set_time_limit(0);
        $productos = hoja1::all();
        $items = [];

        foreach ($productos as $producto) {
            try {
                $nuevo = new Product();
                $nuevo->name = trim($producto->Produ);
                $nuevo->aplications = "";
                $nuevo->sku = trim($producto->sku);
                $nuevo->bar_code = $producto->codigo_barras;
                $nuevo->is_service = false;
                $nuevo->category_id = ($producto->Linea == 0) ? 52 : $producto->Linea;
                $nuevo->marca_id = ($producto->marca == 0) ? 49 : $producto->marca;
                $nuevo->unit_measurement_id = 1;
                $nuevo->is_taxed = true;
                $nuevo->images = null;
                $nuevo->is_active = true;
//                $nuevo->save();

                //llenar el inventario
                $inventario = new Inventory();
                $inventario->product_id = $nuevo->id;
                $inventario->branch_id = 3;
                $inventario->cost_without_taxes = $producto->Costo;
                $inventario->cost_with_taxes = $producto->CostoIVA;
                $inventario->stock = $producto->Existencia;
                $inventario->stock_min = $producto->ExisteMinima??0;
                $inventario->stock_max = $producto->E_Maxima??0;
                $inventario->is_stock_alert =true;
                $inventario->is_expiration_date = false;
                $inventario->is_active = true;
//                $inventario->save();
                //llenar los precios
                $precio = new Price();
                $precio->inventory_id = $inventario->id;
                $precio->name = "Público";
                $precio->price = $producto->PrecioIVA;
                $precio->is_default = true;
                $precio->is_active = true;
//                $precio->save();

            } catch (\Exception $e) {
                $items[] = $producto->id; // Use the actual product ID for tracking failures
                Log::error("Failed to save product ID {$producto->id}: " . $e->getMessage());
            }
        }

// Output any failed product IDs
        if (!empty($items)) {
            dd($items); // Output failed IDs after processing all products
        }


    }
}
