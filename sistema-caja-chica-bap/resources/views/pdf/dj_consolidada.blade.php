<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Declaración Jurada de Gastos</title>
    <style>
        body {
            font-family: 'Helvetica', sans-serif;
            font-size: 12px;
            line-height: 1.6;
            margin: 40px;
            color: #333;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
        }

        .header h1 {
            margin: 0;
            font-size: 18px;
            text-transform: uppercase;
        }

        .content p {
            text-align: justify;
            margin-bottom: 20px;
        }

        .signature {
            margin-top: 80px;
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
        }

        th,
        td {
            border: 1px solid #ccc;
            padding: 8px;
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
    </style>
</head>

<body>

    <div class="header">
        <h1>Declaración Jurada de Gastos</h1>
    </div>

    <div class="content">
        <p>
            Yo, <strong>{{ $nombreCompleto }}</strong>, identificado(a) con DNI N° <strong>{{ $dni ?? '___________' }}</strong>, en pleno uso de mis facultades, declaro bajo juramento que los gastos detallados a continuación fueron realizados para fines exclusivamente laborales y en representación del Banco de Alimentos Perú.
        </p>
        <p>
            Asimismo, declaro que no se pudo obtener un comprobante de pago válido (boleta de venta o factura) para las siguientes transacciones, por lo que asumo la total responsabilidad sobre la veracidad de la información presentada.
        </p>

        <table>
            <thead>
                <tr>
                    <th>N°</th>
                    <!-- MODIFICACIÓN: Nueva columna -->
                    <th>Gasto Proyectado</th>
                    <th>Descripción del Gasto (Glosa)</th>
                    <th class="text-right">Monto (S/)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($gastos as $index => $gasto)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <!-- MODIFICACIÓN: Nuevo campo a mostrar -->
                    <td>{{ $gasto['gasto_proyectado_descripcion'] }}</td>
                    <td>{{ $gasto['glosa'] }}</td>
                    <td class="text-right">{{ number_format($gasto['monto_total'], 2) }}</td>
                </tr>
                @endforeach
                <tr class="total-row">
                    <!-- MODIFICACIÓN: Colspan ajustado a 3 -->
                    <td colspan="3" class="text-right"><strong>TOTAL GENERAL</strong></td>
                    <td class="text-right"><strong>S/ {{ number_format($totalGeneral, 2) }}</strong></td>
                </tr>
            </tbody>
        </table>

        <p>
            Firmo la presente declaración en señal de conformidad, en la ciudad de Lima, a los {{ $fecha }}.
        </p>
    </div>

    <div class="signature">
        <div class="signature-line">
            Firma del Declarante<br>
            {{ $nombreCompleto }}<br>
            DNI: {{ $dni ?? '___________' }}
        </div>
    </div>

</body>

</html>