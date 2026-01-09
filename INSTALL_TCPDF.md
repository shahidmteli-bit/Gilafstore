# Install TCPDF for Invoice Generation

## Option 1: Using Composer (Recommended)

1. Open Command Prompt in your project directory:
   ```
   cd C:\xampp\htdocs\Gilaf Ecommerce website
   ```

2. Install TCPDF via Composer:
   ```
   composer require tecnickcom/tcpdf
   ```

## Option 2: Manual Installation

1. Download TCPDF from: https://github.com/tecnickcom/TCPDF/releases
2. Extract the ZIP file
3. Create a folder: `C:\xampp\htdocs\Gilaf Ecommerce website\vendor\tecnickcom\tcpdf`
4. Copy all TCPDF files into that folder

## Option 3: Simple Alternative (No Composer)

If you don't have Composer, I can create a simpler invoice system using HTML and a library like mPDF or FPDF.

## After Installation

Once TCPDF is installed, the invoice system will work automatically:
- Click "Download Invoice" on any order details page
- PDF will be generated with all order details
- Invoice includes: Order number, EAN, GST, timestamps, customer details, product details

## Test the Invoice

1. Go to any order details page
2. Click the "Download Invoice" button
3. A professional PDF invoice will be downloaded
