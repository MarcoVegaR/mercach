<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de Recibo • {{ $receipt->receipt_number }}</title>
    <link rel="icon" href="/favicon.ico" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; 
            color: #111827; 
            background: #f9fafb;
            line-height: 1.6;
        }
        .container { max-width: 800px; margin: 0 auto; padding: 2rem 1rem; }
        
        /* Header */
        .header { 
            background: white; 
            border-radius: 12px; 
            padding: 2rem; 
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .header-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .header h1 { font-size: 1.5rem; font-weight: 700; color: #111827; }
        .badge { 
            display: inline-block;
            padding: 0.375rem 0.75rem;
            background: #f3f4f6;
            border: 2px solid #111827;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .badge.verified { background: #d1fae5; border-color: #059669; color: #065f46; }
        .badge.void { background: #fee2e2; border-color: #dc2626; color: #991b1b; }
        .header-meta { font-size: 0.875rem; color: #6b7280; }
        
        /* Card */
        .card { 
            background: white; 
            border-radius: 12px; 
            padding: 1.5rem; 
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .card-title { 
            font-size: 1rem; 
            font-weight: 600; 
            color: #111827; 
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        /* Grid */
        .info-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 1.25rem;
        }
        .info-item { }
        .info-label { 
            font-size: 0.75rem; 
            font-weight: 500; 
            color: #6b7280; 
            text-transform: uppercase; 
            letter-spacing: 0.05em;
            margin-bottom: 0.25rem;
        }
        .info-value { 
            font-size: 0.9375rem; 
            color: #111827;
            word-break: break-word;
        }
        .info-value.mono { 
            font-family: ui-monospace, 'SF Mono', Menlo, Monaco, Consolas, monospace;
            font-size: 0.875rem;
            background: #f9fafb;
            padding: 0.375rem 0.5rem;
            border-radius: 4px;
            border: 1px solid #e5e7eb;
        }
        .info-value.large { font-size: 1.125rem; font-weight: 600; }
        
        /* Actions */
        .actions { 
            display: flex; 
            gap: 0.75rem; 
            flex-wrap: wrap;
        }
        .btn { 
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            font-size: 0.9375rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
            border: 1px solid;
        }
        .btn-primary { 
            background: #111827; 
            color: white; 
            border-color: #111827;
        }
        .btn-primary:hover { background: #1f2937; border-color: #1f2937; }
        .btn-secondary { 
            background: white; 
            color: #111827; 
            border-color: #e5e7eb;
        }
        .btn-secondary:hover { background: #f9fafb; }
        
        /* Alert */
        .alert { 
            padding: 1rem; 
            border-radius: 8px; 
            font-size: 0.875rem;
            line-height: 1.5;
        }
        .alert-info { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
        .alert-warning { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        
        /* Footer */
        .footer { 
            text-align: center; 
            padding: 2rem 1rem; 
            color: #6b7280; 
            font-size: 0.875rem;
        }
        
        /* Icons */
        .icon { width: 1.25rem; height: 1.25rem; display: inline-block; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-top">
                <h1>Verificación de Recibo</h1>
                @php
                    $status = strtoupper($summary['status'] ?? $receipt->status ?? '');
                    $isVoid = in_array($status, ['VOID', 'REPLACED'], true);
                @endphp
                <span class="badge {{ $isVoid ? 'void' : 'verified' }}">
                    {{ $isVoid ? '✕ ANULADO' : '✓ VERIFICADO' }}
                </span>
            </div>
            <div class="header-meta">
                <strong>{{ $receipt->receipt_number }}</strong> • 
                Emitido: {{ optional($receipt->issued_at)->format('d/m/Y H:i') }}
            </div>
        </div>

        <!-- Receipt Info -->
        <div class="card">
            <h2 class="card-title">Información del Recibo</h2>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Emisor</div>
                    <div class="info-value">{{ $issuer ?? 'No especificado' }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Tipo</div>
                    <div class="info-value">{{ $scope ?? 'PAYMENT' }} {{ $concept ? ('• '.$concept) : '' }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Estado</div>
                    <div class="info-value">{{ $status }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Plantilla</div>
                    <div class="info-value">{{ $summary['template_version'] ?? 'v1' }}</div>
                </div>
            </div>
        </div>

        @if ($scope === 'CHARGE' && $charge)
        <!-- Charge Details -->
        <div class="card">
            <h2 class="card-title">Detalle del Cargo</h2>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Monto del cargo</div>
                    <div class="info-value large">{{ number_format((data_get($charge,'amount_minor',0))/100, 2, ',', '.') }} {{ data_get($charge,'currency','') }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Aplicado (VES)</div>
                    <div class="info-value large">{{ number_format((data_get($charge,'applied_bs_minor',0))/100, 2, ',', '.') }} VES</div>
                </div>
                @if (!is_null(data_get($charge,'applied_currency_minor')))
                <div class="info-item">
                    <div class="info-label">Aplicado ({{ data_get($charge,'currency','') }})</div>
                    <div class="info-value">{{ number_format((data_get($charge,'applied_currency_minor',0))/100, 2, ',', '.') }} {{ data_get($charge,'currency','') }}</div>
                </div>
                @endif
                @if (!is_null(data_get($charge,'rate_to_ves')))
                <div class="info-item">
                    <div class="info-label">Tasa de cambio</div>
                    <div class="info-value">{{ number_format((float) data_get($charge,'rate_to_ves',0), 2, ',', '.') }} VES/{{ data_get($charge,'currency','') }}</div>
                </div>
                @endif
            </div>
        </div>
        @endif

        <!-- Technical Details -->
        <div class="card">
            <h2 class="card-title">Detalles Técnicos</h2>
            <div class="info-grid">
                <div class="info-item" style="grid-column: 1 / -1;">
                    <div class="info-label">Hash SHA-256</div>
                    <div class="info-value mono">{{ $summary['hash'] ?? 'No disponible' }}</div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        @if ($downloadUrl ?? null)
        <div class="card">
            <div class="actions">
                <a href="{{ $downloadUrl }}" class="btn btn-primary">
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Descargar PDF
                </a>
            </div>
        </div>
        @endif

        <!-- Alert -->
        @if ($isVoid)
        <div class="alert alert-warning">
            <strong>⚠️ Documento anulado:</strong> Este recibo ha sido marcado como ANULADO o REEMPLAZADO y no debe usarse como comprobante vigente. Si necesitas un comprobante válido, solicita una nueva emisión.
        </div>
        @else
        <div class="alert alert-info">
            <strong>ℹ️ Verificación exitosa:</strong> Este recibo es auténtico y ha sido verificado correctamente. Puedes descargar el PDF para conservarlo como comprobante oficial.
        </div>
        @endif

        <!-- Footer -->
        <div class="footer">
            <p>Sistema de Gestión de Recibos • MERCACH</p>
            <p style="margin-top: 0.5rem; font-size: 0.8125rem;">
                Esta página verifica la autenticidad del recibo mediante firma digital.
            </p>
        </div>
    </div>
</body>
</html>
