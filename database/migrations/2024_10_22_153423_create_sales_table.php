<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cashbox_open_id')->nullable()->constrained('cash_box_opens')->cascadeOnDelete();//Apertura de caja
            $table->date('operation_date')->default(now());
            $table->foreignId('document_type_id')->nullable()->constrained('document_types')->cascadeOnDelete();//factura, nota de venta, etc
            $table->string('document_internal_number')->nullable(); //Controll interno correlativos caja
            $table->foreignId('wherehouse_id')->constrained('branches')->cascadeOnDelete();//Sucursal
            $table->foreignId('seller_id')->constrained('employees')->cascadeOnDelete();//Vendedor
            $table->foreignId('customer_id')->nullable()->constrained('customers')->cascadeOnDelete();//Cliente
            $table->foreignId('operation_condition_id')->nullable()->constrained('operation_conditions')->cascadeOnDelete();//Condicion de operacion contado, credito
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->cascadeOnDelete();//Metodo de pago cheque, efectivo, tarjeta
            $table->enum('sales_payment_status',['Pagado','Pendiente','Abono'])->nullable();
            $table->enum('status',['Nuevo','Procesando','Cancelado','Facturado','Anulado'])->default('Nuevo');
            $table->boolean('is_taxed')->default(true);
            $table->boolean('have_retention')->default(false);
            $table->decimal('net_amount',10,2)->default(0);
            $table->decimal('taxe',10,2)->default(0);
            $table->decimal('discount',10,2)->default(0);
            $table->decimal('retention',10,2)->default(0);
            $table->decimal('sale_total',10,2)->default(0);
            $table->decimal('cash',10,2)->default(0);
            $table->decimal('change',10,2)->default(0);
            $table->foreignId('casher_id')->nullable()->constrained('employees')->cascadeOnDelete();//Cajero
            $table->boolean('is_dte')->default(false);
            $table->string('generationCode')->nullable();
            $table->string('receiptStamp')->nullable();
            $table->string('jsonUrl')->nullable();
            $table->boolean('is_order')->default(false);
            $table->boolean('is_order_closed_without_invoiced')->default(false);
            $table->boolean('is_invoiced_order')->default(false);
            $table->string('order_number')->nullable();//NUmero de la orden es difernte en cada sucrusal
            $table->decimal('discount_percentage',10,2)->default(0);
            $table->decimal('discount_money',10,2)->default(0);
            $table->decimal('total_order_after_discount',10,2)->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
