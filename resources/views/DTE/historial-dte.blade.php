<div class="overflow-x-auto max-w-full">
    <table class="table-auto text-xs">
        <thead>
        <tr>
{{--            <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Venta</th>--}}
            <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Versión</th>
            <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Ambiente</th>
            <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Versión App</th>
            <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Estado</th>
            <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Código Generación</th>
            <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Sello Recibido</th>
            <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Fecha Procesamiento</th>
            <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Clasifica Mensaje</th>
            <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Código Mensaje</th>
            <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Descripción Mensaje</th>
            <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Observaciones</th>
        </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
        @foreach ($historial as $item)
            <tr style="{{$item['estado'] == 'RECHAZADO' ? 'background-color: rgb(254 202 202); ' : 'background-color: rgb(134 239 172);'}}">

{{--            <td class="px-4 py-2">{{$item["sales_invoice_id"]}}</td>--}}
                <td class="px-4 py-2">{{$item["version"]}}</td>
                <td class="px-4 py-2">{{$item["ambiente"]}}</td>
                <td class="px-4 py-2">{{$item["versionApp"]}}</td>
                <td class="px-4 py-2">{{$item["estado"]}}</td>
                <td class="px-4 py-2">{{$item["codigoGeneracion"]}}</td>
                <td class="px-4 py-2">{{$item["selloRecibido"]}}</td>
                <td class="px-4 py-2">{{$item["fhProcesamiento"]}}</td>
                <td class="px-4 py-2">{{$item["clasificaMsg"]}}</td>
                <td class="px-4 py-2">{{$item["codigoMsg"]}}</td>
                <td class="px-4 py-2">{{$item["descripcionMsg"]}}</td>
                <td class="px-4 py-2">{{$item["observaciones"]}}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
