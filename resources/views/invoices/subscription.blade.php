<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: -apple-system, Arial, sans-serif; color: #1e293b; background: #fff; font-size: 13px; }
.page { max-width: 680px; margin: 0 auto; padding: 48px 40px; }
.header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; border-bottom: 2px solid #7c3aed; padding-bottom: 24px; }
.brand { font-size: 22px; font-weight: 800; color: #7c3aed; }
.brand-sub { font-size: 11px; color: #64748b; margin-top: 2px; }
.invoice-label { font-size: 20px; font-weight: 700; color: #0f172a; text-align: right; }
.invoice-num { font-size: 12px; color: #64748b; margin-top: 4px; text-align: right; }
.meta { display: flex; justify-content: space-between; margin-bottom: 32px; }
.meta-block h4 { font-size: 10px; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; margin-bottom: 6px; }
.meta-block p { font-size: 13px; color: #1e293b; line-height: 1.6; }
.table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
.table th { background: #f8fafc; text-align: left; padding: 10px 12px; font-size: 10px; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b; border-bottom: 1px solid #e2e8f0; }
.table td { padding: 12px; border-bottom: 1px solid #f1f5f9; color: #1e293b; }
.table td.right { text-align: right; }
.totals { margin-left: auto; width: 280px; }
.total-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 12px; color: #64748b; }
.total-row.final { border-top: 2px solid #7c3aed; margin-top: 8px; padding-top: 10px; font-size: 15px; font-weight: 700; color: #0f172a; }
.badge { display: inline-block; background: #dcfce7; color: #166534; font-size: 10px; font-weight: 600; padding: 3px 10px; border-radius: 12px; }
.footer { margin-top: 48px; padding-top: 20px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; font-size: 10px; color: #94a3b8; }
</style>
</head>
<body>
<div class="page">
    <div class="header">
        <div>
            <div class="brand">heyd2c</div>
            <div class="brand-sub">SaltyPay Software Private Limited</div>
            <div style="font-size:11px;color:#64748b;margin-top:8px;line-height:1.6;">
                GST: 27AAAAA0000A1Z5<br>
                Maharashtra, India
            </div>
        </div>
        <div>
            <div class="invoice-label">TAX INVOICE</div>
            <div class="invoice-num">{{ $invoice->invoice_number }}</div>
            <div class="invoice-num" style="margin-top:4px;">
                <span class="badge">PAID</span>
            </div>
        </div>
    </div>

    <div class="meta">
        <div class="meta-block">
            <h4>Bill To</h4>
            <p>
                <strong>{{ $company->name }}</strong><br>
                {{ $company->email }}<br>
                @if($company->gstin)<strong>GSTIN:</strong> {{ $company->gstin }}@endif
            </p>
        </div>
        <div class="meta-block" style="text-align:right;">
            <h4>Invoice Details</h4>
            <p>
                <strong>Date:</strong> {{ $invoice->paid_at ? \Carbon\Carbon::parse($invoice->paid_at)->format('d M Y') : '—' }}<br>
                <strong>Payment ID:</strong> {{ $invoice->razorpay_payment_id ?? '—' }}<br>
                <strong>Status:</strong> Paid
            </p>
        </div>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Description</th>
                <th class="right">Period</th>
                <th class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <strong>{{ $plan?->name ?? 'Subscription' }} Plan</strong><br>
                    <span style="font-size:11px;color:#64748b;">heyd2c D2C Operations Platform</span>
                </td>
                <td class="right" style="font-size:11px;color:#64748b;">Monthly</td>
                <td class="right">₹{{ number_format($invoice->subtotal, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="totals">
        <div class="total-row">
            <span>Subtotal</span>
            <span>₹{{ number_format($invoice->subtotal, 2) }}</span>
        </div>
        @if($invoice->cgst || $invoice->sgst)
        <div class="total-row">
            <span>CGST (9%)</span>
            <span>₹{{ number_format($invoice->cgst, 2) }}</span>
        </div>
        <div class="total-row">
            <span>SGST (9%)</span>
            <span>₹{{ number_format($invoice->sgst, 2) }}</span>
        </div>
        @endif
        @if($invoice->igst)
        <div class="total-row">
            <span>IGST (18%)</span>
            <span>₹{{ number_format($invoice->igst, 2) }}</span>
        </div>
        @endif
        <div class="total-row final">
            <span>Total</span>
            <span>₹{{ number_format($invoice->total, 2) }}</span>
        </div>
    </div>

    <div class="footer">
        <span>heyd2c · SaltyPay Software Private Limited · support@heyd2c.ai</span>
        <span>This is a computer-generated invoice</span>
    </div>
</div>
</body>
</html>
