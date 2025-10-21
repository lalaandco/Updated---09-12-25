<?php session_start(); ?>
<?php include 'admin_header.php'; ?>
<?php

// Get order for invoice generation
$order_id = $_GET['order_id'] ?? null;
$order_data = null;
$order_items = [];

if ($order_id) {
    // Fetch order details
    $order_query = "
        SELECT o.*, u.name as customer_name, u.contact_number
        FROM orders o
        LEFT JOIN users u ON o.user_email = u.email
        WHERE o.order_id = ?
    ";
    $stmt = $conn->prepare($order_query);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $order_data = $stmt->get_result()->fetch_assoc();
    
    // Fetch order items
    $items_query = "SELECT * FROM order_items WHERE order_id = ?";
    $stmt = $conn->prepare($items_query);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $order_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get all completed orders for selection
$orders_query = "
    SELECT o.order_id, o.full_name, o.user_email, o.order_date, o.total_amount, o.status
    FROM orders o
    WHERE o.status IN ('confirmed', 'processing', 'shipped', 'delivered')
    ORDER BY o.order_date DESC
    LIMIT 50
";
$available_orders = $conn->query($orders_query)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="adminStyle.css">
    <title>Create Invoice - Admin Panel</title>
    <style>
    /* ========== PRINT STYLES - CRITICAL FOR INVOICE PRINTING ========== */
    @media print {
        /* Hide everything except invoice */
        body > *:not(.invoice-preview) {
            display: none !important;
        }
        
        .header,
        .order-selector,
        .invoice-actions,
        button,
        select {
            display: none !important;
        }
        
        /* Reset body for clean print */
        body {
            margin: 0;
            padding: 0;
            background: white !important;
        }
        
        /* Make invoice visible and print-ready */
        .invoice-container {
            display: block !important;
            max-width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        
        .invoice-preview {
            display: block !important;
            box-shadow: none !important;
            margin: 0 !important;
            padding: 40px !important;
            max-width: 100% !important;
            background: white !important;
            page-break-after: avoid;
        }
        
        /* Prevent awkward page breaks */
        .invoice-header,
        .billing-section,
        .items-table thead,
        .totals-section {
            page-break-inside: avoid;
        }
        
        .items-table tr {
            page-break-inside: avoid;
        }
        
        /* Ensure colors print correctly */
        .invoice-header {
            border-bottom: 3px solid #4CAF50 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        
        .items-table th {
            background: #f8f9fa !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        
        .total-row.grand-total {
            border-top: 2px solid #4CAF50 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        
        /* Fix font sizes for print */
        h1, h2, h3 {
            color: #000 !important;
        }
        
        /* Ensure all text is visible */
        * {
            color: #000 !important;
        }
    }
    
    /* ========== SCREEN STYLES ========== */
    .invoice-container {
        max-width: 1200px;
        margin: 20px auto;
        padding: 20px;
    }
    
    .order-selector {
        background: white;
        padding: 25px;
        border-radius: 10px;
        margin-bottom: 30px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .order-selector h3 {
        margin-bottom: 20px;
    }
    
    .order-selector select {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 14px;
    }
    
    .invoice-preview {
        background: white;
        padding: 40px;
        border-radius: 10px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }
    
    .invoice-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 40px;
        padding-bottom: 20px;
        border-bottom: 3px solid #4CAF50;
    }
    
    .company-info h1 {
        color: #4CAF50;
        margin-bottom: 10px;
    }
    
    .invoice-details {
        text-align: right;
    }
    
    .invoice-number {
        font-size: 24px;
        font-weight: bold;
        color: #333;
    }
    
    .billing-section {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 40px;
        margin-bottom: 40px;
    }
    
    .billing-info h3 {
        color: #4CAF50;
        margin-bottom: 15px;
        font-size: 16px;
    }
    
    .billing-info p {
        margin: 5px 0;
        color: #666;
    }
    
    .items-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 30px;
    }
    
    .items-table thead {
        background: #f8f9fa;
    }
    
    .items-table th {
        padding: 15px;
        text-align: left;
        font-weight: 600;
        border-bottom: 2px solid #dee2e6;
    }
    
    .items-table td {
        padding: 12px 15px;
        border-bottom: 1px solid #dee2e6;
    }
    
    .totals-section {
        display: flex;
        justify-content: flex-end;
        margin-top: 20px;
    }
    
    .totals-box {
        width: 300px;
    }
    
    .total-row {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
    }
    
    .total-row.grand-total {
        border-top: 2px solid #4CAF50;
        margin-top: 10px;
        padding-top: 15px;
        font-size: 18px;
        font-weight: bold;
    }
    
    .invoice-actions {
        display: flex;
        gap: 15px;
        justify-content: center;
        margin-top: 30px;
    }
    
    .btn {
        padding: 12px 30px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 14px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }
    
    .btn-primary {
        background: #4CAF50;
        color: white;
    }
    
    .btn-secondary {
        background: #2196F3;
        color: white;
    }
    
    .btn:hover {
        opacity: 0.9;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #666;
    }
    
    .empty-state i {
        font-size: 64px;
        margin-bottom: 20px;
        color: #ddd;
    }
</style>
</head>
<body>

    <div class="invoice-container">
        <div class="order-selector">
            <h3><i class='bx bx-file-find'></i> Select Order to Generate Invoice</h3>
            <select onchange="window.location.href='adminCreateInvoice.php?order_id=' + this.value">
                <option value="">-- Select an Order --</option>
                <?php foreach ($available_orders as $order): ?>
                    <option value="<?php echo $order['order_id']; ?>" 
                            <?php echo ($order_id == $order['order_id']) ? 'selected' : ''; ?>>
                        Order #<?php echo str_pad($order['order_id'], 8, '0', STR_PAD_LEFT); ?> - 
                        <?php echo htmlspecialchars($order['full_name']); ?> - 
                        ₱<?php echo number_format($order['total_amount'], 2); ?> - 
                        <?php echo date('M d, Y', strtotime($order['order_date'])); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($order_data): ?>
            <div class="invoice-preview" id="invoiceContent">
                <div class="invoice-header">
                    <div class="company-info">
                        <h1>Lalal & Co.</h1>
                        <p>Perfume & Fragrance Shop</p>
                        <p>Email: contact@lagalco.com</p>
                        <p>Phone: +63 XXX XXX XXXX</p>
                    </div>
                    <div class="invoice-details">
                        <div class="invoice-number">INVOICE</div>
                        <p><strong>#<?php echo str_pad($order_data['order_id'], 8, '0', STR_PAD_LEFT); ?></strong></p>
                        <p>Date: <?php echo date('M d, Y', strtotime($order_data['order_date'])); ?></p>
                        <p>Status: <strong><?php echo strtoupper($order_data['status']); ?></strong></p>
                    </div>
                </div>

                <div class="billing-section">
                    <div class="billing-info">
                        <h3>Bill To:</h3>
                        <p><strong><?php echo htmlspecialchars($order_data['full_name']); ?></strong></p>
                        <p><?php echo htmlspecialchars($order_data['address']); ?></p>
                        <p><?php echo htmlspecialchars($order_data['city']); ?> <?php echo htmlspecialchars($order_data['postal_code']); ?></p>
                        <p>Email: <?php echo htmlspecialchars($order_data['user_email']); ?></p>
                        <p>Phone: <?php echo htmlspecialchars($order_data['phone']); ?></p>
                    </div>
                    
                    <div class="billing-info">
                        <h3>Payment Information:</h3>
                        <p><strong>Method:</strong> <?php echo strtoupper($order_data['payment_method']); ?></p>
                        <?php if ($order_data['tracking_number']): ?>
                            <p><strong>Tracking:</strong> <?php echo htmlspecialchars($order_data['tracking_number']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <table class="items-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th style="text-align: right;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $item_num = 1; ?>
                        <?php foreach ($order_items as $item): ?>
                            <tr>
                                <td><?php echo $item_num++; ?></td>
                                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                <td>₱<?php echo number_format($item['product_price'], 2); ?></td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td style="text-align: right;">₱<?php echo number_format($item['subtotal'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="totals-section">
                    <div class="totals-box">
                        <div class="total-row">
                            <span>Subtotal:</span>
                            <span>₱<?php echo number_format($order_data['total_amount'], 2); ?></span>
                        </div>
                        <div class="total-row">
                            <span>Shipping:</span>
                            <span>₱0.00</span>
                        </div>
                        <div class="total-row grand-total">
                            <span>TOTAL:</span>
                            <span>₱<?php echo number_format($order_data['total_amount'], 2); ?></span>
                        </div>
                    </div>
                </div>

                <?php if ($order_data['order_notes']): ?>
                    <div style="margin-top: 30px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
                        <strong>Notes:</strong>
                        <p><?php echo nl2br(htmlspecialchars($order_data['order_notes'])); ?></p>
                    </div>
                <?php endif; ?>

                <div style="margin-top: 50px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; color: #666; font-size: 12px;">
                    <p>Thank you for your business!</p>
                    <p>This is a computer-generated invoice and does not require a signature.</p>
                </div>
            </div>

            <div class="invoice-actions">
                <button class="btn btn-primary" onclick="window.print()">
                    <i class='bx bx-printer'></i> Print Invoice
                </button>
                <button class="btn btn-secondary" onclick="downloadPDF()">
                    <i class='bx bx-download'></i> Download PDF
                </button>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class='bx bx-file'></i>
                <h3>No Order Selected</h3>
                <p>Please select an order from the dropdown above to generate an invoice.</p>
            </div>
        <?php endif; ?>

        <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
            
    </div>

    <script>
        function downloadPDF() {
            // Modern browsers support Print to PDF natively
            // This guides the user to use the browser's PDF feature
            
            const userAgent = navigator.userAgent.toLowerCase();
            
            if (userAgent.includes('chrome') || userAgent.includes('edge')) {
                alert('To save as PDF:\n\n1. Click "Print Invoice" button\n2. In the print dialog, select "Save as PDF" as the destination\n3. Click "Save"\n\nNote: Make sure "Background graphics" is enabled in print settings for best results.');
            } else if (userAgent.includes('firefox')) {
                alert('To save as PDF:\n\n1. Click "Print Invoice" button\n2. In the print dialog, select "Save to PDF" or "Microsoft Print to PDF"\n3. Click "Save"\n\nNote: Enable "Print backgrounds" in the print preview for full colors.');
            } else {
                alert('To save as PDF:\n\n1. Click "Print Invoice" button\n2. Select "Save as PDF" or a PDF printer in the print dialog\n3. Click "Save"');
            }
            
            // Trigger print dialog
            window.print();
        }


        function downloadPDFAdvanced() {
            // Add this to <head>: 
            // Add this to <head>: 
            const { jsPDF } = window.jspdf;
            const invoice = document.getElementById('invoiceContent');
            
            html2canvas(invoice, {
                scale: 2,
                useCORS: true,
                logging: false
            }).then(canvas => {
                const imgData = canvas.toDataURL('image/png');
                const pdf = new jsPDF('p', 'mm', 'a4');
                const imgWidth = 210; // A4 width in mm
                const imgHeight = (canvas.height * imgWidth) / canvas.width;
                
                pdf.addImage(imgData, 'PNG', 0, 0, imgWidth, imgHeight);
                pdf.save('invoice.pdf');
            });
        }
</script>
</body>
</html>