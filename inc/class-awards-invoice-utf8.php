<?php
/**
 * UTF-8 compatible version of the Awards Invoice Generator
 * 
 * This class extends FPDF to properly handle UTF-8 characters in invoices
 * Put this file in the inc directory of your plugin
 */

if (!class_exists('FPDF')) {
    require_once(ABSPATH . 'wp-content/plugins/awards-signup2/inc/vendor/fpdf/fpdf.php');
}

/**
 * UTF8 FPDF Extension
 * Extends FPDF to add UTF-8 support
 */
class FPDF_UTF8 extends FPDF {
    /**
     * Cell with UTF-8 support
     */
    function Cell($w, $h=0, $txt='', $border=0, $ln=0, $align='', $fill=false, $link='') {
        // Convert UTF-8 to ISO-8859-1 (or Windows-1252)
        $txt = $this->utf8Decode($txt);
        parent::Cell($w, $h, $txt, $border, $ln, $align, $fill, $link);
    }

    /**
     * Multi-cell with UTF-8 support
     */
    function MultiCell($w, $h, $txt, $border=0, $align='J', $fill=false) {
        // Convert UTF-8 to ISO-8859-1
        $txt = $this->utf8Decode($txt);
        parent::MultiCell($w, $h, $txt, $border, $align, $fill);
    }

    /**
     * Convert UTF-8 to ISO-8859-1
     * Handles special characters correctly
     */
    protected function utf8Decode($text) {
        // Try iconv first (better character handling)
        if (function_exists('iconv')) {
            // Replace the character set below if you need a different one
            // Windows-1252 has better character support than ISO-8859-1
            $out = iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
            if ($out !== false) {
                return $out;
            }
        }
        
        // Fallback to utf8_decode
        return utf8_decode($text);
    }
}

/**
 * Awards Invoice Generator with UTF-8 Support
 */
class Awards_Invoice_UTF8 {
    // Private properties
    private $entry_id;
    private $payment_details;
    private $pdf;
    private $user_data;
    private $entry_data;
    private $currency_symbol;
    private $currency_code;
    private $is_mass_payment;
    private $entry_ids;
    private $entry_fees;
    
    /**
     * Constructor
     * 
     * @param int|array $entry_id Entry ID or array of entry IDs for mass payments
     * @param array $payment_details Payment details
     * @param array $entry_fees Optional array of entry fees for mass payments
     */
    public function __construct($entry_id, $payment_details, $entry_fees = array()) {
        // Initialize FPDF with UTF-8 support
        $this->pdf = new FPDF_UTF8();
        $this->pdf->AddPage();
        $this->pdf->AliasNbPages(); // For page numbering
        
        // Set properties
        $this->payment_details = $payment_details;
        
        // Check if this is a mass payment
        $this->is_mass_payment = is_array($entry_id) && count($entry_id) > 1;
        
        if ($this->is_mass_payment) {
            $this->entry_ids = $entry_id;
            $this->entry_id = $entry_id[0]; // Use first entry for user data
            $this->entry_fees = $entry_fees;
        } else {
            $this->entry_id = is_array($entry_id) ? $entry_id[0] : $entry_id;
            $this->entry_ids = array($this->entry_id);
        }
        
        // Load entry and user data
        $this->load_data();
    }
    
    /**
     * Load entry and user data
     */
    private function load_data() {
        // Get entry data
        $this->entry_data = get_post($this->entry_id);
        
        if (!$this->entry_data) {
            throw new Exception(__('Entry not found', 'awards'));
        }
        
        // Get user data
        $user_id = $this->entry_data->post_author;
        $this->user_data = get_userdata($user_id);
        
        if (!$this->user_data) {
            throw new Exception(__('User not found', 'awards'));
        }
        
        // Set currency based on user country and default settings
        $user_country = get_user_meta($user_id, 'country', true);
        $default_currency = awards_options('currency');
        
        if ($user_country === 'Iran (Islamic Republic of)') {
            $this->currency_code = 'IRR';
            $this->currency_symbol = 'IRR ';
        } else if ($default_currency === 'euro' || $default_currency === 'eur') {
            $this->currency_code = 'EUR';
            $this->currency_symbol = 'EUR '; // Using plain text for better compatibility
        } else {
            $this->currency_code = 'USD';
            $this->currency_symbol = '$';
        }
    }
    
    /**
     * Generate a single-entry invoice
     */
    private function generate_single_invoice($file_path) {
        // Add header
        $this->add_header();
        
        // Add invoice information
        $this->add_invoice_info();
        
        // Add customer and vendor information
        $this->add_customer_vendor_info();
        
        // Add entry details
        $this->add_entry_details();
        
        // Add payment summary
        $this->add_payment_summary();
        
        // Add transaction details
        $this->add_transaction_details();
        
        // Add footer
        $this->add_footer();
        
        // Save the PDF
        $this->pdf->Output('F', $file_path);
        
        return $file_path;
    }
    
    /**
     * Generate a mass payment invoice
     */
    private function generate_mass_invoice($file_path) {
        // Add header
        $this->add_header();
        
        // Add invoice information (with mass payment indicator)
        $this->add_invoice_info(true);
        
        // Add customer and vendor information
        $this->add_customer_vendor_info();
        
        // Add mass payment summary
        $this->add_mass_payment_summary();
        
        // Add transaction details
        $this->add_transaction_details(true);
        
        // Add footer
        $this->add_footer();
        
        // Save the PDF
        $this->pdf->Output('F', $file_path);
        
        return $file_path;
    }
    
    /**
     * Generate the invoice
     * 
     * @return string Path to the generated PDF file
     */
    public function generate() {
        // Create directories if they don't exist
        $upload_dir = wp_upload_dir();
        $invoice_dir = $upload_dir['basedir'] . '/awards-invoices/' . $this->user_data->ID;
        
        if (!file_exists($invoice_dir)) {
            wp_mkdir_p($invoice_dir);
        }
        
        // Create index.php file to prevent directory listing
        if (!file_exists($invoice_dir . '/index.php')) {
            file_put_contents($invoice_dir . '/index.php', '<?php // Silence is golden.');
        }
        
        // Generate file name with prefix based on payment type
        $prefix = $this->is_mass_payment ? 'mass-invoice-' : 'invoice-';
        $transaction_id = isset($this->payment_details['RefID']) ? $this->payment_details['RefID'] : 'trans-' . time();
        
        if ($this->is_mass_payment) {
            $id_part = substr($transaction_id, 0, 8);
        } else {
            $id_part = $this->entry_id;
        }
        
        $file_name = $prefix . $id_part . '-' . date('Ymd', strtotime($this->payment_details['time'])) . '.pdf';
        $file_path = $invoice_dir . '/' . $file_name;
        
        // Generate the appropriate invoice type
        if ($this->is_mass_payment) {
            $result = $this->generate_mass_invoice($file_path);
        } else {
            $result = $this->generate_single_invoice($file_path);
        }
        
        // Update entry meta for all affected entries
        $invoice_url = $upload_dir['baseurl'] . '/awards-invoices/' . $this->user_data->ID . '/' . $file_name;
        
        foreach ($this->entry_ids as $entry_id) {
            update_post_meta($entry_id, 'invoice_path', $file_path);
            update_post_meta($entry_id, 'invoice_url', $invoice_url);
            
            // Add mass payment flag if applicable
            if ($this->is_mass_payment) {
                update_post_meta($entry_id, 'is_mass_payment', true);
                update_post_meta($entry_id, 'mass_payment_entries', implode(',', $this->entry_ids));
            }
        }
        
        return $file_path;
    }
    
    /**
     * Add header with title
     */
    private function add_header() {
        // Add title
        $this->pdf->SetFont('Arial', 'B', 16);
        $this->pdf->Cell(0, 10, 'INVOICE', 0, 1, 'C');
        $this->pdf->Ln(5);
    }
    
    /**
     * Add invoice information (number, date)
     */
    private function add_invoice_info($is_mass = false) {
        // Generate invoice number
        $transaction_id = isset($this->payment_details['RefID']) ? $this->payment_details['RefID'] : 'trans-' . time();
        
        if ($is_mass) {
            $invoice_number = 'MASS-' . substr($transaction_id, 0, 8);
        } else {
            $invoice_number = $this->entry_id . '-' . substr($transaction_id, 0, 8);
        }
        
        $this->pdf->SetFont('Arial', '', 10);
        $this->pdf->Cell(95, 10, 'Invoice #: ' . $invoice_number, 0, 0);
        $this->pdf->Cell(95, 10, 'Date: ' . date('F j, Y', strtotime($this->payment_details['time'])), 0, 1, 'R');
        $this->pdf->Ln(5);
    }
    
    /**
     * Add customer and vendor information in two columns
     */
    private function add_customer_vendor_info() {
        // Set fixed positions for columns
        $left_column_x = 10;
        $right_column_x = 105;
        $line_height = 7;
        
        // Prepare customer info
        $customer_lines = array();
        $customer_lines[] = $this->user_data->first_name . ' ' . $this->user_data->last_name;
        $customer_lines[] = $this->user_data->user_email;
        
        $country = get_user_meta($this->user_data->ID, 'country', true);
        if ($country) {
            $customer_lines[] = $country;
        }
        
        $phone = get_user_meta($this->user_data->ID, 'phone', true);
        if ($phone) {
            $customer_lines[] = 'Phone: ' . $phone;
        }
        
        // Prepare vendor info
        $vendor_lines = array();
        $vendor_lines[] = 'Minimalist Photography Awards';
        $vendor_lines[] = 'Milad Safabakhsh';
        $vendor_lines[] = 'info@minimalistphotographyawards.com';
        $vendor_lines[] = 'VAT: ATU80899636';
        $vendor_lines[] = 'Naflastrasse 40, 6800 Feldkirch, Austria';
        
        // Headers
        $this->pdf->SetFont('Arial', 'B', 11);
        $this->pdf->SetX($left_column_x);
        $this->pdf->Cell(95, $line_height, 'Bill To:', 0, 0);
        
        $this->pdf->SetX($right_column_x);
        $this->pdf->Cell(95, $line_height, 'From:', 0, 1);
        
        // Content
        $this->pdf->SetFont('Arial', '', 10);
        
        // Determine max number of lines
        $max_lines = max(count($customer_lines), count($vendor_lines));
        
        // Add lines with alignment
        for ($i = 0; $i < $max_lines; $i++) {
            $this->pdf->SetX($left_column_x);
            if (isset($customer_lines[$i])) {
                $this->pdf->Cell(95, $line_height, $customer_lines[$i], 0, 0);
            } else {
                $this->pdf->Cell(95, $line_height, '', 0, 0);
            }
            
            $this->pdf->SetX($right_column_x);
            if (isset($vendor_lines[$i])) {
                if ($i == 0) {
                    $this->pdf->SetFont('Arial', 'B', 10);
                    $this->pdf->Cell(95, $line_height, $vendor_lines[$i], 0, 1);
                    $this->pdf->SetFont('Arial', '', 10);
                } else {
                    $this->pdf->Cell(95, $line_height, $vendor_lines[$i], 0, 1);
                }
            } else {
                $this->pdf->Cell(95, $line_height, '', 0, 1);
            }
        }
        
        $this->pdf->Ln(10);
    }
    
    /**
     * Add entry details for single entry
     */
    private function add_entry_details() {
        $this->pdf->SetFont('Arial', 'B', 11);
        $this->pdf->Cell(0, 7, 'Entry Details:', 0, 1);
        
        $this->pdf->SetFont('Arial', '', 10);
        $this->pdf->Cell(0, 7, 'Entry Title: ' . $this->entry_data->post_title, 0, 1);
        
        $entry_type = get_post_meta($this->entry_id, 'entrytype', true);
        $this->pdf->Cell(0, 7, 'Entry Type: ' . ucfirst($entry_type), 0, 1);
        
        $categories = get_the_terms($this->entry_id, 'entrycat');
        if ($categories && !is_wp_error($categories)) {
            $category_names = array();
            foreach ($categories as $category) {
                $category_names[] = $category->name;
            }
            $this->pdf->Cell(0, 7, 'Categories: ' . implode(', ', $category_names), 0, 1);
        }
        
        $this->pdf->Ln(10);
    }
    
    /**
     * Add payment summary for single entry
     */
    private function add_payment_summary() {
        // Set up table header
        $this->pdf->SetFillColor(240, 240, 240);
        $this->pdf->SetFont('Arial', 'B', 10);
        
        $this->pdf->Cell(95, 7, 'Description', 1, 0, 'L', true);
        $this->pdf->Cell(47.5, 7, 'Payment Method', 1, 0, 'C', true);
        $this->pdf->Cell(47.5, 7, 'Amount', 1, 1, 'R', true);
        
        $this->pdf->SetFont('Arial', '', 10);
        
        // Entry fee
        $entry_type = get_post_meta($this->entry_id, 'entrytype', true);
        $this->pdf->Cell(95, 7, 'Entry Fee - ' . ucfirst($entry_type), 1);
        $this->pdf->Cell(47.5, 7, ucfirst($this->payment_details['gateway']), 1, 0, 'C');
        
        // Format amount based on currency
        $amount = $this->payment_details['Amount'];
        if ($this->currency_code === 'IRR') {
            $formatted_amount = $this->currency_symbol . number_format((float)$amount, 0);
        } else {
            $formatted_amount = $this->currency_symbol . number_format((float)$amount, 2);
        }
        
        $this->pdf->Cell(47.5, 7, $formatted_amount, 1, 1, 'R');
        
        // Check for coupon
        $coupon_used = get_post_meta($this->entry_id, 'coupon_used', true);
        if ($coupon_used) {
            $this->pdf->Cell(95, 7, 'Discount (Coupon: ' . $coupon_used['copon'] . ')', 1);
            $this->pdf->Cell(47.5, 7, '', 1, 0, 'C');
            
            if ($coupon_used['type'] == 'percent') {
                $discount_text = $coupon_used['value'] . '%';
            } else {
                if ($this->currency_code === 'IRR') {
                    $discount_text = '-' . $this->currency_symbol . number_format((float)$coupon_used['value'], 0);
                } else {
                    $discount_text = '-' . $this->currency_symbol . number_format((float)$coupon_used['value'], 2);
                }
            }
            
            $this->pdf->Cell(47.5, 7, $discount_text, 1, 1, 'R');
        }
        
        // Total row
        $this->pdf->SetFont('Arial', 'B', 10);
        $this->pdf->Cell(142.5, 7, 'Total', 1, 0, 'R', true);
        $this->pdf->Cell(47.5, 7, $formatted_amount, 1, 1, 'R', true);
        
        $this->pdf->Ln(10);
    }
    
    /**
     * Add mass payment summary for multiple entries
     */
    private function add_mass_payment_summary() {
        // Set up table header
        $this->pdf->SetFillColor(240, 240, 240);
        $this->pdf->SetFont('Arial', 'B', 10);
        
        $this->pdf->Cell(95, 7, 'Description', 1, 0, 'L', true);
        $this->pdf->Cell(47.5, 7, 'Payment Method', 1, 0, 'C', true);
        $this->pdf->Cell(47.5, 7, 'Amount', 1, 1, 'R', true);
        
        $this->pdf->SetFont('Arial', '', 10);
        
        // Initialize total
        $total_amount = 0;
        
        // List each entry
        foreach ($this->entry_ids as $entry_id) {
            $entry = get_post($entry_id);
            if (!$entry) {
                continue;
            }
            
            // Get entry fee
            $fee = isset($this->entry_fees[$entry_id]) ? $this->entry_fees[$entry_id] : 0;
            if (!$fee) {
                $fee = get_award_entry_total_fee($entry_id, false);
            }
            
            $total_amount += $fee;
            
            // Format fee based on currency
            if ($this->currency_code === 'IRR') {
                $formatted_fee = $this->currency_symbol . number_format((float)$fee, 0);
            } else {
                $formatted_fee = $this->currency_symbol . number_format((float)$fee, 2);
            }
            
            // Add entry to invoice
            $this->pdf->Cell(95, 7, 'Entry Fee - ' . $entry->post_title, 1);
            $this->pdf->Cell(47.5, 7, ucfirst($this->payment_details['gateway']), 1, 0, 'C');
            $this->pdf->Cell(47.5, 7, $formatted_fee, 1, 1, 'R');
        }
        
        // Check for coupon
        $coupon_used = get_copon_session();
        if ($coupon_used) {
            $this->pdf->Cell(95, 7, 'Discount (Coupon: ' . $coupon_used['copon'] . ')', 1);
            $this->pdf->Cell(47.5, 7, '', 1, 0, 'C');
            
            $discount_amount = 0;
            if ($coupon_used['type'] == 'percent') {
                $discount_amount = ($total_amount * $coupon_used['value']) / 100;
                $discount_text = $coupon_used['value'] . '%';
            } else {
                $discount_amount = $coupon_used['value'];
                if ($this->currency_code === 'IRR') {
                    $discount_text = '-' . $this->currency_symbol . number_format((float)$discount_amount, 0);
                } else {
                    $discount_text = '-' . $this->currency_symbol . number_format((float)$discount_amount, 2);
                }
            }
            
            $this->pdf->Cell(47.5, 7, $discount_text, 1, 1, 'R');
            $total_amount -= $discount_amount;
        }
        
        // Format the total amount
        if ($this->currency_code === 'IRR') {
            $formatted_total = $this->currency_symbol . number_format((float)$total_amount, 0);
        } else {
            $formatted_total = $this->currency_symbol . number_format((float)$total_amount, 2);
        }
        
        // Total row
        $this->pdf->SetFont('Arial', 'B', 10);
        $this->pdf->Cell(142.5, 7, 'Total', 1, 0, 'R', true);
        $this->pdf->Cell(47.5, 7, $formatted_total, 1, 1, 'R', true);
        
        $this->pdf->Ln(10);
    }
    
    /**
     * Add transaction details
     */
    private function add_transaction_details($is_mass = false) {
        $this->pdf->SetFont('Arial', 'B', 11);
        $this->pdf->Cell(0, 7, 'Transaction Details:', 0, 1);
        
        $this->pdf->SetFont('Arial', '', 10);
        $this->pdf->Cell(0, 7, 'Transaction ID: ' . $this->payment_details['RefID'], 0, 1);
        $this->pdf->Cell(0, 7, 'Gateway: ' . ucfirst($this->payment_details['gateway']), 0, 1);
        $this->pdf->Cell(0, 7, 'Date: ' . date('F j, Y H:i:s', strtotime($this->payment_details['time'])), 0, 1);
        
        if ($is_mass) {
            $this->pdf->Cell(0, 7, 'Transaction Type: Mass Payment', 0, 1);
        }
        
        $this->pdf->Ln(10);
    }
    
    /**
     * Add footer with legal text
     */
    private function add_footer() {
        // Set position from bottom
        $this->pdf->SetY(-25);
        
        // Legal text
        $this->pdf->SetFont('Arial', 'I', 8);
        $this->pdf->Cell(0, 5, 'VAT exempt according to §6 Abs. 1 Z 1 UStG. Reverse charge applies under Article 44 and 196 of the EU VAT Directive where applicable.', 0, 1, 'C');
        $this->pdf->Cell(0, 5, 'Thank you for participating in the Minimalist Photography Awards.', 0, 1, 'C');
    }
}