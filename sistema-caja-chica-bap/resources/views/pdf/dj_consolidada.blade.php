<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Declaración Jurada de Gastos</title>
    <style>
        body {
            font-family: 'Helvetica', sans-serif;
            font-size: 11px;
            line-height: 1.5;
            margin: 40px;
            color: #333;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            margin: 0;
            font-size: 16px;
            text-transform: uppercase;
            font-weight: bold;
        }

        .content p {
            text-align: justify;
            margin-bottom: 15px;
        }

        .signature {
            margin-top: 60px;
            text-align: center;
        }

        .signature-line {
            border-top: 1px solid #333;
            width: 250px;
            margin: 0 auto;
            padding-top: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            margin-bottom: 20px;
            font-size: 10px;
        }

        th,
        td {
            border: 1px solid #ccc;
            padding: 6px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }

        .total-row td {
            font-weight: bold;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .warning-box {
            border: 1px solid #c0392b;
            background-color: #fbeee6;
            color: #c0392b;
            padding: 10px;
            margin-bottom: 20px;
            font-size: 10px;
            text-align: center;
        }
    </style>
</head>

<body>

    <div class="header">
        <h1>Declaración Jurada de Gastos</h1>
    </div>

    <div class="content">
        <p>
            Yo, <strong>{{ $usuario_declarante->name }} {{ $usuario_declarante->last_name }}</strong>,
            identificado(a) con DNI N° <strong>{{ $usuario_declarante->numero_documento_identidad ?? '___________' }}</strong>,
            en pleno uso de mis facultades, declaro bajo juramento que los gastos detallados a continuación fueron realizados para fines exclusivamente laborales y en representación del Banco de Alimentos Perú.
        </p>
        <p>
            Asimismo, declaro que no se pudo obtener un comprobante de pago válido (boleta de venta o factura) para las siguientes transacciones y que <strong>las fechas indicadas en la columna 'Fecha Transacción' corresponden al día en que se realizó efectivamente cada operación</strong>, asumiendo la total responsabilidad sobre la veracidad de la información presentada.
        </p>

        @php
        $mesDeclaracion = $fecha_declaracion->month;
        $hayGastosFueraDeMes = $gastos->contains(function ($gasto) use ($mesDeclaracion) {
        // Se accede a la fecha de forma segura, ya sea un objeto o un array
        $fechaGasto = is_object($gasto) ? $gasto->fecha_documento : $gasto['fecha_documento'];
        return \Carbon\Carbon::parse($fechaGasto)->month != $mesDeclaracion;
        });
        @endphp

        @if($hayGastosFueraDeMes)
        <div class="warning-box">
            <strong>ATENCIÓN:</strong> Esta declaración incluye gastos correspondientes a un período contable anterior, los cuales han sido debidamente autorizados por la Jefatura de Administración para su regularización.
        </div>
        @endif

        <table>
            <thead>
                <tr>
                    <th class="text-center">N°</th>
                    <th>Gasto Proyectado</th>
                    <th>Descripción del Gasto (Glosa)</th>
                    <th class="text-center">Fecha Transacción</th>
                    <th class="text-right">Monto (S/)</th>
                </tr>
            </thead>
            <tbody>
                {{-- INICIO DE CAMBIOS --}}
                @foreach ($gastos as $index => $gasto)
                @php
                // Se definen las variables de forma segura para manejar tanto arrays como objetos
                $descripcionProyectado = is_object($gasto) ? ($gasto->gastoProyectado->descripcion ?? 'N/A') : ($gasto['gasto_proyectado_descripcion'] ?? 'N/A');
                $glosa = is_object($gasto) ? $gasto->glosa : $gasto['glosa'];
                $fechaDocumento = is_object($gasto) ? $gasto->fecha_documento : $gasto['fecha_documento'];
                $montoTotal = is_object($gasto) ? $gasto->monto_total : $gasto['monto_total'];
                @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $descripcionProyectado }}</td>
                    <td>{{ $glosa }}</td>
                    <td class="text-center">{{ \Carbon\Carbon::parse($fechaDocumento)->format('d/m/Y') }}</td>
                    <td class="text-right">{{ number_format($montoTotal, 2) }}</td>
                </tr>
                @endforeach
                {{-- FIN DE CAMBIOS --}}
                <tr class="total-row">
                    <td colspan="4" class="text-right"><strong>TOTAL GENERAL</strong></td>
                    <td class="text-right"><strong>S/ {{ number_format($totalGeneral, 2) }}</strong></td>
                </tr>
            </tbody>
        </table>

        <p>
            Firmo la presente declaración en señal de conformidad, en la ciudad de Lima, a los {{ $fecha_declaracion->isoFormat('D [días del mes de] MMMM [de] YYYY') }}.
        </p>
    </div>

    <div class="signature">
        <div class="signature-line">
            Firma del Declarante<br>
            {{ $usuario_declarante->name }} {{ $usuario_declarante->last_name }}
        </div>
    </div>

</body>

</html>